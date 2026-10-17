<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Organization\Actions\CreateOrganizationAction;
use App\Modules\Organization\Models\Organization;
use App\Modules\Survey\Enums\QuestionType;
use App\Modules\Survey\Enums\SurveyStatus;
use App\Modules\Survey\Models\Survey;
use App\Modules\Survey\Models\SurveyPage;
use App\Modules\Survey\Models\SurveyQuestion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ModerationAndPublishingTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private User $admin;

    private Organization $organization;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->admin = User::factory()->admin()->create();

        $orgAction = new CreateOrganizationAction;
        $this->organization = $orgAction->execute('Moderation Org', $this->user);
    }

    private function createCompleteSurvey(): Survey
    {
        $survey = Survey::create([
            'organization_id' => $this->organization->id,
            'created_by' => $this->user->id,
            'status' => SurveyStatus::DRAFT,
            'default_locale' => 'uz',
            'locales' => ['uz'],
            'title' => ['uz' => 'Mukammal so‘rovnoma'],
        ]);

        $page = SurveyPage::create([
            'survey_id' => $survey->id,
            'position' => 1,
        ]);

        SurveyQuestion::create([
            'page_id' => $page->id,
            'type' => QuestionType::SHORT_TEXT,
            'title' => ['uz' => 'Savol matni'],
            'position' => 1,
            'is_required' => true,
        ]);

        return $survey;
    }

    public function test_cannot_submit_incomplete_survey_for_review(): void
    {
        // Survey without questions
        $survey = Survey::create([
            'organization_id' => $this->organization->id,
            'created_by' => $this->user->id,
            'status' => SurveyStatus::DRAFT,
            'default_locale' => 'uz',
            'locales' => ['uz'],
            'title' => ['uz' => 'Bo‘sh so‘rovnoma'],
        ]);

        SurveyPage::create(['survey_id' => $survey->id, 'position' => 1]);

        $response = $this->actingAs($this->user)->postJson(
            "/api/v1/business/organizations/{$this->organization->id}/surveys/{$survey->id}/submit-review"
        );

        $response->assertStatus(422)
            ->assertJsonPath('error.code', 'VALIDATION_ERROR');
    }

    public function test_submit_for_review_approval_publish_and_archive_lifecycle(): void
    {
        $survey = $this->createCompleteSurvey();

        // 1. Submit for review
        $submitResponse = $this->actingAs($this->user)->postJson(
            "/api/v1/business/organizations/{$this->organization->id}/surveys/{$survey->id}/submit-review"
        );

        $submitResponse->assertStatus(200)
            ->assertJsonPath('survey.status', 'pending_review');

        $this->assertDatabaseHas('survey_moderations', [
            'survey_id' => $survey->id,
            'decision' => 'submitted',
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'entity_id' => $survey->id,
            'action' => 'survey.submitted_for_review',
        ]);

        // 2. Non-admin (business user) cannot approve survey
        $this->actingAs($this->user)->postJson(
            "/api/v1/admin/moderation/surveys/{$survey->id}/approve"
        )->assertStatus(403)
            ->assertJsonPath('error.code', 'FORBIDDEN');

        // 3. Admin requests changes
        $changeResponse = $this->actingAs($this->admin)->postJson(
            "/api/v1/admin/moderation/surveys/{$survey->id}/request-changes",
            ['comment' => 'Savol matnini aniqroq qiling.']
        );
        $changeResponse->assertStatus(200)
            ->assertJsonPath('survey.status', 'changes_requested');

        $this->assertDatabaseHas('survey_moderations', [
            'survey_id' => $survey->id,
            'decision' => 'changes_requested',
            'comment' => 'Savol matnini aniqroq qiling.',
        ]);

        // 4. User re-submits after changes
        $this->actingAs($this->user)->postJson(
            "/api/v1/business/organizations/{$this->organization->id}/surveys/{$survey->id}/submit-review"
        )->assertStatus(200)
            ->assertJsonPath('survey.status', 'pending_review');

        // 5. Admin approves survey
        $approveResponse = $this->actingAs($this->admin)->postJson(
            "/api/v1/admin/moderation/surveys/{$survey->id}/approve"
        );
        $approveResponse->assertStatus(200)
            ->assertJsonPath('survey.status', 'approved');

        // 6. User publishes survey
        $publishResponse = $this->actingAs($this->user)->postJson(
            "/api/v1/business/organizations/{$this->organization->id}/surveys/{$survey->id}/publish"
        );
        $publishResponse->assertStatus(200)
            ->assertJsonPath('survey.status', 'active');

        $survey->refresh();
        $this->assertNotNull($survey->published_at);
        $this->assertEquals(SurveyStatus::ACTIVE, $survey->status);

        // 7. Active survey structure is immutable (cannot add new pages/questions)
        $this->actingAs($this->user)->postJson(
            "/api/v1/business/organizations/{$this->organization->id}/surveys/{$survey->id}/pages",
            ['title' => ['uz' => 'Yangi sahifa']]
        )->assertStatus(422)
            ->assertJsonPath('error.code', 'SURVEY_NOT_EDITABLE');

        // 8. Pause and resume
        $this->actingAs($this->user)->postJson(
            "/api/v1/business/organizations/{$this->organization->id}/surveys/{$survey->id}/pause"
        )->assertStatus(200)
            ->assertJsonPath('survey.status', 'paused');

        $this->actingAs($this->user)->postJson(
            "/api/v1/business/organizations/{$this->organization->id}/surveys/{$survey->id}/resume"
        )->assertStatus(200)
            ->assertJsonPath('survey.status', 'active');

        // 9. Close survey
        $this->actingAs($this->user)->postJson(
            "/api/v1/business/organizations/{$this->organization->id}/surveys/{$survey->id}/close"
        )->assertStatus(200)
            ->assertJsonPath('survey.status', 'closed');

        $survey->refresh();
        $this->assertNotNull($survey->closed_at);

        // 10. Archive survey
        $archiveResponse = $this->actingAs($this->user)->postJson(
            "/api/v1/business/organizations/{$this->organization->id}/surveys/{$survey->id}/archive"
        );
        $archiveResponse->assertStatus(200)
            ->assertJsonPath('survey.status', 'archived');

        $survey->refresh();
        $this->assertNotNull($survey->archived_at);
        $this->assertEquals(SurveyStatus::ARCHIVED, $survey->status);

        // 11. Cannot pause or publish an archived survey
        $this->actingAs($this->user)->postJson(
            "/api/v1/business/organizations/{$this->organization->id}/surveys/{$survey->id}/publish"
        )->assertStatus(422)
            ->assertJsonPath('error.code', 'INVALID_STATUS_TRANSITION');
    }
}
