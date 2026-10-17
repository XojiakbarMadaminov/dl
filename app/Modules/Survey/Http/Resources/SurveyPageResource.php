<?php

namespace App\Modules\Survey\Http\Resources;

use App\Modules\Survey\Models\SurveyPage;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin SurveyPage
 */
class SurveyPageResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'survey_id' => $this->survey_id,
            'position' => $this->position,
            'title' => $this->title,
            'description' => $this->description,
            'questions' => SurveyQuestionResource::collection($this->whenLoaded('questions')),
        ];
    }
}
