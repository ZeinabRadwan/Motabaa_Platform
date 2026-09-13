<?php

namespace App\Http\Controllers\Auth\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;

class PasswordResetController extends Controller
{
    // Generate and send password reset email
    public function create(Request $request)
    {
        // app()->setLocale('ar');
        $request->validate([
            'email' => 'required|email',
        ]);

        $response = Password::sendResetLink($request->only('email'));

        if ($response === Password::RESET_LINK_SENT) {
            return response()->json(['message' => 'Password reset link sent successfully', 'status' => true], 200);
        } else {
            return response()->json(['message' => 'Failed to send reset link', 'status' => false], 500);
        }
    }

    // Reset the password
    public function reset(Request $request, $token)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required|confirmed|min:8',
        ]);

        $response = Password::reset($request->only('email', 'password', 'password_confirmation', 'token'), function ($user, $password) {
            $user->password = bcrypt($password);
            $user->save();
        });

        if ($response === Password::PASSWORD_RESET) {
            return response()->json(['message' => 'Password reset successful', 'status' => true], 200);
        } else {
            return response()->json(['message' => 'Failed to reset password', 'status' => false], 500);
        }
    }
}
