<?php

namespace App\Modules\Identity\Actions;

use App\Models\User;
use App\Modules\Identity\Enums\PlatformLocale;
use App\Modules\Identity\Enums\PlatformRole;
use App\Modules\Identity\Enums\UserStatus;
use App\Modules\Organization\Actions\CreateOrganizationAction;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class RegisterUserAction
{
    public function __construct(
        protected ?CreateOrganizationAction $createOrgAction = null,
    ) {
        $this->createOrgAction = $createOrgAction ?? new CreateOrganizationAction;
    }

    /**
     * @param  array{name: string, email: string, password: string, locale?: string|PlatformLocale, organization_name?: string|null}  $data
     */
    public function execute(array $data): User
    {
        $locale = isset($data['locale'])
            ? ($data['locale'] instanceof PlatformLocale ? $data['locale'] : PlatformLocale::tryFrom($data['locale']) ?? PlatformLocale::UZ)
            : PlatformLocale::UZ;

        return DB::transaction(function () use ($data, $locale) {
            $user = User::create([
                'name' => $data['name'],
                'email' => strtolower(trim($data['email'])),
                'password' => Hash::make($data['password']),
                'locale' => $locale,
                'platform_role' => PlatformRole::USER,
                'status' => UserStatus::ACTIVE,
            ]);

            if (! empty($data['organization_name'])) {
                $this->createOrgAction->execute(
                    name: $data['organization_name'],
                    creator: $user,
                );
            }

            return $user;
        });
    }
}
