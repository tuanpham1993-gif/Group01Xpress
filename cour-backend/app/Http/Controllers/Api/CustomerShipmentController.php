<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Shipment;
use App\Models\ShipmentDetail;
use App\Models\ShipmentTracking;
use Illuminate\Support\Facades\DB;
use App\Models\Branch;
use Carbon\Carbon;
use App\Models\NotificationTemplate;
use App\Models\Notification;
use App\Models\Invoice;
use Illuminate\Support\Str;
use App\Models\ShipmentAssignment;
use App\Models\User;

class CustomerShipmentController extends Controller
{
    

    // Customer xem danh sách đơn + lọc + phân trang
    public function myShipments(Request $request)
    {
        $query = Shipment::where('created_by', auth()->id());

        if ($request->filled('tracking_code')) {
            $query->where('tracking_code', 'like', '%' . $request->tracking_code . '%');
        }

        if ($request->filled('status')) {
            $query->where('shipment_status', $request->status);
        }

        if ($request->filled('from_date')) {
            $query->whereDate('delivery_date', '>=', $request->from_date);
        }

        if ($request->filled('to_date')) {
            $query->whereDate('delivery_date', '<=', $request->to_date);
        }

        if ($request->filled('month')) {
            $query->whereMonth('delivery_date', $request->month);
        }

        if ($request->filled('year')) {
            $query->whereYear('delivery_date', $request->year);
        }

        return response()->json(
            $query->orderBy('id', 'desc')->paginate(10)
        );
    }

    public function trackShipment($trackingCode)
    {
        // Lấy shipment theo tracking code
        $shipment = Shipment::where('tracking_code', $trackingCode)->first();

        if (!$shipment) {
            return response()->json(['message' => 'Tracking code not found'], 404);
        }

        // Lấy lịch sử tracking thực tế
        $history = ShipmentTracking::where('shipment_id', $shipment->id)
            ->orderBy('created_at', 'asc')
            ->get(['id', 'shipment_id', 'status', 'note', 'updated_by', 'created_at']);

        // Trả về JSON với 2 key riêng biệt
        return response()->json([
            'shipment' => [
                'tracking_code' => $shipment->tracking_code,
                'shipment_status' => $shipment->shipment_status,
                'booking_date' => $shipment->booking_date,
                'delivery_date' => $shipment->delivery_date,
                'expected_arrival_time' => $shipment->expected_arrival_time,
                'sender_name' => $shipment->sender_name,
                'receiver_name' => $shipment->receiver_name,
                'service_type' => $shipment->service_type,
            ],
            'history' => $history
        ]);
    }

    

    // hủy đơn hàng (chỉ khi đang Pending)
    public function destroy($id)
    {
        $shipment = Shipment::where('id', $id)
            ->where('created_by', auth()->id())
            ->firstOrFail();

        if ($shipment->shipment_status !== 'Pending') {
            return response()->json([
                'message' => 'Only pending shipments can be cancelled'
            ], 400);
        }

        $shipment->update([
            'shipment_status' => 'Cancelled'
        ]);

        ShipmentTracking::create([
            'shipment_id' => $shipment->id,
            'status' => 'Cancelled',
            'updated_by' => auth()->id(),
        ]);

        $template = NotificationTemplate::where('template_name', 'Cancelled Template')
            ->where('is_active', true)
            ->first();

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
            'message' => 'Shipment cancelled successfully',
            'shipment' => $shipment
        ]);
    }


    // Customer tạo đơn hàng mới
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


            // ================= AUTO ASSIGN PICKUP AGENT =================

            // agent chi nhánh người gửi

            $pickupAgent = User::where('role_id', 2)
                ->where('branch_id', $branch->id)
                ->where('status', 'active')
                ->inRandomOrder()
                ->first();

            if ($pickupAgent) {

                ShipmentAssignment::create([
                    'shipment_id' => $shipment->id,
                    'agent_id' => $pickupAgent->id,
                    'branch_id' => $pickupAgent->branch_id,

                    'assignment_type' => 'pickup',
                    'assignment_status' => 'pending',

                    'assigned_at' => now(),
                ]);

                Notification::create([
                    'user_id' => $pickupAgent->id,
                    'shipment_id' => $shipment->id,

                    'title' => 'New Pickup Assignment',

                    'message' => 'You have been assigned a pickup order: '
                        . $shipment->tracking_code,

                    'is_read' => false,
                    'created_at' => now(),
                ]);

            } else {

                ShipmentAssignment::create([
                    'shipment_id' => $shipment->id,
                    'branch_id' => $branch->id,

                    'assignment_type' => 'pickup',
                    'assignment_status' => 'manual_required',

                    'assigned_at' => now(),
                ]);

            }



            // ================= DESTINATION BRANCH AGENT =================

            // tìm chi nhánh người nhận

            $receiverBranch = Branch::where(
                'city',
                $request->shipment['receiver_city']
            )->first();

            if ($receiverBranch) {

                $destinationAgent = User::where('role_id', 2)
                    ->where('branch_id', $receiverBranch->id)
                    ->where('status', 'active')
                    ->where('id', '!=', optional($pickupAgent)->id)
                    ->inRandomOrder()
                    ->first();

                if ($destinationAgent) {

                    ShipmentAssignment::create([
                        'shipment_id' => $shipment->id,
                        'agent_id' => $destinationAgent->id,
                        'branch_id' => $destinationAgent->branch_id,

                        'assignment_type' => 'destination',
                        'assignment_status' => 'pending',

                        'assigned_at' => now(),
                    ]);

                    Notification::create([
                        'user_id' => $destinationAgent->id,
                        'shipment_id' => $shipment->id,

                        'title' => 'New Pickup Assignment',

                        'message' => 'You have been assigned a pickup order: '
                            . $shipment->tracking_code,

                        'is_read' => false,
                        'created_at' => now(),
                    ]);

                } 
                else {

                    ShipmentAssignment::create([
                        'shipment_id' => $shipment->id,
                        'branch_id' => $receiverBranch->id,

                        'assignment_type' => 'destination',
                        'assignment_status' => 'manual_required',

                        'assigned_at' => now(),
                    ]);
                  
                }
            }

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

            $templateMap = [
                'Pending' => 'Pending Template',
                'Picked Up' => 'Picked Up Template',
                'In Transit' => 'In Transit Template',
                'Delivered' => 'Delivered Template',
                'Cancelled' => 'Cancelled Template',
            ];

            $templateName = $templateMap[$shipment->shipment_status] ?? 'Pending Template';

            $template = NotificationTemplate::where('template_name', $templateName)
                ->where('is_active', true)
                ->first();

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

            $totalShippingFee = 0;
            foreach ($request->packages as $item) {
                $itemFee = $item['shipping_fee'] ?? 0;
                $totalShippingFee += $itemFee;
            }

            $taxRate = 0.1; // Thuế VAT 10%
            $taxAmount = $totalShippingFee * $taxRate;
            $finalAmount = $totalShippingFee + $taxAmount;

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
}