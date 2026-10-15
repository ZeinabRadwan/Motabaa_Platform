<?php

namespace App\Services\Auth;

use App\Models\User;
use App\Support\LoginIdentifier;
use App\Support\LoginMessages;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\ValidationException;

class PasswordResetService
{
    /**
     * @return array{sent: bool, response: string}
     */
    public function sendResetLinkForIdentifier(string $identifier): array
    {
        $identifier = LoginIdentifier::trimInput($identifier);
        if ($identifier === '') {
            throw ValidationException::withMessages([
                'email' => [LoginMessages::identifierRequired()],
            ]);
        }

        if (LoginIdentifier::normalizedEmailOrNull($identifier) === null
            && ! LoginIdentifier::isValidSaudiMobileInput($identifier)) {
            throw ValidationException::withMessages([
                'email' => [LoginMessages::invalidIdentifier()],
            ]);
        }

        $user = $this->resolveUser($identifier);

        if ($user === null) {
            Log::info('password reset: no matching user (generic success returned)');

            return ['sent' => false, 'response' => Password::RESET_LINK_SENT];
        }

        if (! $user->email || trim((string) $user->email) === '') {
            throw ValidationException::withMessages([
                'email' => [__('validation.password_reset.no_email_on_account')],
            ]);
        }

        if ((int) $user->status !== User::STATUS_CAN_LOGIN) {
            Log::info('password reset: inactive user', ['user_id' => $user->id]);

            return ['sent' => false, 'response' => Password::RESET_LINK_SENT];
        }

        try {
            $response = Password::sendResetLink(['email' => $user->email]);
        } catch (\Throwable $e) {
            Log::error('password reset: mail send failed', [
                'user_id' => $user->id,
                'message' => $e->getMessage(),
                'exception' => $e,
            ]);

            throw ValidationException::withMessages([
                'email' => [__('validation.password_reset.mail_failed')],
            ]);
        }

        if ($response !== Password::RESET_LINK_SENT) {
            Log::warning('password reset broker response', [
                'user_id' => $user->id,
                'response' => $response,
            ]);
        }

        if ($response === Password::RESET_THROTTLED) {
            throw ValidationException::withMessages([
                'email' => [__('passwords.throttled')],
            ]);
        }

        return [
            'sent' => $response === Password::RESET_LINK_SENT,
            'response' => $response,
        ];
    }

    public function resolveUser(string $identifier): ?User
    {
        if ($email = LoginIdentifier::normalizedEmailOrNull($identifier)) {
            return User::query()
                ->whereRaw('LOWER(email) = ?', [strtolower($email)])
                ->first();
        }

        if (LoginIdentifier::isPlausiblePhoneInput($identifier)) {
            $storedPhone = LoginIdentifier::resolveStoredPhone($identifier);
            if ($storedPhone === null) {
                return null;
            }

            return User::query()->where('phone', $storedPhone)->first();
        }

        return null;
    }
}
