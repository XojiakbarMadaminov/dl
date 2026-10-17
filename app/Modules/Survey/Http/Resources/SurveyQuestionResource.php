<?php

namespace App\Modules\Survey\Http\Resources;

use App\Modules\Survey\Models\SurveyQuestion;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin SurveyQuestion
 */
class SurveyQuestionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'page_id' => $this->page_id,
            'type' => $this->type->value,
            'title' => $this->title,
            'description' => $this->description,
            'is_required' => $this->is_required,
            'position' => $this->position,
            'settings' => $this->settings,
            'options' => SurveyOptionResource::collection($this->whenLoaded('options')),
        ];
    }
}
