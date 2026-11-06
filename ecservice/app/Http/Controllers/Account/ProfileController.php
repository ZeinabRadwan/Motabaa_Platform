<?php

namespace App\Http\Controllers\Account;

use App\Http\Controllers\Controller;
use App\Http\Requests\Account\UpdatePasswordRequest;
use App\Http\Requests\Account\UpdateProfileRequest;
use App\Http\Resources\Admin\User\UserResource;
use App\Models\User;
use App\Support\ParentPhoneNumberService;

class ProfileController extends Controller
{
    public function show()
    {
        $user = auth()->user();
        $user->loadMissing(['roles', 'centers']);

        return apiResponse(UserResource::forSession($user));
    }

    public function update(UpdateProfileRequest $request)
    {
        /** @var User $user */
        $user = auth()->user();
        $input = $request->validated();

        $previousPhone = $user->phone;
        $isParentAccount = isParentUser($user);
        if ($isParentAccount && ($conflict = ParentPhoneNumberService::accountPhoneConflict($user, $input['phone'] ?? null))) {
            return response()->json(['errors' => ['phone' => [__('validation.parent_phone_numbers.'.$conflict)]]], 422);
        }

        $user->update($input);

        if ($isParentAccount && $previousPhone !== $user->phone) {
            ParentPhoneNumberService::syncAfterAccountPhoneChange($user, $previousPhone);
        }

        if ($request->hasFile('image')) {
            $currentImage = $request->current_image ?: null;
            $user->setImage($input['image'], $currentImage);
        }

        $user->refresh();
        $user->loadMissing(['roles.permissions', 'permissions', 'centers']);

        return apiResponse(UserResource::forSession($user));
    }

    public function updatePassword(UpdatePasswordRequest $request)
    {
        /** @var User $user */
        $user = auth()->user();
        $user->update([
            'password' => bcrypt($request->validated('password')),
        ]);

        return response()->json(['message' => '', 'status' => true], 200);
    }
}
