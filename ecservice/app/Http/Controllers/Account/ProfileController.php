<?php

namespace App\Http\Controllers\Account;

use App\Http\Controllers\Controller;
use App\Http\Requests\Account\UpdatePasswordRequest;
use App\Http\Requests\Account\UpdateProfileRequest;
use App\Http\Resources\Admin\User\UserResource;
use App\Models\User;

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

        $user->update($input);

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
