<?php

namespace Modules\Core\Http\Controllers\Api;

use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Modules\Core\Http\Resources\UserResource;

class AuthController
{
    /**
     * Issue a personal access token for the mobile app.
     */
    public function token(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
            'device_name' => ['required', 'string', 'max:255'],
        ]);

        $throttleKey = Str::lower($data['email']).'|'.$request->ip();

        if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
            throw ValidationException::withMessages([
                'email' => __('core::auth.throttle', ['seconds' => RateLimiter::availableIn($throttleKey)]),
            ]);
        }

        $user = User::where('email', $data['email'])->first();

        if (! $user || ! $user->is_active || ! Hash::check($data['password'], $user->password)) {
            RateLimiter::hit($throttleKey);

            throw ValidationException::withMessages(['email' => __('core::auth.failed')]);
        }

        RateLimiter::clear($throttleKey);

        return response()->json([
            'token' => $user->createToken($data['device_name'])->plainTextToken,
            'user' => new UserResource($user),
        ], Response::HTTP_CREATED);
    }

    public function me(Request $request): UserResource
    {
        return new UserResource($request->user());
    }

    /**
     * Revoke the token used for this request.
     */
    public function logout(Request $request): Response
    {
        $request->user()->currentAccessToken()->delete();

        return response()->noContent();
    }
}
