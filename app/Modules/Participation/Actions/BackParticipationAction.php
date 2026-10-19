<?php

namespace App\Modules\Participation\Actions;

use App\Modules\Participation\Models\Participation;
use App\Modules\Participation\Services\SurveyFlow;
use Illuminate\Support\Facades\DB;

class BackParticipationAction
{
    public function __construct(private SurveyFlow $flow) {}

    public function execute(Participation $participation): Participation
    {
        return DB::transaction(function () use ($participation) {
            $participation = Participation::query()->lockForUpdate()->findOrFail($participation->id);
            $participation->assertCanAcceptAnswers();
            $path = $this->flow->path($participation->survey, $this->flow->answers($participation));
            $history = $participation->page_history ?: array_slice($path['page_ids'], 0, (int) array_search($participation->current_page_id, $path['page_ids'], true) + 1);
            if (count($history) > 1) {
                array_pop($history);
            }
            $participation->update([
                'current_page_id' => end($history),
                'page_history' => $history,
                'last_activity_at' => now(),
            ]);

            return $participation;
        });
    }
}
