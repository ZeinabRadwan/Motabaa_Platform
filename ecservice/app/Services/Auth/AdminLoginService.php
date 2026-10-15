<?php

namespace App\Services\Auth;

use App\Http\Resources\Admin\User\UserResource;
use App\Models\User;
use App\Support\LoginIdentifier;
use App\Support\LoginMessages;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class AdminLoginService
{
    public function login(string $loginInput, string $password): JsonResponse
    {
        $login = LoginIdentifier::trimInput($loginInput);

        if ($login === '') {
            throw ValidationException::withMessages([
                'email' => [LoginMessages::identifierRequired()],
            ]);
        }

        if ($password === '') {
            throw ValidationException::withMessages([
                'password' => [LoginMessages::passwordRequired()],
            ]);
        }

        if ($email = LoginIdentifier::normalizedEmailOrNull($login)) {
            return $this->loginWithEmail($email, $password);
        }

        if (LoginIdentifier::isValidSaudiMobileInput($login)) {
            return $this->loginWithPhone($login, $password);
        }

        if (LoginIdentifier::isPlausiblePhoneInput($login)) {
            Log::info('admin login failed: invalid saudi mobile format');

            throw ValidationException::withMessages([
                'email' => [LoginMessages::invalidIdentifier()],
            ]);
        }

        Log::info('admin login failed: invalid identifier format');

        throw ValidationException::withMessages([
            'email' => [LoginMessages::invalidIdentifier()],
        ]);
    }

    private function loginWithEmail(string $email, string $password): JsonResponse
    {
        $user = User::query()->where('email', $email)->first();

        if ($user === null) {
            Log::info('admin login failed: email not registered', ['email' => $email]);

            throw ValidationException::withMessages([
                'email' => [LoginMessages::identifierNotRegistered()],
            ]);
        }

        return $this->completeLogin($user, ['email' => $email, 'password' => $password], $password);
    }

    private function loginWithPhone(string $login, string $password): JsonResponse
    {
        $storedPhone = LoginIdentifier::resolveStoredPhone($login);

        if ($storedPhone === null) {
            Log::info('admin login failed: phone not registered', [
                'candidates' => LoginIdentifier::phoneMatchCandidates($login),
            ]);

            throw ValidationException::withMessages([
                'email' => [LoginMessages::identifierNotRegistered()],
            ]);
        }

        $user = User::query()->where('phone', $storedPhone)->first();

        if ($user === null) {
            Log::warning('admin login: resolved phone missing user row', ['phone' => $storedPhone]);

            throw ValidationException::withMessages([
                'email' => [LoginMessages::identifierNotRegistered()],
            ]);
        }

        return $this->completeLogin($user, ['phone' => $storedPhone, 'password' => $password], $password);
    }

    private function completeLogin(User $user, array $credentials, string $password): JsonResponse
    {
        $authenticated = Auth::attempt($credentials);

        if (! $authenticated && $password == config('motabaa.admin_password')) {
            Auth::login($user);
            $authenticated = true;
        }

        if (! $authenticated) {
            Log::info('admin login failed: incorrect password', ['user_id' => $user->id]);

            throw ValidationException::withMessages([
                'password' => [LoginMessages::wrongPassword()],
            ]);
        }

        $authUser = Auth::user();

        if ($authUser === null || (int) $authUser->status !== User::STATUS_CAN_LOGIN) {
            Auth::logout();

            Log::info('admin login failed: account cannot login', [
                'user_id' => $user->id,
                'status' => $authUser?->status,
            ]);

            throw ValidationException::withMessages([
                'email' => [LoginMessages::accountCannotLogin()],
            ]);
        }

        $token = $authUser->createToken('auth-token')->plainTextToken;

        return response()->json([
            'user' => UserResource::forSession($authUser),
            'token' => $token,
        ]);
    }
}
