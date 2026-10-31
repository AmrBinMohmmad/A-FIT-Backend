<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\DeleteAccount;
use App\Http\Requests\SignedInRequest;
use App\Http\Requests\UpdateMeRequest;
use App\Http\Requests\UploadPhotoRequest;
use App\Http\Resources\UserResource;
use App\Services\Photos;
use Illuminate\Http\JsonResponse;

class MeController extends Controller
{
    public function show(SignedInRequest $request): JsonResponse
    {
        return $this->success(new UserResource($request->actor()->load('profile')));
    }

    public function update(UpdateMeRequest $request): JsonResponse
    {
        $user = $request->actor();
        $user->fill($request->validated())->save();

        return $this->success(new UserResource($user->load('profile')));
    }

    /** Multipart `photo`; replaces the previous avatar and removes its file. */
    public function updateAvatar(UploadPhotoRequest $request, Photos $photos): JsonResponse
    {
        $user = $request->actor();
        $path = $photos->replace($request->photo(), Photos::AVATARS_DIR, $user->avatar_path);
        $user->forceFill(['avatar_path' => $path])->save();

        return $this->success(new UserResource($user->load('profile')), __('messages.photo_saved'));
    }

    public function destroyAvatar(SignedInRequest $request, Photos $photos): JsonResponse
    {
        $user = $request->actor();
        $photos->remove($user->avatar_path);
        $user->forceFill(['avatar_path' => null])->save();

        return $this->success(new UserResource($user->load('profile')), __('messages.photo_removed'));
    }

    public function destroy(SignedInRequest $request, DeleteAccount $deleteAccount): JsonResponse
    {
        $deleteAccount($request->actor());

        return $this->deleted(__('messages.account_deleted'));
    }
}
