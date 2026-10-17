<?php

namespace App\Modules\Identity\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Identity\Actions\LoginUserAction;
use App\Modules\Identity\Actions\RegisterUserAction;
use App\Modules\Identity\Http\Requests\LoginRequest;
use App\Modules\Identity\Http\Requests\RegisterRequest;
use App\Modules\Identity\Http\Resources\UserResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * @group Authentication
 *
 * Endpoints for user registration, authentication, session tokens, and current user identity.
 */
class AuthController extends Controller
{
    /**
     * Register User
     *
     * Create a new user account with email and password. Optionally supply `organization_name` to automatically provision a new business organization with the user as OWNER.
     *
     * @unauthenticated
     *
     * @response 201 {
     *   "user": {
     *     "id": 2,
     *     "name": "Dilshod Karimov",
     *     "email": "owner@dialog.uz",
     *     "locale": "uz",
     *     "platform_role": "user",
     *     "status": "active",
     *     "created_at": "2026-10-17T12:00:00.000000Z"
     *   },
     *   "token": "1|qXyZ...plainTextToken"
     * }
     * @response 422 {
     *   "error": {
     *     "code": "VALIDATION_ERROR",
     *     "message": "The email has already been taken.",
     *     "fields": {
     *       "email": ["The email has already been taken."]
     *     }
     *   }
     * }
     */
    public function register(RegisterRequest $request, RegisterUserAction $action): JsonResponse
    {
        $user = $action->execute($request->validated());
        $token = $user->createToken('web')->plainTextToken;

        return response()->json([
            'user' => new UserResource($user),
            'token' => $token,
        ], 201);
    }

    /**
     * Login User
     *
     * Authenticate with email and password to receive a Sanctum bearer token and user profile.
     *
     * @unauthenticated
     *
     * @response 200 {
     *   "user": {
     *     "id": 2,
     *     "name": "Dilshod Karimov",
     *     "email": "owner@dialog.uz",
     *     "locale": "uz",
     *     "platform_role": "user",
     *     "status": "active",
     *     "created_at": "2026-10-17T12:00:00.000000Z"
     *   },
     *   "token": "2|AbCd...plainTextToken"
     * }
     * @response 401 {
     *   "error": {
     *     "code": "INVALID_CREDENTIALS",
     *     "message": "Invalid email or password.",
     *     "fields": {}
     *   }
     * }
     * @response 403 {
     *   "error": {
     *     "code": "ACCOUNT_INACTIVE",
     *     "message": "Your account is not active.",
     *     "fields": {}
     *   }
     * }
     */
    public function login(LoginRequest $request, LoginUserAction $action): JsonResponse
    {
        $result = $action->execute($request->validated());

        return response()->json([
            'user' => new UserResource($result['user']),
            'token' => $result['token'],
        ]);
    }

    /**
     * Current User Profile
     *
     * Retrieve the currently authenticated user's profile and platform role.
     *
     * @authenticated
     *
     * @response 200 {
     *   "user": {
     *     "id": 2,
     *     "name": "Dilshod Karimov",
     *     "email": "owner@dialog.uz",
     *     "locale": "uz",
     *     "platform_role": "user",
     *     "status": "active",
     *     "created_at": "2026-10-17T12:00:00.000000Z"
     *   }
     * }
     * @response 401 {
     *   "error": {
     *     "code": "UNAUTHORIZED",
     *     "message": "Unauthenticated.",
     *     "fields": {}
     *   }
     * }
     */
    public function me(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        return response()->json([
            'user' => new UserResource($user),
        ]);
    }

    /**
     * Logout
     *
     * Revoke the current access token. For SPA cookie sessions, invalidates the web session.
     *
     * @authenticated
     *
     * @response 200 {
     *   "message": "Logged out successfully."
     * }
     */
    public function logout(Request $request): JsonResponse
    {
        $request->user()?->currentAccessToken()?->delete();

        return response()->json([
            'message' => 'Logged out successfully.',
        ]);
    }
}
