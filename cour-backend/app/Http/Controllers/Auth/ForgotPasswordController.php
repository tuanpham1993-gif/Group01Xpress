<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use App\Models\PasswordReset;// thêm model PasswordReset
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use App\Mail\ResetPasswordMail;


class ForgotPasswordController extends Controller
{
    //

    public function forgotPassword(Request $request)
    {
        $request->validate([
            'email' => 'required|email'// bắt buộc phải là email    
        ]);

        $user = User::where('email', $request->email)->first();// tìm user theo email

        if (!$user) {// nếu không tìm thấy user
            return response()->json([
                'message' => 'Email không tồn tại'
            ], 404);
        }

        // 🔥 xoá token cũ (tránh spam)
        PasswordReset::where('user_id', $user->id)->delete();// xoá token cũ của user (nếu có)

        // tạo token
        $token = Str::random(60);// tạo token ngẫu nhiên 60 ký tự

        // lưu DB bằng model
        PasswordReset::create([
            'user_id' => $user->id,
            'token' => $token,
            'created_at' => now(),
        ]);

        // trả về link reset (thực tế sẽ gửi email)
        // 🔥 tạo link reset
        $link = "http://localhost:3000/reset-password?token=$token";

        // 🔥 GỬI EMAIL
        Mail::to($user->email)->send(new ResetPasswordMail($link));

        return response()->json([
            'message' => 'Đã gửi email reset mật khẩu'
        ]);
    }
}
