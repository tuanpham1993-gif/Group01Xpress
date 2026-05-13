<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ShipmentAssignment;
use App\Models\Shipment;
use App\Models\Branch;
use App\Models\User;
use Illuminate\Http\Request;
use App\Models\ShipmentTracking;

class AssignmentController extends Controller
{
    // ════════════════════════════════════════════════════════
    // GET ALL ASSIGNMENTS
    // ════════════════════════════════════════════════════════
    public function index(Request $request)
    {
        $query = ShipmentAssignment::with([
            'shipment',
            'branch',
            'agent',
        ]);

        // filter status
        if ($request->filled('status')) {
            $query->where('assignment_status', $request->status);
        }

        // filter type
        if ($request->filled('type')) {
            $query->where('assignment_type', $request->type);
        }

        $assignments = $query
            ->orderByDesc('assigned_at')
            ->paginate(10);

        return response()->json($assignments);
    }

    // ════════════════════════════════════════════════════════
    // ADMIN MANUAL ASSIGN
    // ════════════════════════════════════════════════════════
    public function store(Request $request)
{
    $request->validate([
        'shipment_id' => 'required|exists:shipments,id',
        'branch_id' => 'required|exists:branches,id',
        'assignment_type' => 'required|in:pickup,destination',
        'agent_id' => 'nullable|exists:users,id',
    ]);

    // 🔥 AUTO ASSIGN nếu agent NULL
    if (!$request->agent_id) {

        $rejectedAgentIds = ShipmentAssignment::where('shipment_id', $request->shipment_id)
            ->where('assignment_type', $request->assignment_type)
            ->pluck('agent_id');

        $agent = User::where('role_id', 2)
            ->where('branch_id', $request->branch_id)
            ->where('status', 'active')
            ->whereNotIn('id', $rejectedAgentIds)
            ->first();

        if (!$agent) {
            return response()->json([
                'message' => 'Không còn agent phù hợp để auto assign'
            ], 422);
        }

        $request->merge(['agent_id' => $agent->id]);
    }

    $assignment = ShipmentAssignment::create([
        'shipment_id' => $request->shipment_id,
        'branch_id' => $request->branch_id,
        'agent_id' => $request->agent_id,
        'assignment_type' => $request->assignment_type,
        'assignment_status' => 'pending',
        'assigned_at' => now(),
    ]);

    return response()->json([
        'message' => 'Assign thành công',
        'assignment' => $assignment->load(['shipment','branch','agent'])
    ]);
}

    // ════════════════════════════════════════════════════════
    // AGENT ACCEPT ASSIGNMENT
    // ════════════════════════════════════════════════════════
    public function accept($id)
    {
        $assignment = ShipmentAssignment::with('shipment')
            ->findOrFail($id);

        if ($assignment->assignment_status !== 'pending') {
            return response()->json([
                'message' => 'Assignment không còn pending.'
            ], 422);
        }

        $assignment->assignment_status = 'accepted';
        $assignment->save();

        // pickup accept -> shipment thành Picked Up
        if ($assignment->assignment_type === 'pickup') {// nếu agent nhận nhiệm vụ pickup thì sẽ update trạng thái shipment thành "Picked Up"

            $shipment = $assignment->shipment;

            $shipment->shipment_status = 'Picked Up';
            $shipment->save();

            ShipmentTracking::create([
                'shipment_id' => $shipment->id,
                'status' => 'Picked Up',
                'updated_by' => auth()->id(),
                'note' => 'Agent đã nhận đơn và đang đi lấy hàng.',
            ]);
        }
        return response()->json([
            'message' => 'Agent accepted assignment',
            'assignment' => $assignment
        ]);
    }

    // ════════════════════════════════════════════════════════
    // AGENT REJECT ASSIGNMENT
    // ════════════════════════════════════════════════════════
    public function reject($id)
    {
        $assignment = ShipmentAssignment::findOrFail($id);

        if ($assignment->assignment_status !== 'pending') {
            return response()->json([
                'message' => 'Assignment không còn pending.'
            ], 422);
        }

        // reject current assignment
        $assignment->assignment_status = 'rejected';
        $assignment->save();

        // lấy toàn bộ agent đã reject assignment này
        $rejectedAgentIds = ShipmentAssignment::where(
            'shipment_id',
            $assignment->shipment_id
        )
            ->where('assignment_type', $assignment->assignment_type)
            ->where('assignment_status', 'rejected')
            ->pluck('agent_id');

        // tìm agent khác chưa reject
        $newAgent = User::where('role_id', 2)
            ->where('branch_id', $assignment->branch_id)
            ->where('status', 'active')
            ->whereNotIn('id', $rejectedAgentIds)
            ->first();

        // nếu có agent khác -> assign lại
        if ($newAgent) {

            $newAssignment = ShipmentAssignment::create([
                'shipment_id' => $assignment->shipment_id,
                'branch_id' => $assignment->branch_id,
                'agent_id' => $newAgent->id,

                'assignment_type' => $assignment->assignment_type,
                'assignment_status' => 'pending',

                'assigned_at' => now(),
            ]);

            return response()->json([
                'message' => 'Agent rejected. Re-assigned automatically.',
                'assignment' => $newAssignment->load([
                    'shipment',
                    'branch',
                    'agent'
                ])
            ]);
        }

        // không còn agent -> manual_required
        $manualAssignment = ShipmentAssignment::create([
            'shipment_id' => $assignment->shipment_id,
            'branch_id' => $assignment->branch_id,

            'assignment_type' => $assignment->assignment_type,
            'assignment_status' => 'manual_required',

            'assigned_at' => now(),
        ]);

        return response()->json([
            'message' => 'Không còn agent khả dụng. Admin cần assign thủ công.',
            'assignment' => $manualAssignment
        ]);
    }

    // ════════════════════════════════════════════════════════
    // DELETE ASSIGNMENT
    // ════════════════════════════════════════════════════════
    public function destroy($id)
    {
        $assignment = ShipmentAssignment::findOrFail($id);

        $assignment->delete();

        return response()->json([
            'message' => 'Xóa assignment thành công.'
        ]);
    }
}