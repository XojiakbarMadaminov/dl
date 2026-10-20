<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Organization\Actions\CreateOrganizationAction;
use App\Modules\Survey\Enums\QuestionType;
use App\Modules\Survey\Enums\SurveyStatus;
use App\Modules\Survey\Models\Survey;
use App\Modules\Survey\Models\SurveyPage;
use App\Modules\Survey\Models\SurveyQuestion;
use App\Modules\Survey\Models\SurveyQuestionOption;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class SurveyLogicTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private User $respondent;

    private Survey $survey;

    private array $pages;

    private array $questions;

    private array $options;

    private string $builder;

    protected function setUp(): void
    {
        parent::setUp();
        $this->owner = User::factory()->create();
        $this->respondent = User::factory()->create();
        $organization = (new CreateOrganizationAction)->execute('Logic Org', $this->owner);
        $this->survey = Survey::create([
            'organization_id' => $organization->id, 'created_by' => $this->owner->id,
            'status' => SurveyStatus::DRAFT, 'default_locale' => 'uz', 'locales' => ['uz'],
            'title' => ['uz' => 'Mantiqli survey'],
        ]);
        $this->pages = [];
        $this->questions = [];
        foreach ([QuestionType::SINGLE_CHOICE, QuestionType::SHORT_TEXT, QuestionType::RATING] as $index => $type) {
            $this->pages[$index] = SurveyPage::create([
                'survey_id' => $this->survey->id, 'position' => $index + 1, 'title' => ['uz' => 'Sahifa'],
            ]);
            $this->questions[$index] = SurveyQuestion::create([
                'page_id' => $this->pages[$index]->id, 'type' => $type, 'position' => 1,
                'title' => ['uz' => 'Savol'], 'is_required' => true,
                'settings' => $type === QuestionType::RATING ? ['min' => 1, 'max' => 5] : [],
            ]);
        }
        $this->options = [];
        foreach (['Ha', 'Yo‘q'] as $index => $label) {
            $this->options[$index] = SurveyQuestionOption::create([
                'question_id' => $this->questions[0]->id, 'position' => $index + 1, 'label' => ['uz' => $label],
            ]);
        }
        $this->builder = "/api/v1/business/organizations/{$organization->id}/surveys/{$this->survey->id}";
    }

    private function condition(int $option = 0): array
    {
        return ['question_id' => $this->questions[0]->id, 'operator' => 'equals', 'value' => $this->options[$option]->id];
    }

    private function jumpRule(): array
    {
        return [
            'action' => 'skip_to_page', 'source_page_id' => $this->pages[0]->id,
            'target_page_id' => $this->pages[2]->id, 'priority' => 10, 'match' => 'all',
            'conditions' => [$this->condition(1)],
        ];
    }

    private function visibilityRule(): array
    {
        return [
            'action' => 'show_question', 'target_question_id' => $this->questions[1]->id,
            'priority' => 0, 'match' => 'all', 'conditions' => [$this->condition()],
        ];
    }

    private function rules(array $rules): void
    {
        $this->actingAs($this->owner)->putJson("{$this->builder}/logic", ['rules' => $rules])->assertOk();
    }

    private function start(): string
    {
        $this->survey->update(['status' => SurveyStatus::ACTIVE]);
        $response = $this->actingAs($this->respondent)->postJson("/api/v1/respondent/surveys/{$this->survey->id}/participate");
        $response->assertCreated();

        return '/api/v1/respondent/participations/'.$response->json('participation_id');
    }

    private function save(string $participation, int $pageIndex, mixed $value): TestResponse
    {
        return $this->putJson("{$participation}/pages/{$this->pages[$pageIndex]->id}", ['answers' => [$this->questions[$pageIndex]->id => $value]]);
    }

    public function test_builder_can_read_replace_and_clear_rules(): void
    {
        $this->rules([$this->jumpRule(), $this->visibilityRule()]);
        $this->getJson("{$this->builder}/logic")->assertOk()->assertJsonCount(2, 'rules')->assertJsonPath('rules.0.action', 'show_question');
        $this->rules([]);
        $this->getJson("{$this->builder}/logic")->assertJsonCount(0, 'rules');
    }

    public function test_logic_is_authorized_scoped_and_locked_after_submission(): void
    {
        $this->actingAs($this->respondent)->getJson("{$this->builder}/logic")->assertForbidden();
        $this->putJson("{$this->builder}/logic", ['rules' => []])->assertForbidden();
        $otherOrganization = (new CreateOrganizationAction)->execute('Other Org', $this->owner);
        $this->actingAs($this->owner)->getJson("/api/v1/business/organizations/{$otherOrganization->id}/surveys/{$this->survey->id}/logic")->assertNotFound();
        $this->survey->update(['status' => SurveyStatus::PENDING_REVIEW]);
        $this->putJson("{$this->builder}/logic", ['rules' => []])->assertUnprocessable()->assertJsonPath('error.code', 'SURVEY_NOT_EDITABLE');
    }

    public function test_bad_references_backward_jumps_and_unsupported_operators_are_rejected_atomically(): void
    {
        $this->rules([$this->jumpRule()]);
        $invalidRules = [];
        $invalidRules[] = array_replace($this->jumpRule(), ['target_page_id' => $this->pages[0]->id]);
        $invalidRules[] = array_replace($this->jumpRule(), ['target_page_id' => 999999]);
        $invalidRules[] = array_replace($this->jumpRule(), ['conditions' => [['question_id' => 999999, 'operator' => 'equals', 'value' => 1]]]);
        $invalidRules[] = array_replace($this->jumpRule(), ['conditions' => [array_replace($this->condition(), ['operator' => 'greater_than'])]]);
        $invalidRules[] = array_replace($this->jumpRule(), ['conditions' => [array_replace($this->condition(), ['value' => 999999])]]);
        $invalidRules[] = array_replace($this->visibilityRule(), ['target_question_id' => $this->questions[0]->id]);
        foreach ($invalidRules as $rule) {
            $this->putJson("{$this->builder}/logic", ['rules' => [$rule]])->assertUnprocessable()->assertJsonPath('error.code', 'INVALID_SURVEY_LOGIC');
            $this->assertDatabaseCount('survey_logic_rules', 1);
        }
        $this->putJson("{$this->builder}/logic", ['rules' => [$this->visibilityRule(), $this->visibilityRule()]])->assertUnprocessable();
        $this->putJson("{$this->builder}/logic", ['rules' => [array_replace($this->jumpRule(), ['match' => 'invalid'])]])->assertUnprocessable();
    }

    public function test_branch_skips_required_pages_and_back_uses_actual_history(): void
    {
        $this->rules([$this->jumpRule()]);
        $participation = $this->start();
        $this->save($participation, 0, $this->options[1]->id)->assertOk()->assertJsonPath('next.id', $this->pages[2]->id);
        $this->postJson("{$participation}/back")->assertOk()->assertJsonPath('current_step.page.id', $this->pages[0]->id);
        $this->save($participation, 0, $this->options[1]->id)->assertOk();
        $this->save($participation, 2, 5)->assertOk();
        $this->postJson("{$participation}/complete")->assertOk()->assertJsonPath('completed', true);
        $this->postJson("{$participation}/complete")->assertOk();
        $this->postJson("{$participation}/back")->assertUnprocessable()->assertJsonPath('error.code', 'PARTICIPATION_ALREADY_COMPLETED');
    }

    public function test_hidden_required_question_is_not_returned_validated_or_persisted(): void
    {
        $this->rules([$this->visibilityRule()]);
        $participation = $this->start();
        $this->save($participation, 0, $this->options[1]->id)->assertOk()->assertJsonCount(0, 'next_step.page.questions');
        $this->getJson($participation)->assertOk()->assertJsonCount(0, 'current_step.page.questions');
        $this->save($participation, 1, 'Injected hidden answer')->assertOk();
        $this->assertDatabaseMissing('answers', ['question_id' => $this->questions[1]->id]);
        $this->save($participation, 2, 4)->assertOk();
        $this->postJson("{$participation}/complete")->assertOk();
    }

    public function test_visible_required_question_still_blocks_progress_and_completion(): void
    {
        $this->rules([$this->visibilityRule()]);
        $participation = $this->start();
        $this->save($participation, 0, $this->options[0]->id)->assertOk()->assertJsonCount(1, 'next_step.page.questions');
        $this->putJson("{$participation}/pages/{$this->pages[1]->id}", ['answers' => []])->assertUnprocessable()->assertJsonPath('error.code', 'INVALID_ANSWER');
        $this->postJson("{$participation}/complete")->assertUnprocessable();
        $this->save($participation, 2, 5)->assertUnprocessable()->assertJsonPath('error.code', 'INVALID_PAGE_FLOW');
    }

    public function test_unvisited_and_skipped_pages_are_rejected_before_answer_validation(): void
    {
        $this->rules([$this->jumpRule()]);
        $participation = $this->start();
        $this->putJson("{$participation}/pages/{$this->pages[2]->id}", ['answers' => []])
            ->assertUnprocessable()->assertJsonPath('error.code', 'INVALID_PAGE_FLOW');
        $this->save($participation, 0, $this->options[1]->id)->assertOk();
        $this->putJson("{$participation}/pages/{$this->pages[1]->id}", ['answers' => []])
            ->assertUnprocessable()->assertJsonPath('error.code', 'INVALID_PAGE_FLOW');
        $this->assertDatabaseCount('answers', 1);
        $this->assertDatabaseCount('answer_options', 1);
    }

    public function test_changing_an_earlier_answer_removes_downstream_answers_from_results(): void
    {
        $this->rules([$this->jumpRule()]);
        $participation = $this->start();
        $this->save($participation, 0, $this->options[0]->id)->assertOk();
        $this->save($participation, 1, 'Obsolete answer')->assertOk();
        $this->postJson("{$participation}/back")->assertOk();
        $this->postJson("{$participation}/back")->assertOk();
        $this->save($participation, 0, $this->options[1]->id)->assertOk();
        $this->assertDatabaseMissing('answers', ['question_id' => $this->questions[1]->id]);
        $this->save($participation, 2, 3)->assertOk();
        $this->postJson("{$participation}/complete")->assertOk();
        $this->actingAs($this->owner)->getJson("{$this->builder}/results/questions/{$this->questions[1]->id}")->assertOk()->assertDontSee('Obsolete answer');
        $this->get("{$this->builder}/results/export")->assertOk()->assertDontSee('Obsolete answer');
    }

    public function test_early_completion_priority_and_or_conditions(): void
    {
        $complete = [
            'action' => 'complete', 'source_page_id' => $this->pages[0]->id,
            'priority' => 0, 'match' => 'any', 'conditions' => [$this->condition(), $this->condition(1)],
        ];
        $this->rules([$this->jumpRule(), $complete]);
        $participation = $this->start();
        $this->save($participation, 0, $this->options[1]->id)->assertOk()->assertJsonPath('next.type', 'completed')->assertJsonPath('can_complete', true);
        $this->postJson("{$participation}/complete")->assertOk();
    }

    public function test_and_requires_every_condition(): void
    {
        $rule = $this->jumpRule();
        $rule['conditions'] = [$this->condition(), $this->condition(1)];
        $this->rules([$rule]);
        $participation = $this->start();
        $this->save($participation, 0, $this->options[0]->id)->assertOk()->assertJsonPath('next.id', $this->pages[1]->id);
    }

    public function test_preview_uses_same_rules_without_participations_or_answers(): void
    {
        $this->rules([$this->jumpRule(), $this->visibilityRule()]);
        $this->postJson("{$this->builder}/preview/pages/{$this->pages[0]->id}/validate", ['answers' => [$this->questions[0]->id => $this->options[1]->id]])
            ->assertOk()->assertJsonPath('current_step.next.id', $this->pages[2]->id);
        $this->postJson("{$this->builder}/preview/pages/{$this->pages[2]->id}/validate", [
            'previous_answers' => [$this->questions[0]->id => $this->options[1]->id],
            'answers' => [$this->questions[2]->id => 4],
        ])->assertOk()->assertJsonPath('current_step.next.type', 'completed');
        $this->assertDatabaseCount('participations', 0);
        $this->assertDatabaseCount('answers', 0);
    }

    public function test_deleted_references_and_reordered_pages_block_review_and_preview(): void
    {
        $this->rules([$this->jumpRule()]);
        $this->pages[2]->update(['position' => 0]);
        $this->postJson("{$this->builder}/submit-review")->assertUnprocessable()->assertJsonPath('error.code', 'INVALID_SURVEY_LOGIC');
        $this->pages[2]->update(['position' => 3]);
        $this->options[1]->delete();
        SurveyQuestionOption::create(['question_id' => $this->questions[0]->id, 'position' => 3, 'label' => ['uz' => 'Other']]);
        $this->postJson("{$this->builder}/submit-review")->assertUnprocessable()->assertJsonPath('error.code', 'INVALID_SURVEY_LOGIC');
        $this->getJson("{$this->builder}/preview")->assertUnprocessable()->assertJsonPath('error.code', 'INVALID_SURVEY_LOGIC');
        $this->survey->update(['status' => SurveyStatus::APPROVED]);
        $this->postJson("{$this->builder}/publish")->assertUnprocessable()->assertJsonPath('error.code', 'INVALID_SURVEY_LOGIC');
    }

    public function test_back_checks_ownership_and_first_page_is_no_op(): void
    {
        $participation = $this->start();
        $this->postJson("{$participation}/back")->assertOk()->assertJsonPath('current_step.page.id', $this->pages[0]->id);
        $this->actingAs($this->owner)->postJson("{$participation}/back")->assertForbidden();
    }

    public function test_rating_rule_matches_in_preview_and_persisted_flow(): void
    {
        $this->questions[0]->update(['type' => QuestionType::RATING, 'settings' => ['min' => 1, 'max' => 5]]);
        $rule = $this->jumpRule();
        $rule['conditions'] = [['question_id' => $this->questions[0]->id, 'operator' => 'equals', 'value' => 5]];
        $this->rules([$rule]);
        $this->postJson("{$this->builder}/preview/pages/{$this->pages[0]->id}/validate", ['answers' => [$this->questions[0]->id => '5.0']])
            ->assertOk()->assertJsonPath('current_step.next.id', $this->pages[2]->id);
        $participation = $this->start();
        $this->save($participation, 0, 5.0)->assertOk()->assertJsonPath('next.id', $this->pages[2]->id);
    }

    public function test_multiple_choice_and_text_conditions_use_question_specific_values(): void
    {
        $this->questions[0]->update(['type' => QuestionType::MULTIPLE_CHOICE]);
        $rule = $this->jumpRule();
        $rule['conditions'] = [array_replace($this->condition(1), ['operator' => 'contains'])];
        $this->rules([$rule]);
        $participation = $this->start();
        $this->save($participation, 0, [$this->options[1]->id])->assertOk()->assertJsonPath('next.id', $this->pages[2]->id);

        $this->actingAs($this->owner);
        $this->survey->update(['status' => SurveyStatus::DRAFT]);
        $this->questions[0]->update(['type' => QuestionType::SHORT_TEXT]);
        $rule['conditions'] = [['question_id' => $this->questions[0]->id, 'operator' => 'contains', 'value' => 'skip']];
        $this->rules([$rule]);
        $this->respondent = User::factory()->create();
        $participation = $this->start();
        $this->save($participation, 0, 'please skip')->assertOk()->assertJsonPath('next.id', $this->pages[2]->id);
    }

    public function test_foreign_questions_options_and_future_visibility_references_are_rejected(): void
    {
        $otherSurvey = $this->survey->replicate();
        $otherSurvey->save();
        $foreignPage = SurveyPage::create(['survey_id' => $otherSurvey->id, 'position' => 1]);
        $foreignQuestion = SurveyQuestion::create([
            'page_id' => $foreignPage->id, 'type' => QuestionType::SINGLE_CHOICE,
            'title' => ['uz' => 'Foreign'], 'position' => 1,
        ]);
        $foreignOption = SurveyQuestionOption::create(['question_id' => $foreignQuestion->id, 'label' => ['uz' => 'Foreign'], 'position' => 1]);
        $this->actingAs($this->owner);
        foreach ([
            array_replace($this->jumpRule(), ['target_page_id' => $foreignPage->id]),
            array_replace($this->jumpRule(), ['conditions' => [['question_id' => $foreignQuestion->id, 'operator' => 'equals', 'value' => $foreignOption->id]]]),
            array_replace($this->jumpRule(), ['conditions' => [array_replace($this->condition(), ['value' => $foreignOption->id])]]),
            array_replace($this->visibilityRule(), ['conditions' => [['question_id' => $this->questions[2]->id, 'operator' => 'answered']]]),
        ] as $rule) {
            $this->putJson("{$this->builder}/logic", ['rules' => [$rule]])->assertUnprocessable()->assertJsonPath('error.code', 'INVALID_SURVEY_LOGIC');
        }
    }

    public function test_resume_retains_history_and_back_rejects_closed_surveys(): void
    {
        $this->rules([$this->jumpRule()]);
        $participation = $this->start();
        $this->save($participation, 0, $this->options[1]->id)->assertOk();
        $this->postJson("/api/v1/respondent/surveys/{$this->survey->id}/participate")->assertCreated()->assertJsonPath('current_step.page.id', $this->pages[2]->id);
        $this->postJson("{$participation}/back")->assertOk()->assertJsonPath('current_step.page.id', $this->pages[0]->id);
        $this->survey->update(['status' => SurveyStatus::CLOSED]);
        $this->postJson("{$participation}/back")->assertUnprocessable()->assertJsonPath('error.code', 'SURVEY_NOT_ACTIVE');
    }

    public function test_visibility_change_removes_an_existing_answer_without_skipping_its_page(): void
    {
        $this->rules([$this->visibilityRule()]);
        $participation = $this->start();
        $this->save($participation, 0, $this->options[0]->id)->assertOk();
        $this->save($participation, 1, 'Previously visible')->assertOk();
        $this->postJson("{$participation}/back")->assertOk();
        $this->postJson("{$participation}/back")->assertOk();
        $this->save($participation, 0, $this->options[1]->id)->assertOk()->assertJsonCount(0, 'next_step.page.questions');
        $this->assertDatabaseMissing('answers', ['question_id' => $this->questions[1]->id]);
        $this->putJson("{$participation}/pages/{$this->pages[1]->id}", ['answers' => []])->assertOk();
        $this->save($participation, 2, 2)->assertOk();
        $this->postJson("{$participation}/complete")->assertOk();
    }

    public function test_rule_limits_and_preview_context_validation(): void
    {
        $this->actingAs($this->owner);
        $this->putJson("{$this->builder}/logic", ['rules' => array_fill(0, 101, $this->jumpRule())])->assertUnprocessable()->assertJsonPath('error.code', 'VALIDATION_ERROR');
        $rule = $this->jumpRule();
        $rule['conditions'] = array_fill(0, 21, $this->condition());
        $this->putJson("{$this->builder}/logic", ['rules' => [$rule]])->assertUnprocessable()->assertJsonPath('error.code', 'VALIDATION_ERROR');
        $this->postJson("{$this->builder}/preview/pages/{$this->pages[0]->id}/validate", [
            'previous_answers' => [$this->questions[2]->id => 5], 'answers' => [$this->questions[0]->id => $this->options[0]->id],
        ])->assertUnprocessable()->assertJsonPath('error.code', 'INVALID_PAGE_FLOW');
        $this->postJson("{$this->builder}/preview/pages/{$this->pages[0]->id}/validate", ['answers' => 'malformed'])->assertUnprocessable()->assertJsonPath('error.code', 'VALIDATION_ERROR');
    }
}
