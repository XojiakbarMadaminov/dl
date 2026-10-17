<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Organization\Actions\CreateOrganizationAction;
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
        $org = $orgAction->execute('Participation Org', $this->businessUser);

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
            ->assertJsonPath('next', $this->page2->id)
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
            ->assertJsonPath('next', 'complete')
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
        )->assertStatus(422)
            ->assertJsonPath('error.code', 'SURVEY_ALREADY_COMPLETED');
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
