<?php

namespace App\Modules\Survey\Actions;

use App\Exceptions\ApiException;
use App\Modules\Survey\Models\SurveyPage;
use App\Modules\Survey\Models\SurveyQuestion;
use Illuminate\Support\Facades\DB;

class ReorderQuestionsAction
{
    /**
     * @param  array<int, int>  $orderedQuestionIds
     */
    public function execute(SurveyPage $page, array $orderedQuestionIds): void
    {
        $page->survey->assertEditable();

        $existingIds = $page->questions()->pluck('id')->all();

        if (count($orderedQuestionIds) !== count($existingIds) || array_diff($orderedQuestionIds, $existingIds)) {
            throw new ApiException(
                message: 'All existing question IDs on this page must be provided for reordering.',
                errorCode: 'INVALID_REORDER_PAYLOAD',
                statusCode: 422,
            );
        }

        DB::transaction(function () use ($orderedQuestionIds) {
            foreach ($orderedQuestionIds as $index => $questionId) {
                SurveyQuestion::where('id', $questionId)->update(['position' => -($index + 1)]);
            }

            foreach ($orderedQuestionIds as $index => $questionId) {
                SurveyQuestion::where('id', $questionId)->update(['position' => $index + 1]);
            }
        });
    }
}
