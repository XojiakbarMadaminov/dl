<?php

namespace Tests\Feature;

use App\Exceptions\ApiException;
use App\Models\User;
use App\Modules\Organization\Actions\CreateOrganizationAction;
use App\Modules\Organization\Models\Organization;
use App\Modules\Participation\Validators\AnswerValidator;
use App\Modules\Survey\Enums\QuestionType;
use App\Modules\Survey\Enums\SurveyStatus;
use App\Modules\Survey\Models\Survey;
use App\Modules\Survey\Models\SurveyPage;
use App\Modules\Survey\Models\SurveyQuestion;
use App\Modules\Survey\Models\SurveyQuestionOption;
use App\Modules\Survey\Validators\SurveyTranslationValidator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LocalizationAndPreviewTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Organization $organization;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $action = new CreateOrganizationAction;
        $this->organization = $action->execute('Localization Org', $this->user);
    }

    public function test_survey_with_only_uz_requires_only_uz(): void
    {
        $survey = Survey::create([
            'organization_id' => $this->organization->id,
            'created_by' => $this->user->id,
            'status' => SurveyStatus::DRAFT,
            'default_locale' => 'uz',
            'locales' => ['uz'],
            'title' => ['uz' => 'Faqat o‘zbekcha so‘rovnoma'],
        ]);

        $page = SurveyPage::create(['survey_id' => $survey->id, 'position' => 1]);

        $q = SurveyQuestion::create([
            'page_id' => $page->id,
            'type' => QuestionType::SINGLE_CHOICE,
            'title' => ['uz' => 'Savol matni'],
            'position' => 1,
            'is_required' => true,
        ]);

        SurveyQuestionOption::create([
            'question_id' => $q->id,
            'label' => ['uz' => 'Variant 1'],
            'position' => 1,
        ]);

        SurveyQuestionOption::create([
            'question_id' => $q->id,
            'label' => ['uz' => 'Variant 2'],
            'position' => 2,
        ]);

        $validator = new SurveyTranslationValidator;
        $errors = $validator->validate($survey, throw: false);

        $this->assertEmpty($errors, 'Survey with only uz should have no errors when uz translations are complete');
    }

    public function test_survey_with_uz_and_ru_validates_both_and_ignores_en(): void
    {
        $survey = Survey::create([
            'organization_id' => $this->organization->id,
            'created_by' => $this->user->id,
            'status' => SurveyStatus::DRAFT,
            'default_locale' => 'uz',
            'locales' => ['uz', 'ru'],
            'title' => ['uz' => 'O‘zbekcha sarlavha', 'ru' => 'Русский заголовок'],
        ]);

        $page = SurveyPage::create(['survey_id' => $survey->id, 'position' => 1]);

        $q = SurveyQuestion::create([
            'page_id' => $page->id,
            'type' => QuestionType::SINGLE_CHOICE,
            'title' => ['uz' => 'Savol 1'], // Missing ru title
            'position' => 1,
            'is_required' => true,
        ]);

        $opt1 = SurveyQuestionOption::create([
            'question_id' => $q->id,
            'label' => ['uz' => 'Variant 1', 'ru' => 'Вариант 1'],
            'position' => 1,
        ]);

        $opt2 = SurveyQuestionOption::create([
            'question_id' => $q->id,
            'label' => ['uz' => 'Variant 2'], // Missing ru label
            'position' => 2,
        ]);

        $validator = new SurveyTranslationValidator;

        try {
            $validator->validate($survey);
            $this->fail('Expected ApiException was not thrown.');
        } catch (ApiException $e) {
            $this->assertEquals('TRANSLATION_INCOMPLETE', $e->getErrorCode());
            $this->assertEquals(422, $e->getStatusCode());
            $fields = $e->getFields();

            $this->assertArrayHasKey("questions.{$q->id}.title.ru", $fields);
            $this->assertArrayHasKey("options.{$opt2->id}.label.ru", $fields);
            // English was not selected, so no English errors should be present
            $this->assertArrayNotHasKey("questions.{$q->id}.title.en", $fields);
        }
    }

    public function test_survey_with_uz_ru_and_en_requires_all_three_locales(): void
    {
        $survey = Survey::create([
            'organization_id' => $this->organization->id,
            'created_by' => $this->user->id,
            'status' => SurveyStatus::DRAFT,
            'default_locale' => 'uz',
            'locales' => ['uz', 'ru', 'en'],
            'title' => ['uz' => 'Sarlavha', 'ru' => 'Заголовок', 'en' => 'Title'],
        ]);

        $page = SurveyPage::create(['survey_id' => $survey->id, 'position' => 1]);

        $q = SurveyQuestion::create([
            'page_id' => $page->id,
            'type' => QuestionType::SINGLE_CHOICE,
            'title' => ['uz' => 'Savol', 'ru' => 'Вопрос'], // Missing en
            'position' => 1,
            'is_required' => true,
        ]);

        SurveyQuestionOption::create([
            'question_id' => $q->id,
            'label' => ['uz' => 'V1', 'ru' => 'B1', 'en' => 'O1'],
            'position' => 1,
        ]);

        SurveyQuestionOption::create([
            'question_id' => $q->id,
            'label' => ['uz' => 'V2', 'ru' => 'B2'], // Missing en
            'position' => 2,
        ]);

        $validator = new SurveyTranslationValidator;
        $errors = $validator->validate($survey, throw: false);

        $this->assertArrayHasKey("questions.{$q->id}.title.en", $errors);
        $this->assertArrayHasKey("options.{$survey->pages[0]->questions[0]->options[1]->id}.label.en", $errors);
    }

    public function test_answer_validator_checks_question_rules_accurately(): void
    {
        $validator = new AnswerValidator;

        $survey = Survey::create([
            'organization_id' => $this->organization->id,
            'created_by' => $this->user->id,
            'status' => SurveyStatus::DRAFT,
            'default_locale' => 'uz',
            'locales' => ['uz'],
            'title' => ['uz' => 'Test'],
        ]);

        $page = SurveyPage::create(['survey_id' => $survey->id, 'position' => 1]);

        // 1. Rating question
        $ratingQ = SurveyQuestion::create([
            'page_id' => $page->id,
            'type' => QuestionType::RATING,
            'title' => ['uz' => 'Reyting'],
            'position' => 1,
            'is_required' => true,
            'settings' => ['min' => 1, 'max' => 5],
        ]);

        $this->assertNotEmpty($validator->validateQuestionAnswer($ratingQ, 10)); // out of bounds
        $this->assertEmpty($validator->validateQuestionAnswer($ratingQ, 4));  // valid

        // 2. Single choice
        $scQ = SurveyQuestion::create([
            'page_id' => $page->id,
            'type' => QuestionType::SINGLE_CHOICE,
            'title' => ['uz' => 'Tanlang'],
            'position' => 2,
            'is_required' => true,
        ]);
        $opt = SurveyQuestionOption::create([
            'question_id' => $scQ->id,
            'label' => ['uz' => 'A'],
            'position' => 1,
        ]);
        $scQ->load('options');

        $this->assertNotEmpty($validator->validateQuestionAnswer($scQ, 9999)); // invalid option
        $this->assertEmpty($validator->validateQuestionAnswer($scQ, $opt->id)); // valid option
    }

    public function test_survey_preview_returns_localized_flow_and_validates_answers(): void
    {
        $survey = Survey::create([
            'organization_id' => $this->organization->id,
            'created_by' => $this->user->id,
            'status' => SurveyStatus::DRAFT,
            'default_locale' => 'uz',
            'locales' => ['uz', 'ru'],
            'title' => ['uz' => 'O‘zbekcha', 'ru' => 'Русский'],
            'welcome_message' => ['uz' => 'Xush kelibsiz', 'ru' => 'Добро пожаловать'],
        ]);

        $page1 = SurveyPage::create(['survey_id' => $survey->id, 'position' => 1]);
        $page2 = SurveyPage::create(['survey_id' => $survey->id, 'position' => 2]);

        $q = SurveyQuestion::create([
            'page_id' => $page1->id,
            'type' => QuestionType::SHORT_TEXT,
            'title' => ['uz' => 'Ismingiz?', 'ru' => 'Ваше имя?'],
            'position' => 1,
            'is_required' => true,
        ]);

        // Preview in Russian
        $previewResponse = $this->actingAs($this->user)->getJson(
            "/api/v1/business/organizations/{$this->organization->id}/surveys/{$survey->id}/preview?locale=ru"
        );

        $previewResponse->assertStatus(200)
            ->assertJsonPath('survey.title', 'Русский')
            ->assertJsonPath('survey.welcome_message', 'Добро пожаловать')
            ->assertJsonPath('current_step.progress.current_step', 1)
            ->assertJsonPath('current_step.progress.total_steps', 2)
            ->assertJsonPath('current_step.next', $page2->id)
            ->assertJsonPath('current_step.page.questions.0.title', 'Ваше имя?');

        // Test answering preview step
        $validateResponse = $this->actingAs($this->user)->postJson(
            "/api/v1/business/organizations/{$this->organization->id}/surveys/{$survey->id}/preview/pages/{$page1->id}/validate",
            [
                'locale' => 'ru',
                'answers' => [
                    $q->id => 'Александр',
                ],
            ]
        );

        $validateResponse->assertStatus(200)
            ->assertJsonPath('valid', true)
            ->assertJsonPath('current_step.next', $page2->id);

        // Preview does not write answers to database
        $this->assertDatabaseEmpty('participations');
        $this->assertDatabaseEmpty('answers');
    }
}
