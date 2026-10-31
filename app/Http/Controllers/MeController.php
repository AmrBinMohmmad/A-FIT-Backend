<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\DeleteAccount;
use App\Http\Requests\SignedInRequest;
use App\Http\Requests\UpdateMeRequest;
use App\Http\Resources\UserResource;
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

    public function destroy(SignedInRequest $request, DeleteAccount $deleteAccount): JsonResponse
    {
        $deleteAccount($request->actor());

        return $this->deleted(__('messages.account_deleted'));
    }
}
