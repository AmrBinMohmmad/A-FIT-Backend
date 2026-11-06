<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Base for every request behind auth:sanctum: authorization is "a user is signed in"
 * (never a bare `return true`, Rakaya D10.4); resource-level checks live in Policies
 * and are invoked by the controller.
 */
abstract class AuthenticatedRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() instanceof User;
    }

    /** The signed-in user, typed. */
    public function actor(): User
    {
        /** @var User $user */
        $user = $this->user();

        return $user;
    }
}
