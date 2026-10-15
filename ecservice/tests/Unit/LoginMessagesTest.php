<?php

namespace Tests\Unit;

use App\Support\LoginMessages;
use Tests\TestCase;

class LoginMessagesTest extends TestCase
{
    public function test_arabic_login_messages(): void
    {
        app()->setLocale('ar');

        $this->assertSame('يرجى إدخال البريد الإلكتروني أو رقم الهاتف.', LoginMessages::identifierRequired());
        $this->assertSame('يرجى إدخال كلمة المرور.', LoginMessages::passwordRequired());
        $this->assertSame('البريد الإلكتروني أو رقم الجوال غير مسجل', LoginMessages::identifierNotRegistered());
        $this->assertSame('كلمة المرور غير صحيحة', LoginMessages::wrongPassword());
    }

    public function test_english_login_messages(): void
    {
        app()->setLocale('en');

        $this->assertSame('Please enter your email or phone number.', LoginMessages::identifierRequired());
        $this->assertSame('Incorrect password.', LoginMessages::wrongPassword());
        $this->assertSame('This email or mobile number is not registered.', LoginMessages::identifierNotRegistered());
    }
}
