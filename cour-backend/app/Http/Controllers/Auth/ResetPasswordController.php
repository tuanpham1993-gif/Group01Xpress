<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use App\Models\PasswordReset;

class ResetPasswordController extends Controller
{
    //
    public function resetPassword(Request $request)
    {
        $request->validate([
            'token' => 'required',
            'password' => 'required|min:6'
        ]);

        // tìm token
        $reset = PasswordReset::where('token', $request->token)->first();

        if (!$reset) {
            return response()->json([
                'message' => 'Token is invalid or has expired'
            ], 400);
        }

        // tìm user
        $user = User::find($reset->user_id);

        // update password
        $user->password = Hash::make($request->password);
        $user->save();

        // xoá token
        $reset->delete();

        return response()->json([
            'message' => 'Password changed successfully'
        ]);
    }
}