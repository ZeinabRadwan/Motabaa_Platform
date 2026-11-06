<?php

namespace Tests\Unit;

use App\Http\Resources\Admin\User\UserResource;
use App\Models\Center;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Mockery;
use Tests\TestCase;

class UserImpersonationSessionResourceTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    private function sessionUserWithCenters(array $centers): User
    {
        /** @var User|\Mockery\MockInterface $user */
        $user = Mockery::mock(User::class)->makePartial();
        $user->id = 10;
        $user->name = 'Staff User';
        $user->email = 'staff@example.com';
        $user->phone = '966500000001';
        $user->status = User::STATUS_CAN_LOGIN;
        $user->password = 'hashed';
        $user->gender = 0;
        $user->setRelation('roles', collect([]));
        $user->setRelation('centers', collect($centers));
        $user->setRelation('permissions', collect([]));
        $user->shouldReceive('loadMissing')->andReturnSelf();
        $user->shouldReceive('getAllPermissions')->andReturn(collect());
        $user->shouldReceive('urlImage')->andReturn(null);
        $user->shouldReceive('annualLeaveEntitlement')->andReturn(21);

        return $user;
    }

    private function resolveCenters(User $user): array
    {
        $payload = UserResource::forSession($user)->resolve(Request::create('/'));

        return collect($payload['centers'])->values()->all();
    }

    public function test_session_centers_include_route_gate_fields_without_heavy_billing_fields(): void
    {
        Schema::shouldReceive('hasColumn')->andReturn(false);

        $center = new Center(['title' => 'Center A', 'status' => 1, 'package_id' => 3]);
        $center->id = 1;
        $center->paid_service = 0;

        $centers = $this->resolveCenters($this->sessionUserWithCenters([$center]));

        $this->assertCount(1, $centers);
        $this->assertSame(1, $centers[0]['id']);
        $this->assertSame(1, $centers[0]['status']);
        $this->assertSame(3, $centers[0]['package_id']);
        $this->assertTrue($centers[0]['is_paid']);
        $this->assertArrayNotHasKey('can_pay', $centers[0]);
        $this->assertArrayNotHasKey('next_subscription_date', $centers[0]);
    }

    public function test_unpaid_center_is_reported_as_unpaid(): void
    {
        Schema::shouldReceive('hasColumn')->andReturn(false);

        $center = Mockery::mock(Center::class)->makePartial();
        $center->id = 2;
        $center->status = 1;
        $center->package_id = 2;
        $center->shouldReceive('isPaid')->andReturn(false);

        $centers = $this->resolveCenters($this->sessionUserWithCenters([$center]));

        $this->assertFalse($centers[0]['is_paid']);
    }

    public function test_broken_billing_data_does_not_break_session(): void
    {
        Schema::shouldReceive('hasColumn')->andReturn(false);

        $center = Mockery::mock(Center::class)->makePartial();
        $center->id = 3;
        $center->status = 1;
        $center->package_id = 1;
        $center->shouldReceive('isPaid')->andThrow(new \RuntimeException('bad expiry_date'));

        $centers = $this->resolveCenters($this->sessionUserWithCenters([$center]));

        $this->assertTrue($centers[0]['is_paid']);
    }
}
