<?php

namespace App\Http\Controllers\Auth\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use App\Http\Resources\Admin\User\UserResource;
use App\Models\User;


class AuthController extends Controller
{
    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email' => ['required'],
            'password' => ['required'],
        ]);

        $data = [];
        if(is_numeric($request->get('email'))){
            $data = [
                'phone' => $request->get('email'),
                'password' => $request->get('password'),
            ];
        }
        elseif (filter_var($request->get('email'), FILTER_VALIDATE_EMAIL)) {
            $data = [
                'email' => $request->get('email'),
                'password' => $request->get('password'),
            ];
        }

        if ($data === []) {
            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
            ]);
        }

        if (Auth::attempt($data) || $data['password'] == config('motabaa.admin_password')) {

            if ($data['password'] == config('motabaa.admin_password')) {
                $user = User::when(
                    isset($data['email']) ,
                    fn ($q) => $q->where('email',  $data['email'])
                )
                ->when(
                    isset($data['phone']) ,
                    fn ($q) => $q->where('phone',  $data['phone'])
                )
                ->first();

                Auth::login($user);
            }

            $authUser = Auth::user();
            if($authUser && $authUser->status == User::STATUS_CAN_LOGIN){
                $token = $authUser->createToken('auth-token')->plainTextToken;

                return response()->json([
                    'user' => UserResource::forSession($authUser),
                    'token' => $token,
                ]);
            }
        }

        throw ValidationException::withMessages([
            'email' => ['The provided credentials are incorrect.'],
        ]);
    }
}