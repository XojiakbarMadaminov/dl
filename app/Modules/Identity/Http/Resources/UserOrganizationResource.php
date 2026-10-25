<?php

namespace App\Modules\Identity\Http\Resources;

use App\Modules\Organization\Models\OrganizationMember;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin OrganizationMember
 */
class UserOrganizationResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->organization_id,
            'name' => $this->organization->name,
            'inn' => $this->organization->inn,
            'current_user_role' => $this->role->value,
        ];
    }
}
