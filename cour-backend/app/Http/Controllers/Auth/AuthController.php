<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;
use Tymon\JWTAuth\Facades\JWTAuth;

class AuthController extends Controller
{
    //
    public function register(Request $request)
    {
        $request->validate([
            'username' => 'required|unique:users',
            'email' => 'required|email|unique:users',
            'password' => 'required|min:6',
            'full_name' => 'required',
            'phone' => 'required|regex:/^[0-9]{10,15}$/|unique:users,phone',
            'address' => 'required',
            'city' => 'required'
        ]);

        $user = User::create([
            'username' => $request->username,
            'email' => $request->email,
            'password' => $request->password,
            'role_id' => 3,
            'branch_id' => null,
            'full_name' => $request->full_name,
            'phone' => $request->phone,
            'address' => $request->address,
            'city' => $request->city,
            'status' => 'active'
        ]);

        // ❗ Ẩn password
        $user->makeHidden(['password']);

        return response()->json([
            'message' => 'Registration successful',
            'user' => $user
        ]);
    }

    // LOGIN (ĐÚNG CHUẨN SANCTUM)
    public function login(Request $request)
    {
        $request->validate([
            'username' => 'required',
            'password' => 'required'
        ]);

        $credentials = [
            'password' => $request->password
        ];

        // thử username
        $credentials['username'] = $request->username;

        if (!$token = Auth::attempt($credentials)) {

            // thử email
            unset($credentials['username']);
            $credentials['email'] = $request->username;

            if (!$token = Auth::attempt($credentials)) {
                return response()->json([
                    'message' => 'Invalid username or password'
                ], 401);
            }
        }

        return response()->json([
            'token' => $token,
            'user' => Auth::user()
        ]);
    }

}