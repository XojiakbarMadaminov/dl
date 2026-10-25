<?php

namespace App\Modules\Organization\Http\Resources;

use App\Models\User;
use App\Modules\Organization\Models\Organization;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Organization
 */
class OrganizationResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var User|null $user */
        $user = $request->user();

        return [
            'id' => $this->id,
            'name' => $this->name,
            'inn' => $this->inn,
            'status' => $this->status->value,
            'created_by' => $this->created_by,
            'current_user_role' => $user ? $this->roleOf($user)?->value : null,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
