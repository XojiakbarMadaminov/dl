<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Organization\Actions\CreateOrganizationAction;
use App\Modules\Organization\Models\Organization;
use App\Modules\Survey\Enums\QuestionType;
use App\Modules\Survey\Enums\SurveyStatus;
use App\Modules\Survey\Models\Survey;
use App\Modules\Survey\Models\SurveyPage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SurveyBuilderTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Organization $organization;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $orgAction = new CreateOrganizationAction;
        $this->organization = $orgAction->execute('Survey Org', $this->user);
    }

    public function test_can_create_survey_with_valid_locales_and_titles(): void
    {
        $response = $this->actingAs($this->user)->postJson(
            "/api/v1/business/organizations/{$this->organization->id}/surveys",
            [
                'default_locale' => 'uz',
                'locales' => ['uz', 'ru'],
                'title' => [
                    'uz' => 'Mijozlar fikrini o‘rganish',
                    'ru' => 'Опрос удовлетворенности клиентов',
                ],
                'description' => [
                    'uz' => 'Qisqacha tavsif',
                    'ru' => 'Краткое описание',
                ],
                'welcome_message' => [
                    'uz' => 'Xush kelibsiz!',
                    'ru' => 'Добро пожаловать!',
                ],
                'thank_you_message' => [
                    'uz' => 'Rahmat!',
                    'ru' => 'Спасибо!',
                ],
            ]
        );

        $response->assertStatus(201)
            ->assertJsonPath('survey.status', 'draft')
            ->assertJsonPath('survey.default_locale', 'uz')
            ->assertJsonPath('survey.locales', ['uz', 'ru'])
            ->assertJsonPath('survey.title.uz', 'Mijozlar fikrini o‘rganish')
            ->assertJsonPath('survey.title.ru', 'Опрос удовлетворенности клиентов');

        $surveyId = $response->json('survey.id');
        $this->assertDatabaseHas('surveys', [
            'id' => $surveyId,
            'organization_id' => $this->organization->id,
            'status' => 'draft',
        ]);

        // Verify default first page was created
        $this->assertDatabaseHas('survey_pages', [
            'survey_id' => $surveyId,
            'position' => 1,
        ]);
    }

    public function test_cannot_create_survey_missing_title_for_any_selected_locale(): void
    {
        $response = $this->actingAs($this->user)->postJson(
            "/api/v1/business/organizations/{$this->organization->id}/surveys",
            [
                'default_locale' => 'uz',
                'locales' => ['uz', 'ru', 'en'],
                'title' => [
                    'uz' => 'Sarlavha bor',
                    'ru' => '', // missing ru title
                ],
            ]
        );

        $response->assertStatus(422)
            ->assertJsonPath('error.code', 'VALIDATION_ERROR')
            ->assertJsonStructure([
                'error' => [
                    'fields' => ['title.ru', 'title.en'],
                ],
            ]);
    }

    public function test_cannot_create_survey_if_default_locale_is_not_selected(): void
    {
        $response = $this->actingAs($this->user)->postJson(
            "/api/v1/business/organizations/{$this->organization->id}/surveys",
            [
                'default_locale' => 'en',
                'locales' => ['uz', 'ru'],
                'title' => [
                    'uz' => 'Sarlavha',
                    'ru' => 'Заголовок',
                ],
            ]
        );

        $response->assertStatus(422)
            ->assertJsonPath('error.code', 'INVALID_DEFAULT_LOCALE');
    }

    public function test_page_lifecycle_and_reordering(): void
    {
        $survey = Survey::create([
            'organization_id' => $this->organization->id,
            'created_by' => $this->user->id,
            'status' => SurveyStatus::DRAFT,
            'default_locale' => 'uz',
            'locales' => ['uz'],
            'title' => ['uz' => 'Test'],
        ]);

        $page1 = SurveyPage::create([
            'survey_id' => $survey->id,
            'position' => 1,
        ]);

        // Add page 2
        $response = $this->actingAs($this->user)->postJson(
            "/api/v1/business/organizations/{$this->organization->id}/surveys/{$survey->id}/pages",
            [
                'title' => ['uz' => 'Sahifa 2'],
            ]
        );

        $response->assertStatus(201)
            ->assertJsonPath('page.position', 2);
        $page2Id = $response->json('page.id');

        // Cannot delete last remaining page
        $page2 = SurveyPage::find($page2Id);
        $page2->delete();

        $delResponse = $this->actingAs($this->user)->deleteJson(
            "/api/v1/business/organizations/{$this->organization->id}/pages/{$page1->id}"
        );
        $delResponse->assertStatus(422)
            ->assertJsonPath('error.code', 'CANNOT_DELETE_LAST_PAGE');

        // Recreate page 2 and test reorder
        $page2 = SurveyPage::create(['survey_id' => $survey->id, 'position' => 2]);

        $reorderResponse = $this->actingAs($this->user)->putJson(
            "/api/v1/business/organizations/{$this->organization->id}/surveys/{$survey->id}/pages/reorder",
            ['pages' => [$page2->id, $page1->id]]
        );
        $reorderResponse->assertStatus(200);

        $this->assertEquals(1, $page2->fresh()->position);
        $this->assertEquals(2, $page1->fresh()->position);
    }

    public function test_question_crud_duplicate_and_settings_validation(): void
    {
        $survey = Survey::create([
            'organization_id' => $this->organization->id,
            'created_by' => $this->user->id,
            'status' => SurveyStatus::DRAFT,
            'default_locale' => 'uz',
            'locales' => ['uz'],
            'title' => ['uz' => 'Test'],
        ]);

        $page = SurveyPage::create([
            'survey_id' => $survey->id,
            'position' => 1,
        ]);

        // Create rating question with valid min/max
        $qResponse = $this->actingAs($this->user)->postJson(
            "/api/v1/business/organizations/{$this->organization->id}/pages/{$page->id}/questions",
            [
                'type' => QuestionType::RATING->value,
                'title' => ['uz' => 'Baholang'],
                'is_required' => true,
                'settings' => [
                    'min' => 1,
                    'max' => 5,
                ],
            ]
        );

        $qResponse->assertStatus(201)
            ->assertJsonPath('question.type', 'rating')
            ->assertJsonPath('question.position', 1)
            ->assertJsonPath('question.is_required', true)
            ->assertJsonPath('question.settings.max', 5);

        $questionId = $qResponse->json('question.id');

        // Add option to question
        $optResponse = $this->actingAs($this->user)->postJson(
            "/api/v1/business/organizations/{$this->organization->id}/questions/{$questionId}/options",
            [
                'label' => ['uz' => 'Variant 1'],
            ]
        );
        $optResponse->assertStatus(201)
            ->assertJsonPath('option.label.uz', 'Variant 1');

        // Duplicate question
        $dupResponse = $this->actingAs($this->user)->postJson(
            "/api/v1/business/organizations/{$this->organization->id}/questions/{$questionId}/duplicate"
        );

        $dupResponse->assertStatus(201)
            ->assertJsonPath('question.position', 2)
            ->assertJsonPath('question.type', 'rating')
            ->assertJsonCount(1, 'question.options');
    }

    public function test_cannot_edit_active_survey(): void
    {
        $survey = Survey::create([
            'organization_id' => $this->organization->id,
            'created_by' => $this->user->id,
            'status' => SurveyStatus::ACTIVE,
            'default_locale' => 'uz',
            'locales' => ['uz'],
            'title' => ['uz' => 'Active Survey'],
        ]);

        $page = SurveyPage::create([
            'survey_id' => $survey->id,
            'position' => 1,
        ]);

        // Try updating active survey metadata
        $this->actingAs($this->user)->patchJson(
            "/api/v1/business/organizations/{$this->organization->id}/surveys/{$survey->id}",
            ['title' => ['uz' => 'Mutated Title']]
        )->assertStatus(422)
            ->assertJsonPath('error.code', 'SURVEY_NOT_EDITABLE');

        // Try adding a page to active survey
        $this->actingAs($this->user)->postJson(
            "/api/v1/business/organizations/{$this->organization->id}/surveys/{$survey->id}/pages",
            ['title' => ['uz' => 'New Page']]
        )->assertStatus(422)
            ->assertJsonPath('error.code', 'SURVEY_NOT_EDITABLE');

        // Try adding a question to active survey page
        $this->actingAs($this->user)->postJson(
            "/api/v1/business/organizations/{$this->organization->id}/pages/{$page->id}/questions",
            [
                'type' => 'short_text',
                'title' => ['uz' => 'Savol'],
            ]
        )->assertStatus(422)
            ->assertJsonPath('error.code', 'SURVEY_NOT_EDITABLE');
    }

    public function test_tenant_isolation_on_surveys(): void
    {
        $otherUser = User::factory()->create();
        $orgAction = new CreateOrganizationAction;
        $otherOrg = $orgAction->execute('Other Org', $otherUser);

        $survey = Survey::create([
            'organization_id' => $otherOrg->id,
            'created_by' => $otherUser->id,
            'status' => SurveyStatus::DRAFT,
            'default_locale' => 'uz',
            'locales' => ['uz'],
            'title' => ['uz' => 'Other Org Survey'],
        ]);

        // User from Org A cannot access survey from Org B
        $this->actingAs($this->user)->getJson(
            "/api/v1/business/organizations/{$this->organization->id}/surveys/{$survey->id}"
        )->assertStatus(404);

        $this->actingAs($this->user)->getJson(
            "/api/v1/business/organizations/{$otherOrg->id}/surveys/{$survey->id}"
        )->assertStatus(403);
    }
}
