<?php

namespace App\Modules\Survey\Http\Resources;

use App\Modules\Survey\Models\SurveyLogicRule;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin SurveyLogicRule */
class SurveyLogicRuleResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'action' => $this->action,
            'source_page_id' => $this->source_page_id,
            'target_page_id' => $this->target_page_id,
            'target_question_id' => $this->target_question_id,
            'priority' => $this->priority,
            'match' => $this->match,
            'conditions' => $this->conditions,
        ];
    }
}
