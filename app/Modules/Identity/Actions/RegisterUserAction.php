<?php

namespace App\Modules\Identity\Actions;

use App\Models\User;
use App\Modules\Identity\Enums\PlatformLocale;
use App\Modules\Identity\Enums\PlatformRole;
use App\Modules\Identity\Enums\UserStatus;
use Illuminate\Support\Facades\Hash;

class RegisterUserAction
{
    /**
     * @param  array{name: string, email: string, password: string, locale?: string|PlatformLocale}  $data
     */
    public function execute(array $data): User
    {
        $locale = isset($data['locale'])
            ? ($data['locale'] instanceof PlatformLocale ? $data['locale'] : PlatformLocale::tryFrom($data['locale']) ?? PlatformLocale::UZ)
            : PlatformLocale::UZ;

        return User::create([
            'name' => $data['name'],
            'email' => strtolower(trim($data['email'])),
            'password' => Hash::make($data['password']),
            'locale' => $locale,
            'platform_role' => PlatformRole::USER,
            'status' => UserStatus::ACTIVE,
        ]);
    }
}
