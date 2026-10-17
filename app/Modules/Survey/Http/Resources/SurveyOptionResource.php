<?php

namespace App\Modules\Survey\Http\Resources;

use App\Modules\Survey\Models\SurveyQuestionOption;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin SurveyQuestionOption
 */
class SurveyOptionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'question_id' => $this->question_id,
            'label' => $this->label,
            'position' => $this->position,
        ];
    }
}
