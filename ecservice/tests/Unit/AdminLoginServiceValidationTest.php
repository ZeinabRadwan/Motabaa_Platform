<?php

namespace Tests\Unit;

use App\Services\Auth\AdminLoginService;
use App\Support\LoginMessages;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * Manual scenarios (require local DB + POST /api/auth/login):
 * - Valid email + correct password → 200 with token
 * - Valid email, unknown address → errors.email = identifier not registered (AR/EN via Accept-Language)
 * - Valid email, wrong password → errors.password = wrong password
 * - Saudi phone (05xxxxxxxx / 9665… / +9665…) registered → 200
 * - Valid Saudi mobile not in DB → errors.email = identifier not registered
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
                [LoginMessages::identifierRequired()],
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
                [LoginMessages::passwordRequired()],
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
                [LoginMessages::invalidIdentifier()],
                $e->errors()['email']
            );
        }
    }
}
