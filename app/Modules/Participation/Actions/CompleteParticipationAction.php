<?php

namespace App\Modules\Participation\Actions;

use App\Modules\Participation\Enums\ParticipationStatus;
use App\Modules\Participation\Models\Participation;

class CompleteParticipationAction
{
    /**
     * @return array<string, mixed>
     */
    public function execute(Participation $participation): array
    {
        // Idempotent: if already completed, return gracefully
        if ($participation->status->isCompleted()) {
            return [
                'completed' => true,
                'status' => 'completed',
                'thank_you_message' => $participation->survey->thank_you_message[$participation->locale] ?? null,
            ];
        }

        $participation->update([
            'status' => ParticipationStatus::COMPLETED,
            'current_page_id' => null,
            'completed_at' => now(),
            'last_activity_at' => now(),
        ]);

        return [
            'completed' => true,
            'status' => 'completed',
            'thank_you_message' => $participation->survey->thank_you_message[$participation->locale] ?? null,
        ];
    }
}
