<?php

namespace App\Modules\Survey\Actions;

use App\Exceptions\ApiException;
use App\Modules\Survey\Models\Survey;
use App\Modules\Survey\Models\SurveyPage;
use Illuminate\Support\Facades\DB;

class ReorderPagesAction
{
    /**
     * @param  array<int, int>  $orderedPageIds
     */
    public function execute(Survey $survey, array $orderedPageIds): void
    {
        $survey->assertEditable();

        $existingIds = $survey->pages()->pluck('id')->all();

        if (! array_is_list($orderedPageIds) || count(array_unique($orderedPageIds)) !== count($orderedPageIds) || count($orderedPageIds) !== count($existingIds) || array_diff($orderedPageIds, $existingIds)) {
            throw new ApiException(
                message: 'All existing page IDs must be provided for reordering.',
                errorCode: 'INVALID_REORDER_PAYLOAD',
                statusCode: 422,
            );
        }

        DB::transaction(function () use ($orderedPageIds) {
            // First move to negative offset to avoid any unique collision
            foreach ($orderedPageIds as $index => $pageId) {
                SurveyPage::where('id', $pageId)->update(['position' => -($index + 1)]);
            }

            // Then set final 1-based positions
            foreach ($orderedPageIds as $index => $pageId) {
                SurveyPage::where('id', $pageId)->update(['position' => $index + 1]);
            }
        });
    }
}
