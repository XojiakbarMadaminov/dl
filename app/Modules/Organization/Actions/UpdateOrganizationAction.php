<?php

namespace App\Modules\Organization\Actions;

use App\Modules\Organization\Models\Organization;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class UpdateOrganizationAction
{
    /**
     * @param  array{name: string, inn?: string}  $data
     */
    public function execute(Organization $organization, array $data): Organization
    {
        return DB::transaction(function () use ($organization, $data) {
            $data['name'] = trim($data['name']);

            if (isset($data['inn'])) {
                $data['inn'] = trim($data['inn']);
            }

            try {
                $organization->update($data);
            } catch (UniqueConstraintViolationException) {
                throw ValidationException::withMessages(['inn' => 'The inn has already been taken.']);
            }

            return $organization;
        });
    }
}
