<?php

namespace App\Http\Controllers\Auth\Api;

use App\Http\Controllers\Controller;
use App\Services\Auth\PasswordResetService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\ValidationException;

class PasswordResetController extends Controller
{
    public function __construct(
        private readonly PasswordResetService $passwordResetService,
    ) {}

    public function create(Request $request)
    {
        $request->validate([
            'email' => ['required', 'string', 'max:255'],
        ]);

        try {
            $result = $this->passwordResetService->sendResetLinkForIdentifier($request->input('email'));
        } catch (ValidationException $e) {
            throw $e;
        }

        if ($result['response'] === Password::RESET_LINK_SENT) {
            return response()->json([
                'message' => __('passwords.sent'),
                'status' => true,
            ], 200);
        }

        return response()->json([
            'message' => __('validation.password_reset.mail_failed'),
            'status' => false,
        ], 503);
    }

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
        }

        return response()->json(['message' => 'Failed to reset password', 'status' => false], 422);
    }
}
