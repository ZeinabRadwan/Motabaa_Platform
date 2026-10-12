<?php

namespace Tests\Unit;

use App\Support\LoginMessages;
use Tests\TestCase;

class LoginMessagesTest extends TestCase
{
    public function test_arabic_login_messages_match_product_copy(): void
    {
        $this->assertSame(LoginMessages::IDENTIFIER_REQUIRED, 'يرجى إدخال البريد الإلكتروني أو رقم الهاتف.');
        $this->assertSame(LoginMessages::PASSWORD_REQUIRED, 'يرجى إدخال كلمة المرور.');
        $this->assertSame(LoginMessages::INVALID_IDENTIFIER, 'يرجى إدخال بريد إلكتروني أو رقم هاتف صحيح.');
        $this->assertSame(LoginMessages::EMAIL_NOT_REGISTERED, 'البريد الإلكتروني غير مسجل لدينا.');
        $this->assertSame(LoginMessages::PHONE_NOT_REGISTERED, 'رقم الهاتف غير مسجل لدينا.');
        $this->assertSame(LoginMessages::WRONG_PASSWORD, 'كلمة المرور غير صحيحة.');
        $this->assertSame(LoginMessages::ACCOUNT_CANNOT_LOGIN, 'لا يمكن لهذا المستخدم تسجيل الدخول.');
    }
}
