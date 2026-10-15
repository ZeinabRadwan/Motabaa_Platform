<?php

namespace App\Support;

/**
 * Localized login messages (see lang/{locale}/auth.php → login).
 */
final class LoginMessages
{
    public static function identifierRequired(): string
    {
        return __('auth.login.identifier_required');
    }

    public static function passwordRequired(): string
    {
        return __('auth.login.password_required');
    }

    public static function invalidIdentifier(): string
    {
        return __('auth.login.invalid_identifier');
    }

    public static function identifierNotRegistered(): string
    {
        return __('auth.login.identifier_not_registered');
    }

    public static function wrongPassword(): string
    {
        return __('auth.login.wrong_password');
    }

    public static function accountCannotLogin(): string
    {
        return __('auth.login.account_cannot_login');
    }

    public static function genericError(): string
    {
        return __('auth.login.generic_error');
    }
}
