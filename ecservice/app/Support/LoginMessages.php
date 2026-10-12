<?php

namespace App\Support;

/**
 * Arabic login validation messages (admin / ecsystem).
 */
final class LoginMessages
{
    public const IDENTIFIER_REQUIRED = 'يرجى إدخال البريد الإلكتروني أو رقم الهاتف.';

    public const PASSWORD_REQUIRED = 'يرجى إدخال كلمة المرور.';

    public const INVALID_IDENTIFIER = 'يرجى إدخال بريد إلكتروني أو رقم هاتف صحيح.';

    public const EMAIL_NOT_REGISTERED = 'البريد الإلكتروني غير مسجل لدينا.';

    public const PHONE_NOT_REGISTERED = 'رقم الهاتف غير مسجل لدينا.';

    public const WRONG_PASSWORD = 'كلمة المرور غير صحيحة.';

    /** Matches ecsystem ar.json for validation.This user cannot log in. */
    public const ACCOUNT_CANNOT_LOGIN = 'لا يمكن لهذا المستخدم تسجيل الدخول.';
}
