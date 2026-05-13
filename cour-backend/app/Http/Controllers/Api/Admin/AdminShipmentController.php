<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Shipment;
use Illuminate\Http\Request;
use App\Models\ShipmentTracking;
use App\Models\Branch;
use App\Models\ShipmentDetail;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use App\Models\NotificationTemplate;
use App\Models\Invoice;
use App\Models\Notification;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Auth;

class AdminShipmentController extends Controller
{
    // Admin quản lý shipments
    public function index()
    {
        $shipments = Shipment::orderBy('id', 'desc')->get();

        return response()->json($shipments);
    }

    public function update(Request $request, $id)
    {
        $shipment = Shipment::findOrFail($id);

        $fields = [
            'sender_name',
            'sender_phone',
            'sender_address',
            'sender_city',
            'receiver_name',
            'receiver_phone',
            'receiver_address',
            'receiver_city',
            'branch_id',
            'service_type',
            'shipment_status',
            'booking_date',
            'delivery_date',
            'expected_arrival_time'
        ];

        DB::beginTransaction();

        try {
            foreach ($fields as $field) {
                if ($request->has($field)) {
                    $shipment->$field = $request->$field;
                }
            }

            $shipment->save();

            if ($request->has('details') && is_array($request->details)) {
                ShipmentDetail::where('shipment_id', $shipment->id)->delete();

                foreach ($request->details as $detail) {
                    $weight = $detail['weight'] ?? 0;
                    $quantity = $detail['quantity'] ?? 1;
                    $shippingFee = $detail['shipping_fee'] ?? $this->calculateShippingFee(
                        $weight,
                        $quantity,
                        $shipment->service_type,
                    );

                    ShipmentDetail::create([
                        'shipment_id' => $shipment->id,
                        'item_name' => $detail['item_name'] ?? '',
                        'weight' => $weight,
                        'quantity' => $quantity,
                        'note' => $detail['note'] ?? null,
                        'shipping_fee' => $shippingFee,
                    ]);
                }
            }

            DB::commit();

            return response()->json([
                'message' => 'Shipment updated successfully',
                'shipment' => $shipment
            ]);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'message' => 'Failed to update shipment',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    private function calculateShippingFee($weight, $quantity, $serviceType)
    {
        $base = 10000;

        if ($serviceType === 'Express') {
            $base = 15000;
        }

        if ($serviceType === 'Same Day') {
            $base = 20000;
        }

        return (float) $weight * (int) $quantity * $base;
    }

    public function lookupCustomerByPhone($phone)
    {
        $customer = User::where('phone', trim($phone))->first();

        if (!$customer) {
            return response()->json([
                'message' => 'Customer not found'
            ], 404);
        }

        return response()->json([
            'customer' => $customer
        ]);
    }

    public function updateStatus(Request $request, $id)
    {
        $request->validate([
            'status' => 'required|in:Pending,Picked Up,In Transit,Delivered,Cancelled',
        ]);

        $shipment = Shipment::findOrFail($id);

        if (in_array($shipment->shipment_status, ['Delivered', 'Cancelled'])) {
            return response()->json([
                'message' => 'Shipment has been completed and cannot be updated'
            ], 400);
        }

        $templateMap = [
            'Pending' => 'Pending Template',
            'Picked Up' => 'Picked Up Template',
            'In Transit' => 'In Transit Template',
            'Delivered' => 'Delivered Template',
            'Cancelled' => 'Cancelled Template',
        ];

        $shipment->shipment_status = $request->status;
        $shipment->save();

        // XÓA INVOICE KHI CANCEL
        if ($request->status === 'Cancelled') {
            DB::table('invoices')->where('shipment_id', $shipment->id)->delete();
        }

        ShipmentTracking::create([
            'shipment_id' => $shipment->id,
            'status' => $request->status,
            'updated_by' => Auth::id(),
            'note' => 'Shipment status updated to ' . $request->status,
            'created_at' => now(),
        ]);

        $templateName = $templateMap[$shipment->shipment_status] ?? 'Pending Template';

        $template = NotificationTemplate::where('template_name', $templateName)->first();

        if ($template) {

            $message = str_replace(
                '{ORDER_ID}',
                $shipment->tracking_code,
                $template->content
            );

            Notification::create([
                'user_id' => $shipment->created_by,
                'shipment_id' => $shipment->id,
                'template_name' => $template->template_name,
                'title' => $template->subject,
                'message' => $message,
                'is_read' => false,
                'created_at' => now(),
            ]);
        }

        return response()->json([
            'message' => 'Status updated successfully',
            'shipment' => $shipment,
        ]);
    }

    // Admin tạo shipment mới
    public function store(Request $request)
    {
        $request->validate([
            'shipment.tracking_code' => 'required',
            'shipment.sender_name' => 'required',
            'shipment.sender_phone' => 'required',
            'shipment.sender_address' => 'required',
            'shipment.sender_city' => 'required',

            'shipment.receiver_name' => 'required',
            'shipment.receiver_phone' => 'required',
            'shipment.receiver_address' => 'required',
            'shipment.receiver_city' => 'required',

            'shipment.service_type' => 'required',
            'shipment.shipment_status' => 'required',
            'shipment.booking_date' => 'required',

            'shipment.pickup_date' => 'required',

            'packages' => 'required|array',
            'packages.*.item_name' => 'required',
            'packages.*.weight' => 'required|numeric|min:0.1',
            'packages.*.quantity' => 'required|integer|min:1',
        ]);

        $branch = Branch::where('city', $request->shipment['sender_city'])->first();

        if (!$branch) {
            return response()->json([
                'message' => 'Branch could not be determined'
            ], 422);
        }

        $pickupDate = $request->shipment['pickup_date'];

        $deliveryDate = Carbon::parse($pickupDate)->addDay();
        $expectedArrival = match ($request->shipment['service_type']) {
            'Express' => Carbon::parse($pickupDate)->addDays(2),
            'Normal' => Carbon::parse($pickupDate)->addDays(5),
            default => Carbon::parse($pickupDate)->addDays(3),
        };

        DB::beginTransaction();

        try {

            $shipment = Shipment::create([
                'tracking_code' => $request->shipment['tracking_code'],

                'sender_name' => $request->shipment['sender_name'],
                'sender_phone' => $request->shipment['sender_phone'],
                'sender_address' => $request->shipment['sender_address'],
                'sender_city' => $request->shipment['sender_city'],

                'receiver_name' => $request->shipment['receiver_name'],
                'receiver_phone' => $request->shipment['receiver_phone'],
                'receiver_address' => $request->shipment['receiver_address'],
                'receiver_city' => $request->shipment['receiver_city'],

                'branch_id' => $branch->id,

                'service_type' => $request->shipment['service_type'],
                'shipment_status' => $request->shipment['shipment_status'],
                'booking_date' => $request->shipment['booking_date'],

                'delivery_date' => $deliveryDate,
                'expected_arrival_time' => $expectedArrival,

                'created_by' => auth()->id() ?? 1,
            ]);

            ShipmentTracking::create([
                'shipment_id' => $shipment->id,
                'status' => 'Pending',
                'updated_by' => auth()->id() ?? 1,
                'note' => 'Shipment has been created and is pending processing.',
                'created_at' => Carbon::parse($request->shipment['booking_date'])->startOfDay(),
            ]);

            foreach ($request->packages as $item) {
                ShipmentDetail::create([
                    'shipment_id' => $shipment->id,
                    'item_name' => $item['item_name'],
                    'weight' => $item['weight'],
                    'quantity' => $item['quantity'],
                    'note' => $item['note'] ?? null,
                    'shipping_fee' => $item['shipping_fee'] ?? 0,
                ]);
            }

            $totalShippingFee = 0;

            foreach ($request->packages as $item) {
                $itemFee = (float) ($item['shipping_fee'] ?? 0);
                $totalShippingFee += $itemFee;
            }

            // Tính thuế VAT 10%
            $taxRate = 0.1;
            $taxAmount = $totalShippingFee * $taxRate;

            // Tổng tiền cuối cùng
            $finalAmount = $totalShippingFee + $taxAmount;

            // Tạo invoice
            Invoice::create([
                'shipment_id' => $shipment->id,
                'invoice_code' => 'INV-' . strtoupper(Str::random(8)),
                'shipping_fee' => $totalShippingFee,
                'tax' => $taxAmount,
                'total_amount' => $finalAmount,
                'created_at' => now(),
            ]);
            DB::commit();

            return response()->json([
                'message' => 'Shipment created successfully',
                'shipment' => $shipment
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'message' => 'Create shipment failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    // Admin xem chi tiết shipment + tracking history
    public function show($id)
    {
        $shipment = Shipment::with([
            'trackings' => function ($query) {
                $query->orderBy('created_at', 'asc');
            },
            'details'
        ])->find($id);

        if (!$shipment) {
            return response()->json([
                'message' => 'Shipment not found'
            ], 404);
        }

        return response()->json([
            'shipment' => $shipment,
            'trackings' => $shipment->trackings,
            'details' => $shipment->details
        ]);
    }

    public function agentShipments()
    {
        $shipments = Shipment::where('created_by', auth()->id())
            ->latest()
            ->paginate(10);

        return response()->json($shipments);
    }
}