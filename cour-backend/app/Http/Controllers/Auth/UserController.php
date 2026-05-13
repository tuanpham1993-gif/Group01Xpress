<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;
use App\Models\Shipment;

class UserController extends Controller
{
    //
    public function updateProfile(Request $request)
    {
        $user = auth()->user();

        $request->validate([
            'full_name' => 'sometimes|required',
            'email' => 'sometimes|required|email|unique:users,email,' . $user->id,
            'phone' => 'sometimes|required|regex:/^[0-9]{10,15}$/|unique:users,phone,' . $user->id,
            'address' => 'nullable',
            'city' => 'nullable',

            // password
            'old_password' => 'nullable',
            'password' => 'nullable|min:6',
        ]);

        // update info
        $user->fill($request->only([
            'full_name',
            'email',
            'phone',
            'address',
            'city'
        ]));

        // đổi mật khẩu
        if ($request->filled('password')) {

            if (!$request->filled('old_password')) {
                return response()->json([
                    'message' => 'Please enter your old password'
                ], 422);
            }

            if (!Hash::check($request->old_password, $user->password)) {
                return response()->json([
                    'message' => 'Old password is incorrect'
                ], 422);
            }

            $user->password = Hash::make($request->password);
        }

        $user->save();

        return response()->json([
            'message' => 'Updated successfully',
            'user' => $user
        ]);
    }

    public function getProfile()
    {
        return response()->json([
            'user' => Auth::user() // lấy thông tin user hiện tại
        ]);
    }

    public function getCustomers(Request $request)
    {
        $query = User::where('role_id', 3); // customer

        // search
        if ($request->search) {
            $search = $request->search;

            $query->where(function ($q) use ($search) {
                $q->where('full_name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%")
                    ->orWhere('city', 'like', "%{$search}%")
                    ->orWhere('id', $search);
            });
        }

        $customers = $query->orderBy('id', 'desc')->get();

        return response()->json($customers);
    }

    public function getCustomerDetail($id)
    {
        $customer = User::where('role_id', 3)->findOrFail($id);

        // lấy lịch sử shipment của khách hàng
        $shipments = Shipment::where('created_by', $id)
            ->orderBy('id', 'desc')
            ->get();

        return response()->json([
            'customer' => $customer,
            'shipments' => $shipments
        ]);
    }

    public function updateCustomer(Request $request, $id)
    {
        $customer = User::where('role_id', 3)->findOrFail($id);

        $request->validate([
            'full_name' => 'required',
            'email' => 'required|email|unique:users,email,' . $customer->id,
            'phone' => 'required|unique:users,phone,' . $customer->id,
            'address' => 'nullable',
            'city' => 'nullable',
        ]);

        $customer->update([
            'full_name' => $request->full_name,
            'email' => $request->email,
            'phone' => $request->phone,
            'address' => $request->address,
            'city' => $request->city,
        ]);

        return response()->json([
            'message' => 'Customer updated successfully',
            'customer' => $customer
        ]);
    }
}