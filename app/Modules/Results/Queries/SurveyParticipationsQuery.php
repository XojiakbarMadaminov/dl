<?php

namespace App\Modules\Results\Queries;

use App\Modules\Participation\Models\Participation;
use App\Modules\Survey\Models\Survey;
use Illuminate\Database\Eloquent\Builder;

class SurveyParticipationsQuery
{
    /**
     * @param  array<string, mixed>  $filters
     * @return Builder<Participation>
     */
    public function build(Survey $survey, array $filters = []): Builder
    {
        return Participation::query()->where('survey_id', $survey->id)
            ->when(isset($filters['status']), fn (Builder $query) => $query->where('status', $filters['status']))
            ->when(isset($filters['participation_locale']), fn (Builder $query) => $query->where('locale', $filters['participation_locale']));
    }
}
