<?php

namespace Tests\Unit;

use App\Services\Auth\AdminLoginService;
use App\Support\LoginMessages;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * Manual scenarios (require local DB + POST /api/auth/login):
 * - Valid email + correct password → 200 with token
 * - Valid email, unknown address → errors.email = EMAIL_NOT_REGISTERED
 * - Valid email, wrong password → errors.password = WRONG_PASSWORD
 * - Saudi phone (05xxxxxxxx / +966…) registered → 200
 * - Plausible phone not in DB → errors.email = PHONE_NOT_REGISTERED
 * - User with status=0 → errors.email = ACCOUNT_CANNOT_LOGIN
 */
class AdminLoginServiceValidationTest extends TestCase
{
    private AdminLoginService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(AdminLoginService::class);
    }

    public function test_empty_identifier_returns_required_message(): void
    {
        try {
            $this->service->login('', 'secret');
            $this->fail('Expected ValidationException');
        } catch (ValidationException $e) {
            $this->assertSame(
                [LoginMessages::IDENTIFIER_REQUIRED],
                $e->errors()['email']
            );
        }
    }

    public function test_empty_password_returns_required_message(): void
    {
        try {
            $this->service->login('user@example.com', '');
            $this->fail('Expected ValidationException');
        } catch (ValidationException $e) {
            $this->assertSame(
                [LoginMessages::PASSWORD_REQUIRED],
                $e->errors()['password']
            );
        }
    }

    public function test_invalid_identifier_format_returns_message_on_email_field(): void
    {
        try {
            $this->service->login('not-a-valid-login', 'secret');
            $this->fail('Expected ValidationException');
        } catch (ValidationException $e) {
            $this->assertSame(
                [LoginMessages::INVALID_IDENTIFIER],
                $e->errors()['email']
            );
        }
    }
}
