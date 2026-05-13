<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Shipment;
class ReportController extends Controller
{
    
    public function getShipments(Request $request)
    {
        try {
            // Lấy toàn bộ shipments kèm quan hệ
            $shipments = Shipment::with(['branch', 'invoice', 'details'])
                ->orderBy('booking_date', 'desc')
                ->get();

            // --- 1. Thống kê theo ngày (Date-wise) ---
            $dateWise = $shipments->groupBy(function ($s) {
                return substr($s->booking_date, 0, 10); // "YYYY-MM-DD"
            })->map(function ($group, $date) {
                return [
                    'date' => $date,
                    'total' => $group->count(),
                    'delivered' => $group->where('shipment_status', 'Delivered')->count(),
                    'cancelled' => $group->where('shipment_status', 'Cancelled')->count(),
                    'pending' => $group->whereIn('shipment_status', ['Pending', 'Picked Up', 'In Transit'])->count(),
                    'revenue' => $group->sum(fn($s) => optional($s->invoice)->total_amount ?? 0),
                ];
            })->values();

            // --- 2. Thống kê theo thành phố (City-wise) ---
            $cityWise = $shipments->groupBy('receiver_city')->map(function ($group, $city) {
                return [
                    'city' => $city ?: 'N/A',
                    'incoming' => $group->count(),
                ];
            })->values();

            $cityWiseOutgoing = $shipments->groupBy('sender_city')->map(function ($group, $city) {
                return [
                    'city' => $city ?: 'N/A',
                    'outgoing' => $group->count(),
                ];
            })->values();

            // Merge incoming + outgoing theo city
            $cityMerged = collect($cityWise)->map(function ($item) use ($cityWiseOutgoing) {
                $out = $cityWiseOutgoing->firstWhere('city', $item['city']);
                $item['outgoing'] = $out['outgoing'] ?? 0;
                return $item;
            });

            // --- 3. Thống kê theo chi nhánh (Branch-wise) ---
            $branchWise = $shipments->groupBy('branch_id')->map(function ($group, $branchId) {
                $branch = optional($group->first()->branch);
                return [
                    'branch_id' => $branchId,
                    'branch_name' => $branch->branch_name ?? 'N/A',
                    'city' => $branch->city ?? 'N/A',
                    'total' => $group->count(),
                    'delivered' => $group->where('shipment_status', 'Delivered')->count(),
                    'cancelled' => $group->where('shipment_status', 'Cancelled')->count(),
                    'revenue' => $group->sum(fn($s) => optional($s->invoice)->total_amount ?? 0),
                ];
            })->values();

            // --- 4. Quick Stats (Dashboard cards) ---
            $now = now();
            $delayed = $shipments->filter(function ($s) use ($now) {
                if (!$s->expected_arrival_time)
                    return false;
                $expected = \Carbon\Carbon::parse($s->expected_arrival_time);
                $actual = $s->delivery_date ? \Carbon\Carbon::parse($s->delivery_date) : $now;
                return $actual->gt($expected) && $s->shipment_status !== 'Delivered';
            })->count();

            $stats = [
                'total' => $shipments->count(),
                'delivered' => $shipments->where('shipment_status', 'Delivered')->count(),
                'cancelled' => $shipments->where('shipment_status', 'Cancelled')->count(),
                'pending' => $shipments->where('shipment_status', 'Pending')->count(),
                'in_transit' => $shipments->where('shipment_status', 'In Transit')->count(),
                'picked_up' => $shipments->where('shipment_status', 'Picked Up')->count(),
                'revenue' => $shipments->sum(fn($s) => optional($s->invoice)->total_amount ?? 0),
            ];

            return response()->json([
                'success' => true,
                'stats' => $stats,
                'shipments' => $shipments,          // raw list cho table
                'date_wise' => $dateWise,           // cho Line chart
                'city_wise' => $cityMerged,         // cho Pie/Bar chart thành phố
                'branch_wise' => $branchWise,         // cho Bar chart chi nhánh
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

}
