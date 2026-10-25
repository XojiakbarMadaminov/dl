<?php

namespace Tests\Feature;

use App\Exceptions\ApiException;
use App\Models\User;
use App\Modules\Organization\Actions\CreateOrganizationAction;
use App\Modules\Participation\Actions\CompleteParticipationAction;
use App\Modules\Participation\Actions\SavePageAnswersAction;
use App\Modules\Participation\Actions\StartOrResumeParticipationAction;
use App\Modules\Participation\Enums\ParticipationStatus;
use App\Modules\Participation\Models\Participation;
use App\Modules\Survey\Enums\QuestionType;
use App\Modules\Survey\Enums\SurveyStatus;
use App\Modules\Survey\Models\Survey;
use App\Modules\Survey\Models\SurveyPage;
use App\Modules\Survey\Models\SurveyQuestion;
use App\Modules\Survey\Models\SurveyQuestionOption;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RespondentParticipationTest extends TestCase
{
    use RefreshDatabase;

    private User $businessUser;

    private User $respondentA;

    private User $respondentB;

    private Survey $activeSurvey;

    private SurveyPage $page1;

    private SurveyPage $page2;

    private SurveyQuestion $question1;

    private SurveyQuestion $question2;

    private SurveyQuestionOption $opt1;

    protected function setUp(): void
    {
        parent::setUp();

        $this->businessUser = User::factory()->create();
        $this->respondentA = User::factory()->create();
        $this->respondentB = User::factory()->create();

        $orgAction = new CreateOrganizationAction;
        $org = $orgAction->execute('Participation Org', $this->businessUser, '123456789');

        $this->activeSurvey = Survey::create([
            'organization_id' => $org->id,
            'created_by' => $this->businessUser->id,
            'status' => SurveyStatus::ACTIVE,
            'default_locale' => 'uz',
            'locales' => ['uz', 'ru'],
            'title' => ['uz' => 'Faol so‘rovnoma', 'ru' => 'Активный опрос'],
            'published_at' => now(),
        ]);

        $this->page1 = SurveyPage::create([
            'survey_id' => $this->activeSurvey->id,
            'position' => 1,
            'title' => ['uz' => '1-sahifa'],
        ]);

        $this->question1 = SurveyQuestion::create([
            'page_id' => $this->page1->id,
            'type' => QuestionType::SINGLE_CHOICE,
            'title' => ['uz' => 'Xizmatingiz qanday?'],
            'position' => 1,
            'is_required' => true,
        ]);

        $this->opt1 = SurveyQuestionOption::create([
            'question_id' => $this->question1->id,
            'label' => ['uz' => 'A’lo'],
            'position' => 1,
        ]);

        SurveyQuestionOption::create([
            'question_id' => $this->question1->id,
            'label' => ['uz' => 'Yaxshi'],
            'position' => 2,
        ]);

        $this->page2 = SurveyPage::create([
            'survey_id' => $this->activeSurvey->id,
            'position' => 2,
            'title' => ['uz' => '2-sahifa'],
        ]);

        $this->question2 = SurveyQuestion::create([
            'page_id' => $this->page2->id,
            'type' => QuestionType::RATING,
            'title' => ['uz' => 'Baholang'],
            'position' => 1,
            'is_required' => true,
            'settings' => ['min' => 1, 'max' => 5],
        ]);
    }

    public function test_cannot_start_participation_on_inactive_survey(): void
    {
        $draftSurvey = Survey::create([
            'organization_id' => $this->activeSurvey->organization_id,
            'created_by' => $this->businessUser->id,
            'status' => SurveyStatus::DRAFT,
            'default_locale' => 'uz',
            'locales' => ['uz'],
            'title' => ['uz' => 'Qoralama'],
        ]);

        $response = $this->actingAs($this->respondentA)->postJson(
            "/api/v1/respondent/surveys/{$draftSurvey->id}/participate"
        );

        $response->assertStatus(422)
            ->assertJsonPath('error.code', 'SURVEY_NOT_ACTIVE');
    }

    public function test_respondent_participation_flow_and_answers(): void
    {
        // 1. Respondent A starts participation
        $startResponse = $this->actingAs($this->respondentA)->postJson(
            "/api/v1/respondent/surveys/{$this->activeSurvey->id}/participate",
            ['locale' => 'uz']
        );

        $startResponse->assertStatus(201)
            ->assertJsonPath('status', 'in_progress')
            ->assertJsonPath('current_step.progress.current_step', 1)
            ->assertJsonPath('current_step.progress.total_steps', 2);

        $participationId = $startResponse->json('participation_id');

        $this->assertDatabaseHas('participations', [
            'id' => $participationId,
            'survey_id' => $this->activeSurvey->id,
            'user_id' => $this->respondentA->id,
            'locale' => 'uz',
            'status' => 'in_progress',
        ]);

        // 2. Calling participate again resumes the existing participation
        $resumeResponse = $this->actingAs($this->respondentA)->postJson(
            "/api/v1/respondent/surveys/{$this->activeSurvey->id}/participate"
        );
        $resumeResponse->assertStatus(201)
            ->assertJsonPath('participation_id', $participationId);

        // 3. Save answers for page 1
        $answer1Response = $this->actingAs($this->respondentA)->putJson(
            "/api/v1/respondent/participations/{$participationId}/pages/{$this->page1->id}",
            [
                'answers' => [
                    $this->question1->id => $this->opt1->id,
                ],
            ]
        );

        $answer1Response->assertStatus(200)
            ->assertJsonPath('next', ['type' => 'page', 'id' => $this->page2->id])
            ->assertJsonPath('can_complete', false);

        $this->assertDatabaseHas('answers', [
            'participation_id' => $participationId,
            'question_id' => $this->question1->id,
        ]);
        $this->assertDatabaseHas('answer_options', [
            'option_id' => $this->opt1->id,
        ]);

        // 4. Duplicate submit on page 1 cleanly updates answer
        $reAnswerResponse = $this->actingAs($this->respondentA)->putJson(
            "/api/v1/respondent/participations/{$participationId}/pages/{$this->page1->id}",
            [
                'answers' => [
                    $this->question1->id => $this->opt1->id,
                ],
            ]
        );
        $reAnswerResponse->assertStatus(200);

        // 5. Save answers for page 2 with invalid rating
        $invalidResponse = $this->actingAs($this->respondentA)->putJson(
            "/api/v1/respondent/participations/{$participationId}/pages/{$this->page2->id}",
            [
                'answers' => [
                    $this->question2->id => 99,
                ],
            ]
        );
        $invalidResponse->assertStatus(422)
            ->assertJsonPath('error.code', 'INVALID_ANSWER');

        // 6. Save answers for page 2 with valid rating
        $valid2Response = $this->actingAs($this->respondentA)->putJson(
            "/api/v1/respondent/participations/{$participationId}/pages/{$this->page2->id}",
            [
                'answers' => [
                    $this->question2->id => 5,
                ],
            ]
        );
        $valid2Response->assertStatus(200)
            ->assertJsonPath('next', ['type' => 'completed'])
            ->assertJsonPath('can_complete', true);

        // 7. Complete participation
        $completeResponse = $this->actingAs($this->respondentA)->postJson(
            "/api/v1/respondent/participations/{$participationId}/complete"
        );
        $completeResponse->assertStatus(200)
            ->assertJsonPath('completed', true)
            ->assertJsonPath('status', 'completed');

        $participation = Participation::find($participationId);
        $this->assertEquals(ParticipationStatus::COMPLETED, $participation->status);
        $this->assertNotNull($participation->completed_at);

        // 8. Block editing answers on completed participation
        $this->actingAs($this->respondentA)->putJson(
            "/api/v1/respondent/participations/{$participationId}/pages/{$this->page1->id}",
            ['answers' => [$this->question1->id => $this->opt1->id]]
        )->assertStatus(422)
            ->assertJsonPath('error.code', 'PARTICIPATION_ALREADY_COMPLETED');

        // 9. Cannot start a new participation after completion
        $this->actingAs($this->respondentA)->postJson(
            "/api/v1/respondent/surveys/{$this->activeSurvey->id}/participate"
        )->assertOk()
            ->assertJsonPath('participation_id', $participationId)
            ->assertJsonPath('completed', true);

        $this->assertDatabaseCount('participations', 1);
    }

    public function test_completion_requires_all_required_answers_and_an_active_survey(): void
    {
        $participation = app(StartOrResumeParticipationAction::class)
            ->execute($this->activeSurvey, $this->respondentA);
        $endpoint = "/api/v1/respondent/participations/{$participation->id}/complete";

        $this->actingAs($this->respondentA)->postJson($endpoint)
            ->assertUnprocessable()->assertJsonPath('error.code', 'INVALID_ANSWER');

        $this->assertDatabaseHas('participations', ['id' => $participation->id, 'status' => 'in_progress', 'completed_at' => null]);

        $this->activeSurvey->update(['status' => SurveyStatus::PAUSED]);
        $this->postJson($endpoint)->assertUnprocessable()->assertJsonPath('error.code', 'SURVEY_NOT_ACTIVE');
    }

    public function test_malformed_answers_and_fractional_values_are_rejected_without_writes(): void
    {
        $participation = app(StartOrResumeParticipationAction::class)
            ->execute($this->activeSurvey, $this->respondentA);
        $endpoint = "/api/v1/respondent/participations/{$participation->id}/pages";

        $this->actingAs($this->respondentA)->putJson("{$endpoint}/{$this->page1->id}", ['answers' => 'invalid'])
            ->assertUnprocessable()->assertJsonPath('error.code', 'VALIDATION_ERROR');
        $this->putJson("{$endpoint}/{$this->page1->id}", ['answers' => [$this->question1->id => $this->opt1->id.'.9']])
            ->assertUnprocessable()->assertJsonPath('error.code', 'INVALID_ANSWER');
        $this->question1->update(['type' => QuestionType::MULTIPLE_CHOICE, 'settings' => ['min_selections' => 2]]);
        foreach ([[$this->opt1->id, $this->opt1->id], [['invalid'], $this->opt1->id]] as $value) {
            $this->putJson("{$endpoint}/{$this->page1->id}", ['answers' => [$this->question1->id => $value]])
                ->assertUnprocessable()->assertJsonPath('error.code', 'INVALID_ANSWER');
        }

        $this->assertDatabaseCount('answers', 0);
        $this->assertDatabaseCount('answer_options', 0);

        $this->question1->update(['type' => QuestionType::SINGLE_CHOICE, 'settings' => []]);
        $this->putJson("{$endpoint}/{$this->page1->id}", ['answers' => [$this->question1->id => $this->opt1->id]])->assertOk();
        $this->putJson("{$endpoint}/{$this->page2->id}", ['answers' => [$this->question2->id => 5.9]])
            ->assertUnprocessable()->assertJsonPath('error.code', 'INVALID_ANSWER');
        $this->assertDatabaseCount('answers', 1);
        $this->assertDatabaseMissing('answers', ['question_id' => $this->question2->id]);
    }

    public function test_resume_returns_saved_answers_and_original_locale(): void
    {
        $start = $this->actingAs($this->respondentA)->postJson(
            "/api/v1/respondent/surveys/{$this->activeSurvey->id}/participate", ['locale' => 'ru']
        )->assertCreated();
        $participationId = $start->json('participation_id');
        $this->putJson("/api/v1/respondent/participations/{$participationId}/pages/{$this->page1->id}", [
            'answers' => [$this->question1->id => $this->opt1->id],
        ])->assertOk();

        $this->getJson("/api/v1/respondent/participations/{$participationId}")
            ->assertOk()->assertJsonPath('locale', 'ru')
            ->assertJsonPath("answers.{$this->question1->id}.option_ids", [$this->opt1->id]);
    }

    public function test_stale_models_cannot_overwrite_completed_answers(): void
    {
        $start = app(StartOrResumeParticipationAction::class);
        $save = app(SavePageAnswersAction::class);
        $complete = app(CompleteParticipationAction::class);
        $participation = $start->execute($this->activeSurvey, $this->respondentA);
        $stale = Participation::findOrFail($participation->id);
        $save->execute($participation, $this->page1, [$this->question1->id => $this->opt1->id]);
        $save->execute($participation, $this->page2, [$this->question2->id => 5]);
        $complete->execute($participation);
        $completedAt = $participation->fresh()->completed_at;
        $complete->execute($stale);
        $this->assertEquals($completedAt, $participation->fresh()->completed_at);

        $this->expectException(ApiException::class);
        $save->execute($stale, $this->page2, [$this->question2->id => 1]);
    }

    public function test_ownership_and_security_between_respondents(): void
    {
        $startResponse = $this->actingAs($this->respondentA)->postJson(
            "/api/v1/respondent/surveys/{$this->activeSurvey->id}/participate"
        );
        $participationId = $startResponse->json('participation_id');

        // Respondent B cannot view Respondent A's participation
        $this->actingAs($this->respondentB)->getJson(
            "/api/v1/respondent/participations/{$participationId}"
        )->assertStatus(403)
            ->assertJsonPath('error.code', 'FORBIDDEN');

        // Respondent B cannot answer Respondent A's participation
        $this->actingAs($this->respondentB)->putJson(
            "/api/v1/respondent/participations/{$participationId}/pages/{$this->page1->id}",
            ['answers' => [$this->question1->id => $this->opt1->id]]
        )->assertStatus(403)
            ->assertJsonPath('error.code', 'FORBIDDEN');
    }
}
