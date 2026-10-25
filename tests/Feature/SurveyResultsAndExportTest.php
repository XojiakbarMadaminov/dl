<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Organization\Actions\CreateOrganizationAction;
use App\Modules\Organization\Models\Organization;
use App\Modules\Participation\Actions\CompleteParticipationAction;
use App\Modules\Participation\Actions\SavePageAnswersAction;
use App\Modules\Participation\Actions\StartOrResumeParticipationAction;
use App\Modules\Participation\Models\Participation;
use App\Modules\Survey\Enums\QuestionType;
use App\Modules\Survey\Enums\SurveyStatus;
use App\Modules\Survey\Models\Survey;
use App\Modules\Survey\Models\SurveyPage;
use App\Modules\Survey\Models\SurveyQuestion;
use App\Modules\Survey\Models\SurveyQuestionOption;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SurveyResultsAndExportTest extends TestCase
{
    use RefreshDatabase;

    private User $businessUser;

    private Organization $organization;

    private Survey $survey;

    private SurveyQuestion $choiceQ;

    private SurveyQuestion $ratingQ;

    private SurveyQuestionOption $optA;

    private SurveyQuestionOption $optB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->businessUser = User::factory()->create();
        $orgAction = new CreateOrganizationAction;
        $this->organization = $orgAction->execute('Results Org', $this->businessUser, '123456789');

        $this->survey = Survey::create([
            'organization_id' => $this->organization->id,
            'created_by' => $this->businessUser->id,
            'status' => SurveyStatus::ACTIVE,
            'default_locale' => 'uz',
            'locales' => ['uz'],
            'title' => ['uz' => 'Natijalar so‘rovi'],
            'published_at' => now(),
        ]);

        $page = SurveyPage::create([
            'survey_id' => $this->survey->id,
            'position' => 1,
        ]);

        $this->choiceQ = SurveyQuestion::create([
            'page_id' => $page->id,
            'type' => QuestionType::SINGLE_CHOICE,
            'title' => ['uz' => 'Sevimli taom'],
            'position' => 1,
            'is_required' => true,
        ]);

        $this->optA = SurveyQuestionOption::create([
            'question_id' => $this->choiceQ->id,
            'label' => ['uz' => 'Osh'],
            'position' => 1,
        ]);

        $this->optB = SurveyQuestionOption::create([
            'question_id' => $this->choiceQ->id,
            'label' => ['uz' => 'Somsa'],
            'position' => 2,
        ]);

        $this->ratingQ = SurveyQuestion::create([
            'page_id' => $page->id,
            'type' => QuestionType::RATING,
            'title' => ['uz' => 'Taom sifati'],
            'position' => 2,
            'is_required' => true,
            'settings' => ['min' => 1, 'max' => 5],
        ]);

        // Create 2 respondents: 1 completed, 1 in-progress
        $startAction = app(StartOrResumeParticipationAction::class);
        $saveAction = app(SavePageAnswersAction::class);
        $completeAction = app(CompleteParticipationAction::class);

        // Respondent 1 (Completed: Osh, rating 5)
        $r1 = User::factory()->create(['name' => 'Ali', 'email' => 'ali@example.com']);
        $p1 = $startAction->execute($this->survey, $r1);
        $saveAction->execute($p1, $page, [
            $this->choiceQ->id => $this->optA->id,
            $this->ratingQ->id => 5,
        ]);
        $completeAction->execute($p1);

        // Respondent 2 (In progress: Somsa, rating 4)
        $r2 = User::factory()->create(['name' => 'Vali', 'email' => 'vali@example.com']);
        $p2 = $startAction->execute($this->survey, $r2);
        $saveAction->execute($p2, $page, [
            $this->choiceQ->id => $this->optB->id,
            $this->ratingQ->id => 4,
        ]);
    }

    public function test_results_summary_returns_accurate_metrics(): void
    {
        $response = $this->actingAs($this->businessUser)->getJson(
            "/api/v1/business/organizations/{$this->organization->id}/surveys/{$this->survey->id}/results/summary"
        );

        $response->assertStatus(200)
            ->assertJsonPath('started_count', 2)
            ->assertJsonPath('completed_count', 1)
            ->assertJsonPath('in_progress_count', 1);

        $this->assertEquals(50.0, (float) $response->json('completion_rate'));
    }

    public function test_question_aggregates_for_choice_and_rating(): void
    {
        // Choice aggregate
        $choiceResp = $this->actingAs($this->businessUser)->getJson(
            "/api/v1/business/organizations/{$this->organization->id}/surveys/{$this->survey->id}/results/questions/{$this->choiceQ->id}"
        );

        $choiceResp->assertStatus(200)
            ->assertJsonPath('total_responses', 2)
            ->assertJsonPath('distribution.0.count', 1)
            ->assertJsonPath('distribution.1.count', 1);

        // Rating aggregate
        $ratingResp = $this->actingAs($this->businessUser)->getJson(
            "/api/v1/business/organizations/{$this->organization->id}/surveys/{$this->survey->id}/results/questions/{$this->ratingQ->id}"
        );

        $ratingResp->assertStatus(200)
            ->assertJsonPath('total_responses', 2)
            ->assertJsonPath('average', 4.5);
    }

    public function test_participations_list_and_detail(): void
    {
        $listResp = $this->actingAs($this->businessUser)->getJson(
            "/api/v1/business/organizations/{$this->organization->id}/surveys/{$this->survey->id}/participations"
        );

        $listResp->assertStatus(200)
            ->assertJsonPath('total', 2);

        $p1 = Participation::where('user_id', User::where('email', 'ali@example.com')->value('id'))->firstOrFail();

        $detailResp = $this->actingAs($this->businessUser)->getJson(
            "/api/v1/business/organizations/{$this->organization->id}/surveys/{$this->survey->id}/participations/{$p1->id}"
        );

        $detailResp->assertStatus(200)
            ->assertJsonPath('participation.user.name', 'Ali')
            ->assertJsonPath('participation.status', 'completed')
            ->assertJsonPath('participation.answers.0.selected_options.0.label', 'Osh');
    }

    public function test_csv_export_returns_valid_content(): void
    {
        $response = $this->actingAs($this->businessUser)->get(
            "/api/v1/business/organizations/{$this->organization->id}/surveys/{$this->survey->id}/results/export"
        );

        $response->assertStatus(200);
        $this->assertEquals('text/csv; charset=UTF-8', $response->headers->get('Content-Type'));

        $content = $response->getContent();
        $this->assertIsString($content);
        $this->assertStringContainsString('Participation ID', $content);
        $this->assertStringContainsString('User ID', $content);
        $this->assertStringContainsString('Osh', $content);
    }

    public function test_list_summary_aggregate_and_export_share_filters(): void
    {
        $base = "/api/v1/business/organizations/{$this->organization->id}/surveys/{$this->survey->id}";
        $inProgress = Participation::query()->where('status', 'in_progress')->firstOrFail();
        $inProgress->update(['locale' => 'ru']);
        $filter = '?status=in_progress&participation_locale=ru';
        $this->actingAs($this->businessUser)->getJson("{$base}/participations{$filter}")
            ->assertOk()->assertJsonPath('total', 1)->assertJsonPath('data.0.id', $inProgress->id)
            ->assertJsonMissingPath('data.0.user.platform_role')->assertJsonMissingPath('data.0.user.status');
        $this->getJson("{$base}/results/summary{$filter}")->assertOk()
            ->assertJsonPath('started_count', 1)->assertJsonPath('completed_count', 0);
        $this->getJson("{$base}/results/questions/{$this->ratingQ->id}{$filter}")->assertOk()
            ->assertJsonPath('total_responses', 1)->assertJsonPath('average', 4);
        $response = $this->get("{$base}/results/export{$filter}")->assertOk();
        $this->assertStringStartsWith("\xEF\xBB\xBF", $response->getContent());
        $this->assertStringContainsString('Somsa', $response->getContent());
        $this->assertStringNotContainsString('Osh', $response->getContent());

        $this->get("{$base}/results/export?status=completed")->assertOk()->assertSee('Osh')->assertDontSee('Somsa');
        $this->getJson("{$base}/results/summary?status=unknown")->assertUnprocessable();
        $this->getJson("{$base}/results/export?participation_locale[]=ru")->assertUnprocessable();
    }

    public function test_export_neutralizes_spreadsheet_formulas_in_authored_and_answer_content(): void
    {
        $this->choiceQ->update(['title' => ['uz' => '=HYPERLINK("https://example.test")']]);
        $this->optA->update(['label' => ['uz' => '=1+1']]);
        $response = $this->actingAs($this->businessUser)->get(
            "/api/v1/business/organizations/{$this->organization->id}/surveys/{$this->survey->id}/results/export?status=completed"
        )->assertOk();
        $this->assertStringContainsString("'=1+1", $response->getContent());
    }
}
