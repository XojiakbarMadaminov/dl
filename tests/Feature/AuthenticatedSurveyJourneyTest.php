<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Participation\Models\Participation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class AuthenticatedSurveyJourneyTest extends TestCase
{
    use RefreshDatabase;

    private function api(string $method, string $url, array $data = [], ?string $token = null): TestResponse
    {
        // Each request authenticates its actual bearer token, like a separate HTTP request.
        app('auth')->forgetGuards();

        return $this->json($method, $url, $data, $token === null ? [] : ['Authorization' => "Bearer {$token}"]);
    }

    private function registerAndLogin(string $email, ?string $organizationName = null): string
    {
        $registration = $this->api('POST', '/api/v1/auth/register', [
            'name' => 'API User', 'email' => $email, 'password' => 'SecurePass123!',
            'password_confirmation' => 'SecurePass123!', 'locale' => 'en',
            'organization_name' => $organizationName,
        ])->assertCreated()->assertJsonMissingPath('user.password');
        $token = $registration->json('token');
        $this->api('GET', '/api/v1/auth/me', token: $token)->assertOk()->assertJsonPath('user.email', $email);
        $this->api('POST', '/api/v1/auth/logout', token: $token)->assertOk();
        $this->api('GET', '/api/v1/auth/me', token: $token)->assertUnauthorized();

        return $this->api('POST', '/api/v1/auth/login', [
            'email' => strtoupper($email), 'password' => 'SecurePass123!', 'device_name' => 'Acceptance test',
        ])->assertOk()->json('token');
    }

    public function test_real_tokens_cover_builder_logic_preview_moderation_and_respondent_paths(): void
    {
        $businessToken = $this->registerAndLogin('owner@example.com', 'Journey Organization');
        $organizationId = $this->api('GET', '/api/v1/business/organizations', token: $businessToken)
            ->assertOk()->json('data.0.id');
        $base = "/api/v1/business/organizations/{$organizationId}";
        $translations = fn (string $text) => ['uz' => "UZ {$text}", 'ru' => "RU {$text}", 'en' => "EN {$text}"];
        $survey = $this->api('POST', "{$base}/surveys", [
            'default_locale' => 'uz', 'locales' => ['uz', 'ru', 'en'],
            'title' => $translations('Survey'), 'thank_you_message' => $translations('Thanks'),
        ], $businessToken)->assertCreated()->assertJsonPath('survey.title.en', 'EN Survey');
        $surveyId = $survey->json('survey.id');
        $builder = "{$base}/surveys/{$surveyId}";
        $pages = [$survey->json('survey.pages.0.id')];
        foreach ([2, 3] as $index) {
            $pages[] = $this->api('POST', "{$builder}/pages", ['title' => $translations("Page {$index}")], $businessToken)
                ->assertCreated()->json('page.id');
        }
        $questions = [];
        foreach ([
            [$pages[0], 'single_choice', []],
            [$pages[0], 'multiple_choice', ['min_selections' => 1, 'max_selections' => 2]],
            [$pages[1], 'short_text', ['max_length' => 10]],
            [$pages[1], 'long_text', ['max_length' => 50]],
            [$pages[2], 'rating', ['min' => 1, 'max' => 5, 'min_label' => $translations('Low'), 'max_label' => $translations('High')]],
        ] as [$pageId, $type, $settings]) {
            $questions[] = $this->api('POST', "{$base}/pages/{$pageId}/questions", [
                'type' => $type, 'title' => $translations($type), 'is_required' => true, 'settings' => $settings,
            ], $businessToken)->assertCreated()->json('question.id');
        }
        $options = [];
        foreach ([0 => ['full', 'skip', 'finish'], 1 => ['continue', 'stop']] as $questionIndex => $labels) {
            foreach ($labels as $label) {
                $options[$label] = $this->api('POST', "{$base}/questions/{$questions[$questionIndex]}/options", [
                    'label' => $translations($label),
                ], $businessToken)->assertCreated()->json('option.id');
            }
        }
        $condition = fn (string $label) => ['question_id' => $questions[0], 'operator' => 'equals', 'value' => $options[$label]];
        $this->api('PUT', "{$builder}/logic", ['rules' => [
            ['action' => 'show_question', 'target_question_id' => $questions[2], 'priority' => 0, 'match' => 'all',
                'conditions' => [$condition('full'), ['question_id' => $questions[1], 'operator' => 'contains', 'value' => $options['continue']]]],
            ['action' => 'skip_to_page', 'source_page_id' => $pages[0], 'target_page_id' => $pages[2], 'priority' => 10,
                'match' => 'all', 'conditions' => [$condition('skip')]],
            ['action' => 'complete', 'source_page_id' => $pages[0], 'priority' => 0, 'match' => 'any',
                'conditions' => [$condition('finish'), ['question_id' => $questions[1], 'operator' => 'contains', 'value' => $options['stop']]]],
        ]], $businessToken)->assertOk();
        $this->api('GET', "{$builder}/logic", token: $businessToken)->assertOk()->assertJsonCount(3, 'rules');
        $fullAnswers = [$questions[0] => $options['full'], $questions[1] => [$options['continue']]];
        $textAnswers = [$questions[2] => 'Helpful', $questions[3] => 'Detailed feedback'];
        $this->api('GET', "{$builder}/preview?locale=ru", token: $businessToken)
            ->assertOk()->assertJsonPath('survey.title', 'RU Survey');
        $this->api('POST', "{$builder}/preview/pages/{$pages[1]}/validate", [
            'locale' => 'en', 'previous_answers' => $fullAnswers, 'answers' => $textAnswers,
        ], $businessToken)->assertOk()->assertJsonPath('current_step.next.id', $pages[2]);
        $this->assertDatabaseCount('participations', 0);
        $this->assertDatabaseCount('answers', 0);

        $this->api('POST', "{$builder}/submit-review", token: $businessToken)->assertOk();
        $this->api('POST', "/api/v1/admin/moderation/surveys/{$surveyId}/approve", token: $businessToken)->assertForbidden();
        $adminToken = User::factory()->admin()->create()->createToken('admin')->plainTextToken;
        $this->api('POST', "/api/v1/admin/moderation/surveys/{$surveyId}/approve", token: $adminToken)->assertOk();
        $this->api('POST', "{$builder}/publish", token: $businessToken)->assertOk()->assertJsonPath('survey.status', 'active');
        $this->api('PUT', "{$builder}/logic", ['rules' => []], $businessToken)
            ->assertUnprocessable()->assertJsonPath('error.code', 'SURVEY_NOT_EDITABLE');
        $this->api('POST', "/api/v1/respondent/surveys/{$surveyId}/participate")->assertUnauthorized();

        foreach (['changed_branch', 'full', 'early'] as $scenario) {
            $token = $this->registerAndLogin("{$scenario}@example.com");
            $start = $this->api('POST', "/api/v1/respondent/surveys/{$surveyId}/participate", ['locale' => 'en'], $token)
                ->assertCreated()->assertJsonPath('survey.title', 'EN Survey');
            $participationId = $start->json('participation_id');
            $participation = "/api/v1/respondent/participations/{$participationId}";
            $this->api('GET', $participation, token: $businessToken)->assertForbidden();
            $this->api('POST', "{$participation}/complete", token: $businessToken)->assertForbidden();
            $this->api('POST', "{$participation}/back", token: $businessToken)->assertForbidden();
            $this->api('PUT', "{$participation}/pages/{$pages[0]}", ['answers' => $fullAnswers], $businessToken)->assertForbidden();
            $this->api('POST', "/api/v1/respondent/surveys/{$surveyId}/participate", ['locale' => 'ru'], $token)
                ->assertCreated()->assertJsonPath('participation_id', $participationId)->assertJsonPath('locale', 'en');

            if ($scenario === 'early') {
                // Both navigation rules match; the higher-priority OR completion wins.
                $this->api('PUT', "{$participation}/pages/{$pages[0]}", ['answers' => [
                    $questions[0] => $options['skip'], $questions[1] => [$options['stop']],
                ]], $token)->assertOk()->assertJsonPath('can_complete', true)->assertJsonPath('next.type', 'completed');
            } else {
                foreach ([[], array_replace($fullAnswers, [$questions[1] => [$options['continue'], $options['continue']]])] as $invalid) {
                    $this->api('PUT', "{$participation}/pages/{$pages[0]}", ['answers' => $invalid], $token)
                        ->assertUnprocessable()->assertJsonPath('error.code', 'INVALID_ANSWER');
                }
                $this->api('PUT', "{$participation}/pages/{$pages[2]}", ['answers' => [$questions[4] => 5]], $token)
                    ->assertUnprocessable()->assertJsonPath('error.code', 'INVALID_PAGE_FLOW');
                $this->api('PUT', "{$participation}/pages/{$pages[0]}", ['answers' => $fullAnswers], $token)
                    ->assertOk()->assertJsonPath('next.id', $pages[1])->assertJsonCount(2, 'next_step.page.questions');
                $this->api('PUT', "{$participation}/pages/{$pages[1]}", ['answers' => array_replace($textAnswers, [$questions[2] => str_repeat('x', 11)])], $token)
                    ->assertUnprocessable()->assertJsonPath('error.code', 'INVALID_ANSWER');
                $this->api('PUT', "{$participation}/pages/{$pages[1]}", ['answers' => $textAnswers], $token)
                    ->assertOk()->assertJsonPath('next.id', $pages[2])->assertJsonPath('next_step.page.questions.0.settings.max_label', 'EN High');

                if ($scenario === 'changed_branch') {
                    $this->api('POST', "{$participation}/back", token: $token)->assertOk()->assertJsonPath('current_step.page.id', $pages[1]);
                    $this->api('POST', "{$participation}/back", token: $token)->assertOk()->assertJsonPath('current_step.page.id', $pages[0]);
                    $this->api('PUT', "{$participation}/pages/{$pages[0]}", ['answers' => array_replace($fullAnswers, [$questions[0] => $options['skip']])], $token)
                        ->assertOk()->assertJsonPath('next.id', $pages[2]);
                    $this->assertDatabaseMissing('answers', ['participation_id' => $participationId, 'question_id' => $questions[2]]);
                    $this->assertDatabaseMissing('answers', ['participation_id' => $participationId, 'question_id' => $questions[3]]);
                    $this->api('PUT', "{$participation}/pages/{$pages[1]}", ['answers' => $textAnswers], $token)
                        ->assertUnprocessable()->assertJsonPath('error.code', 'INVALID_PAGE_FLOW');
                }
                $this->api('GET', $participation, token: $token)->assertOk()->assertJsonPath('current_step.page.id', $pages[2])
                    ->assertJsonPath("answers.{$questions[1]}.option_ids", [$options['continue']]);
                $this->api('PUT', "{$participation}/pages/{$pages[2]}", ['answers' => [$questions[4] => 6]], $token)->assertUnprocessable();
                $this->api('PUT', "{$participation}/pages/{$pages[2]}", ['answers' => [$questions[4] => 5]], $token)
                    ->assertOk()->assertJsonPath('can_complete', true);
            }

            $this->api('POST', "{$participation}/complete", token: $token)->assertOk()
                ->assertJsonPath('completed', true)->assertJsonPath('thank_you_message', 'EN Thanks');
            $completedAt = Participation::findOrFail($participationId)->completed_at;
            $this->api('POST', "{$participation}/complete", token: $token)->assertOk();
            $this->assertEquals($completedAt, Participation::findOrFail($participationId)->completed_at);
            $this->api('PUT', "{$participation}/pages/{$pages[0]}", ['answers' => $fullAnswers], $token)
                ->assertUnprocessable()->assertJsonPath('error.code', 'PARTICIPATION_ALREADY_COMPLETED');
            $this->api('POST', "/api/v1/respondent/surveys/{$surveyId}/participate", token: $token)
                ->assertOk()->assertJsonPath('participation_id', $participationId)->assertJsonPath('completed', true);
        }

        $this->assertDatabaseCount('participations', 3);
        $this->api('GET', "{$builder}/results/summary", token: $businessToken)->assertOk()->assertJsonPath('completed_count', 3);
        $this->api('GET', "{$builder}/results/questions/{$questions[2]}", token: $businessToken)->assertOk()->assertJsonPath('total_responses', 1);
        $this->api('GET', "{$builder}/results/questions/{$questions[4]}", token: $businessToken)->assertOk()->assertJsonPath('average', 5);
        $this->api('GET', "{$builder}/results/export?status=completed", token: $businessToken)->assertOk()->assertSee('Detailed feedback');
    }
}
