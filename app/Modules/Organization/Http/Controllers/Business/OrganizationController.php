<?php

namespace App\Modules\Organization\Http\Controllers\Business;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Organization\Actions\CreateOrganizationAction;
use App\Modules\Organization\Actions\UpdateOrganizationAction;
use App\Modules\Organization\Http\Requests\CreateOrganizationRequest;
use App\Modules\Organization\Http\Requests\UpdateOrganizationRequest;
use App\Modules\Organization\Http\Resources\OrganizationResource;
use App\Modules\Organization\Models\Organization;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Knuckles\Scribe\Attributes\ResponseField;

/**
 * @group Business
 *
 * @subgroup Organizations
 *
 * Manage business organizations, memberships, and role assignments.
 */
class OrganizationController extends Controller
{
    /**
     * List Organizations
     *
     * List all organizations that the authenticated user belongs to.
     *
     * @authenticated
     *
     * @response 200 {
     *   "data": [
     *     {
     *       "id": 1,
     *       "name": "Dialog Analytics Group",
     *       "inn": "123456789",
     *       "status": "active",
     *       "created_by": 2,
     *       "current_user_role": "owner",
     *       "created_at": "2026-10-17T12:00:00.000000Z"
     *     }
     *   ]
     * }
     */
    #[ResponseField('data.inn', type: 'string', description: 'Organization INN; null for legacy organizations until completed by an owner/manager.', nullable: true)]
    public function index(Request $request): AnonymousResourceCollection
    {
        /** @var User $user */
        $user = $request->user();

        $organizations = $user->organizations()->latest('organization_members.joined_at')->get();

        return OrganizationResource::collection($organizations);
    }

    /**
     * Create Organization
     *
     * Create a new organization with a name and unique INN after user registration. The authenticated user automatically becomes the OWNER.
     *
     * @authenticated
     *
     * @response 201 {
     *   "organization": {
     *     "id": 1,
     *     "name": "Dialog Analytics Group",
     *     "inn": "123456789",
     *     "status": "active",
     *     "created_by": 2,
     *     "current_user_role": "owner",
     *     "created_at": "2026-10-17T12:00:00.000000Z"
     *   }
     * }
     * @response 422 {
     *   "error": {
     *     "code": "VALIDATION_ERROR",
     *     "message": "The name field is required.",
     *     "fields": {
     *       "name": ["The name field is required."]
     *     }
     *   }
     * }
     */
    public function store(CreateOrganizationRequest $request, CreateOrganizationAction $action): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $organization = $action->execute(
            name: $request->validated('name'),
            creator: $user,
            inn: $request->validated('inn'),
        );

        return response()->json([
            'organization' => new OrganizationResource($organization),
        ], 201);
    }

    /**
     * Get Organization
     *
     * Retrieve details of a specific organization. Requires membership in the organization or platform admin role.
     *
     * @authenticated
     *
     * @urlParam organization int required The ID of the organization. Example: 1
     *
     * @response 200 {
     *   "organization": {
     *     "id": 1,
     *     "name": "Dialog Analytics Group",
     *     "inn": "123456789",
     *     "status": "active",
     *     "created_by": 2,
     *     "current_user_role": "owner",
     *     "created_at": "2026-10-17T12:00:00.000000Z"
     *   }
     * }
     * @response 403 {
     *   "error": {
     *     "code": "FORBIDDEN",
     *     "message": "This action is unauthorized.",
     *     "fields": {}
     *   }
     * }
     */
    #[ResponseField('organization.inn', type: 'string', description: 'Organization INN; null for legacy organizations until completed by an owner/manager.', nullable: true)]
    public function show(Request $request, Organization $organization): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $this->authorize('view', $organization);

        return response()->json([
            'organization' => new OrganizationResource($organization),
        ]);
    }

    /**
     * Update Organization
     *
     * Update organization details. Requires OWNER or MANAGER role in the organization.
     *
     * @authenticated
     *
     * @urlParam organization int required The ID of the organization. Example: 1
     *
     * @response 200 {
     *   "organization": {
     *     "id": 1,
     *     "name": "Dialog Analytics Worldwide",
     *     "inn": "123456789",
     *     "status": "active",
     *     "created_by": 2,
     *     "current_user_role": "owner",
     *     "created_at": "2026-10-17T12:00:00.000000Z"
     *   }
     * }
     * @response 403 {
     *   "error": {
     *     "code": "FORBIDDEN",
     *     "message": "This action is unauthorized.",
     *     "fields": {}
     *   }
     * }
     */
    #[ResponseField('organization.inn', type: 'string', description: 'Organization INN; null for legacy organizations until completed by an owner/manager.', nullable: true)]
    public function update(UpdateOrganizationRequest $request, Organization $organization, UpdateOrganizationAction $action): JsonResponse
    {
        $organization = $action->execute($organization, $request->validated());

        return response()->json([
            'organization' => new OrganizationResource($organization),
        ]);
    }
}
