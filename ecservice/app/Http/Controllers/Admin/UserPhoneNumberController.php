<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Users\PhoneNumberRequest;
use App\Models\User;
use App\Support\ParentPhoneNumberException;
use App\Support\ParentPhoneNumberService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Admin management of a parent-only account's phone numbers. Staff/admin accounts are rejected.
 */
class UserPhoneNumberController extends Controller
{
    public function index(Request $request, User $user): JsonResponse
    {
        if (! canAny(['show_parents', 'edit_parents', 'admin_parents'])) {
            return $this->denied();
        }

        return $this->guarded($user, fn () => $this->listResponse($user));
    }

    public function store(PhoneNumberRequest $request, User $user): JsonResponse
    {
        return $this->guarded($user, function () use ($request, $user) {
            ParentPhoneNumberService::add(
                $user,
                (string) $request->validated('phone'),
                $request->validated('label'),
                (bool) $request->validated('verified', false)
            );

            return $this->listResponse($user, __('validation.parent_phone_numbers.saved'));
        });
    }

    public function update(PhoneNumberRequest $request, User $user, int $phoneNumber): JsonResponse
    {
        return $this->guarded($user, function () use ($request, $user, $phoneNumber) {
            ParentPhoneNumberService::update($user, $phoneNumber, $request->validated('phone'), $request->validated('label'));

            return $this->listResponse($user, __('validation.parent_phone_numbers.saved'));
        });
    }

    public function destroy(PhoneNumberRequest $request, User $user, int $phoneNumber): JsonResponse
    {
        return $this->guarded($user, function () use ($user, $phoneNumber) {
            ParentPhoneNumberService::remove($user, $phoneNumber);

            return $this->listResponse($user, __('validation.parent_phone_numbers.removed'));
        });
    }

    public function primary(PhoneNumberRequest $request, User $user, int $phoneNumber): JsonResponse
    {
        return $this->guarded($user, function () use ($request, $user, $phoneNumber) {
            ParentPhoneNumberService::setPrimary($user, $phoneNumber, (bool) $request->validated('mark_verified', false));

            return $this->listResponse($user, __('validation.parent_phone_numbers.primary_changed'));
        });
    }

    public function verify(PhoneNumberRequest $request, User $user, int $phoneNumber): JsonResponse
    {
        return $this->guarded($user, function () use ($request, $user, $phoneNumber) {
            ParentPhoneNumberService::setVerified($user, $phoneNumber, (bool) $request->validated('verified'));

            return $this->listResponse($user, __('validation.parent_phone_numbers.saved'));
        });
    }

    /**
     * Center isolation mirrors UserController: the actor must share a center with the parent (or be admin).
     */
    private function guarded(User $user, callable $action): JsonResponse
    {
        $actor = auth()->user();
        $actor->loadMissing(['centers:id', 'roles']);
        $sharesCenter = $user->centers()->whereIn('centers.id', $actor->centers->pluck('id'))->exists();
        if (! $sharesCenter && ! isHasRole('admin', $actor)) {
            return response()->json(['errors' => ['error' => [__("validation.You don't belong to this center")]]], 422);
        }

        try {
            ParentPhoneNumberService::assertManageable($user);

            return $action();
        } catch (ParentPhoneNumberException $e) {
            return response()->json([
                'errors' => ['phone' => [$e->localizedMessage()]],
                'reason' => $e->reason,
            ], 422);
        } catch (ModelNotFoundException) {
            return response()->json(['errors' => ['phone' => [__('validation.parent_phone_numbers.not_found')]]], 404);
        }
    }

    private function listResponse(User $user, ?string $message = null): JsonResponse
    {
        $fresh = User::query()->findOrFail($user->id);

        return apiResponse([
            'user_id' => (int) $fresh->id,
            'account_phone' => $fresh->phone,
            'phone_numbers' => ParentPhoneNumberService::displayList($fresh),
        ], $message);
    }

    private function denied(): JsonResponse
    {
        return response()->json(['errors' => ['error' => [__("validation.You don't have permission to access this page.")]]], 422);
    }
}
