<?php

namespace App\Modules\Survey\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ReplaceSurveyLogicRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'rules' => ['present', 'array', 'list', 'max:100'],
            'rules.*' => ['required', 'array:action,source_page_id,target_page_id,target_question_id,priority,match,conditions'],
            'rules.*.action' => ['required', Rule::in(['show_question', 'skip_to_page', 'complete'])],
            'rules.*.source_page_id' => ['nullable', 'integer', 'min:1'],
            'rules.*.target_page_id' => ['nullable', 'integer', 'min:1'],
            'rules.*.target_question_id' => ['nullable', 'integer', 'min:1'],
            'rules.*.priority' => ['required', 'integer', 'min:0', 'max:1000000'],
            'rules.*.match' => ['required', Rule::in(['all', 'any'])],
            'rules.*.conditions' => ['required', 'array', 'list', 'min:1', 'max:20'],
            'rules.*.conditions.*' => ['required', 'array:question_id,operator,value'],
            'rules.*.conditions.*.question_id' => ['required', 'integer', 'min:1'],
            'rules.*.conditions.*.operator' => ['required', Rule::in(['equals', 'not_equals', 'contains', 'not_contains', 'greater_than', 'less_than', 'answered', 'not_answered'])],
            'rules.*.conditions.*.value' => ['nullable'],
        ];
    }

    public function bodyParameters(): array
    {
        return [
            'rules.*' => ['description' => 'One conditional rule. Maximum 100 rules per survey.', 'example' => [
                'action' => 'show_question', 'target_question_id' => 202, 'priority' => 0, 'match' => 'all',
                'conditions' => [['question_id' => 101, 'operator' => 'equals', 'value' => 501]],
            ]],
            'rules.*.conditions.*' => ['description' => 'One condition. Maximum 20 per group.', 'example' => ['question_id' => 101, 'operator' => 'equals', 'value' => 501]],
            'rules' => ['description' => 'Complete replacement rule list; an empty list clears all rules. See docs/API-SURVEY-LOGIC.md.', 'example' => [
                ['action' => 'show_question', 'target_question_id' => 202, 'priority' => 0, 'match' => 'all', 'conditions' => [
                    ['question_id' => 101, 'operator' => 'equals', 'value' => 501],
                ]],
            ]],
            'rules.*.action' => ['description' => 'show_question, skip_to_page, or complete.', 'example' => 'show_question'],
            'rules.*.source_page_id' => ['description' => 'Required only for navigation rules.', 'example' => null],
            'rules.*.target_page_id' => ['description' => 'Required only for skip_to_page; must be a later page.', 'example' => null],
            'rules.*.target_question_id' => ['description' => 'Required only for show_question.', 'example' => 202],
            'rules.*.priority' => ['description' => 'Smaller values win; ties use input order.', 'example' => 0],
            'rules.*.match' => ['description' => 'all for AND, any for OR.', 'example' => 'all'],
            'rules.*.conditions.*.question_id' => ['description' => 'Earlier-page question for visibility, earlier/current-page question for navigation.', 'example' => 101],
            'rules.*.conditions.*.operator' => ['description' => 'Question-type-specific operator; see API-SURVEY-LOGIC.md.', 'example' => 'equals'],
            'rules.*.conditions.*.value' => ['description' => 'Integer option ID, integer rating, or text. Omit for answered/not_answered.', 'example' => 501],
        ];
    }
}
