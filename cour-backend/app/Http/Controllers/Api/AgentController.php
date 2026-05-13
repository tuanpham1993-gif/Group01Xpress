<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;


class AgentController extends Controller
{
    public function index()
    {
        $agents = User::where('role_id', 2)
            ->orderBy('id', 'desc')
            ->get();

        return response()->json($agents);
    }

    // ================= CREATE =================
    public function store(Request $request)
    {
        $validated = $request->validate([
            'full_name' => 'required|string|max:255',

            'username' => 'required|string|max:255|unique:users,username',

            'email' => 'required|email|max:255|unique:users,email',

            'phone' => [
                'required',
                'string',
                'unique:users,phone',
                'regex:/^(0|\+84)[0-9]{9}$/'
            ],

            'address' => 'required|string|max:255',

            'password' => 'required|min:6',

            'branch_id' => 'required|exists:branches,id',

            'status' => 'required|in:active,inactive',
        ]);

        $agent = User::create([
            'full_name' => $validated['full_name'],
            'username' => $validated['username'],
            'email' => $validated['email'],
            'phone' => $validated['phone'],
            'address' => $validated['address'],
            'password' => Hash::make($validated['password']),
            'branch_id' => $validated['branch_id'],
            'role_id' => 2,
            'status' => $validated['status']
        ]);

        return response()->json([
            'message' => 'Agent created successfully',
            'agent' => $agent
        ], 201);
    }

    // ================= UPDATE =================
    public function update(Request $request, $id)
    {
        $agent = User::where('role_id', 2)->findOrFail($id);

        $validated = $request->validate([
            'full_name' => 'required|string|max:255',

            'username' => 'required|string|max:255|unique:users,username,' . $id,

            'email' => 'required|email|max:255|unique:users,email,' . $id,

            'phone' => [
                'required',
                'string',
                'unique:users,phone,' . $id,
                'regex:/^(0|\+84)[0-9]{9}$/'
            ],

            'address' => 'required|string|max:255',

            'branch_id' => 'required|exists:branches,id',

            'status' => 'required|in:active,inactive',
        ]);

        $agent->update($validated);

        // password optional
        if ($request->filled('password')) {
            $agent->password = Hash::make($request->password);
            $agent->save();
        }

        return response()->json([
            'message' => 'Agent updated successfully',
            'agent' => $agent
        ]);
    }

    // ================= TOGGLE STATUS =================
    public function toggleStatus($id)
    {
        $agent = User::where('role_id', 2)->findOrFail($id);

        $agent->status = $agent->status === 'active'
            ? 'inactive'
            : 'active';

        $agent->save();

        return response()->json([
            'message' => 'Status updated',
            'status' => $agent->status
        ]);
    }

    // ================= DELETE =================
    public function destroy($id)
    {
        $agent = User::where('role_id', 2)->findOrFail($id);

        if ($agent->shipmentAssignments()->exists()) {
            return response()->json([
                'message' => 'Cannot delete agent because assigned shipments exist'
            ], 400);
        }

        $agent->delete();

        return response()->json([
            'message' => 'Agent deleted successfully'
        ]);
    }
}