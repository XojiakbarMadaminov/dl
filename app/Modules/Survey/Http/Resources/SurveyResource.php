<?php

namespace App\Modules\Survey\Http\Resources;

use App\Modules\Survey\Models\Survey;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Survey
 */
class SurveyResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'organization_id' => $this->organization_id,
            'created_by' => $this->created_by,
            'status' => $this->status->value,
            'default_locale' => $this->default_locale,
            'locales' => $this->locales,
            'title' => $this->title,
            'description' => $this->description,
            'welcome_message' => $this->welcome_message,
            'thank_you_message' => $this->thank_you_message,
            'internal_note' => $this->internal_note,
            'published_at' => $this->published_at?->toIso8601String(),
            'closed_at' => $this->closed_at?->toIso8601String(),
            'archived_at' => $this->archived_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
            'pages' => SurveyPageResource::collection($this->whenLoaded('pages')),
        ];
    }
}
