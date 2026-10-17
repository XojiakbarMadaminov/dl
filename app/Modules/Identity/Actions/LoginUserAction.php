<?php

namespace App\Modules\Identity\Actions;

use App\Exceptions\ApiException;
use App\Models\User;
use App\Modules\Identity\Enums\UserStatus;
use Illuminate\Support\Facades\Hash;

class LoginUserAction
{
    /**
     * @param  array{email: string, password: string, device_name?: string}  $credentials
     * @return array{user: User, token: string}
     */
    public function execute(array $credentials): array
    {
        $email = strtolower(trim($credentials['email']));
        $user = User::where('email', $email)->first();

        if (! $user || ! Hash::check($credentials['password'], $user->password)) {
            throw new ApiException(
                message: 'Invalid email or password.',
                errorCode: 'INVALID_CREDENTIALS',
                statusCode: 401,
            );
        }

        if ($user->status !== UserStatus::ACTIVE) {
            throw new ApiException(
                message: 'This user account is not active.',
                errorCode: 'ACCOUNT_INACTIVE',
                statusCode: 403,
            );
        }

        $deviceName = $credentials['device_name'] ?? 'web';
        $token = $user->createToken($deviceName)->plainTextToken;

        return [
            'user' => $user,
            'token' => $token,
        ];
    }
}
