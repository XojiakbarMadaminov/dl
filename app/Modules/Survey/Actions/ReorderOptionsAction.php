<?php

namespace App\Modules\Survey\Actions;

use App\Exceptions\ApiException;
use App\Modules\Survey\Models\SurveyQuestion;
use App\Modules\Survey\Models\SurveyQuestionOption;
use Illuminate\Support\Facades\DB;

class ReorderOptionsAction
{
    /**
     * @param  array<int, int>  $orderedOptionIds
     */
    public function execute(SurveyQuestion $question, array $orderedOptionIds): void
    {
        $question->page->survey->assertEditable();

        $existingIds = $question->options()->pluck('id')->all();

        if (count($orderedOptionIds) !== count($existingIds) || array_diff($orderedOptionIds, $existingIds)) {
            throw new ApiException(
                message: 'All existing option IDs for this question must be provided for reordering.',
                errorCode: 'INVALID_REORDER_PAYLOAD',
                statusCode: 422,
            );
        }

        DB::transaction(function () use ($orderedOptionIds) {
            foreach ($orderedOptionIds as $index => $optionId) {
                SurveyQuestionOption::where('id', $optionId)->update(['position' => -($index + 1)]);
            }

            foreach ($orderedOptionIds as $index => $optionId) {
                SurveyQuestionOption::where('id', $optionId)->update(['position' => $index + 1]);
            }
        });
    }
}
