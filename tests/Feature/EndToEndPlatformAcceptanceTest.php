<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Survey\Enums\QuestionType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class EndToEndPlatformAcceptanceTest extends TestCase
{
    use RefreshDatabase;

    public function test_full_platform_end_to_end_acceptance_flow(): void
    {
        // ---------------------------------------------------------
        // 1. Identity & Organization: Register business user & create org
        // ---------------------------------------------------------
        $businessRegister = $this->postJson('/api/v1/auth/register', [
            'name' => 'Dilshod Biznes',
            'email' => 'dilshod@company.uz',
            'password' => 'SecurePass123!',
            'password_confirmation' => 'SecurePass123!',
            'locale' => 'uz',
        ]);
        $businessRegister->assertStatus(201);
        $businessUser = User::where('email', 'dilshod@company.uz')->firstOrFail();

        Sanctum::actingAs($businessUser);

        $createOrgResponse = $this->postJson('/api/v1/business/organizations', [
            'name' => 'Tashkent Market Research LLC',
            'inn' => '123456789',
        ]);
        $createOrgResponse->assertStatus(201);
        $orgId = $createOrgResponse->json('organization.id');

        // ---------------------------------------------------------
        // 2. Survey Builder: Create multi-lingual survey (uz, ru)
        // ---------------------------------------------------------
        $createSurveyResponse = $this->postJson("/api/v1/business/organizations/{$orgId}/surveys", [
            'default_locale' => 'uz',
            'locales' => ['uz', 'ru'],
            'title' => [
                'uz' => 'Mijozlar ehtiyoji so‘rovi',
                'ru' => 'Опрос потребностей клиентов',
            ],
            'welcome_message' => [
                'uz' => 'Assalomu alaykum!',
                'ru' => 'Здравствуйте!',
            ],
            'thank_you_message' => [
                'uz' => 'Rahmat!',
                'ru' => 'Спасибо!',
            ],
        ]);
        $createSurveyResponse->assertStatus(201);
        $surveyId = $createSurveyResponse->json('survey.id');

        // Page 1 is auto-created. Let's add Question 1 (Single choice) on Page 1
        $surveyData = $createSurveyResponse->json('survey');
        $page1Id = $surveyData['pages'][0]['id'];

        $q1Response = $this->postJson("/api/v1/business/organizations/{$orgId}/pages/{$page1Id}/questions", [
            'type' => QuestionType::SINGLE_CHOICE->value,
            'title' => [
                'uz' => 'Qaysi transportdan foydalanasiz?',
                'ru' => 'Каким транспортом вы пользуетесь?',
            ],
            'is_required' => true,
        ]);
        $q1Response->assertStatus(201);
        $q1Id = $q1Response->json('question.id');

        // Options for Q1
        $opt1Response = $this->postJson("/api/v1/business/organizations/{$orgId}/questions/{$q1Id}/options", [
            'label' => ['uz' => 'Metro', 'ru' => 'Метро'],
        ]);
        $opt1Id = $opt1Response->json('option.id');

        $opt2Response = $this->postJson("/api/v1/business/organizations/{$orgId}/questions/{$q1Id}/options", [
            'label' => ['uz' => 'Avtobus', 'ru' => 'Автоbus'],
        ]);
        $opt2Id = $opt2Response->json('option.id');

        // Add Page 2 and Question 2 (Rating)
        $page2Response = $this->postJson("/api/v1/business/organizations/{$orgId}/surveys/{$surveyId}/pages", [
            'title' => ['uz' => '2-sahifa', 'ru' => 'Страница 2'],
        ]);
        $page2Id = $page2Response->json('page.id');

        $q2Response = $this->postJson("/api/v1/business/organizations/{$orgId}/pages/{$page2Id}/questions", [
            'type' => QuestionType::RATING->value,
            'title' => [
                'uz' => 'Xizmat sifatini baholang',
                'ru' => 'Оцените качество обслуживания',
            ],
            'is_required' => true,
            'settings' => ['min' => 1, 'max' => 5],
        ]);
        $q2Id = $q2Response->json('question.id');

        // ---------------------------------------------------------
        // 3. Preview: Preview in Russian
        // ---------------------------------------------------------
        $previewResponse = $this->getJson("/api/v1/business/organizations/{$orgId}/surveys/{$surveyId}/preview?locale=ru");
        $previewResponse->assertStatus(200)
            ->assertJsonPath('survey.title', 'Опрос потребностей клиентов')
            ->assertJsonPath('current_step.progress.total_steps', 2);

        // ---------------------------------------------------------
        // 4. Moderation Flow: Submit, Admin Review, Approval
        // ---------------------------------------------------------
        // Submit survey for review
        $submitResponse = $this->postJson("/api/v1/business/organizations/{$orgId}/surveys/{$surveyId}/submit-review");
        $submitResponse->assertStatus(200)
            ->assertJsonPath('survey.status', 'pending_review');

        // Admin approves survey
        $admin = User::factory()->admin()->create();
        Sanctum::actingAs($admin);

        $approveResponse = $this->postJson("/api/v1/admin/moderation/surveys/{$surveyId}/approve");
        $approveResponse->assertStatus(200)
            ->assertJsonPath('survey.status', 'approved');

        // Business publishes survey
        Sanctum::actingAs($businessUser);
        $publishResponse = $this->postJson("/api/v1/business/organizations/{$orgId}/surveys/{$surveyId}/publish");
        $publishResponse->assertStatus(200)
            ->assertJsonPath('survey.status', 'active');

        // ---------------------------------------------------------
        // 5. Respondent Participation: 2 respondents submit answers
        // ---------------------------------------------------------
        $respondent1 = User::factory()->create();
        $respondent2 = User::factory()->create();

        // Respondent 1 (Uzbek: Metro, rating 5)
        Sanctum::actingAs($respondent1);
        $r1Start = $this->postJson("/api/v1/respondent/surveys/{$surveyId}/participate", ['locale' => 'uz']);
        $r1Start->assertStatus(201);
        $r1PartId = $r1Start->json('participation_id');

        $r1Page1 = $this->putJson("/api/v1/respondent/participations/{$r1PartId}/pages/{$page1Id}", [
            'answers' => [$q1Id => $opt1Id],
        ]);
        $r1Page1->assertStatus(200);

        $r1Page2 = $this->putJson("/api/v1/respondent/participations/{$r1PartId}/pages/{$page2Id}", [
            'answers' => [$q2Id => 5],
        ]);
        $r1Page2->assertStatus(200);

        $r1Complete = $this->postJson("/api/v1/respondent/participations/{$r1PartId}/complete");
        $r1Complete->assertStatus(200)
            ->assertJsonPath('completed', true);

        // Respondent 2 (Russian: Avtobus, rating 4)
        Sanctum::actingAs($respondent2);
        $r2Start = $this->postJson("/api/v1/respondent/surveys/{$surveyId}/participate", ['locale' => 'ru']);
        $r2Start->assertStatus(201);
        $r2PartId = $r2Start->json('participation_id');

        $this->putJson("/api/v1/respondent/participations/{$r2PartId}/pages/{$page1Id}", [
            'answers' => [$q1Id => $opt2Id],
        ])->assertStatus(200);

        $this->putJson("/api/v1/respondent/participations/{$r2PartId}/pages/{$page2Id}", [
            'answers' => [$q2Id => 4],
        ])->assertStatus(200);

        $this->postJson("/api/v1/respondent/participations/{$r2PartId}/complete")
            ->assertStatus(200)
            ->assertJsonPath('completed', true);

        // ---------------------------------------------------------
        // 6. Results & Export
        // ---------------------------------------------------------
        Sanctum::actingAs($businessUser);

        $summaryResp = $this->getJson("/api/v1/business/organizations/{$orgId}/surveys/{$surveyId}/results/summary");
        $summaryResp->assertStatus(200)
            ->assertJsonPath('started_count', 2)
            ->assertJsonPath('completed_count', 2)
            ->assertJsonPath('in_progress_count', 0);

        // Aggregate for Q1 (Transport)
        $q1Aggregate = $this->getJson("/api/v1/business/organizations/{$orgId}/surveys/{$surveyId}/results/questions/{$q1Id}");
        $q1Aggregate->assertStatus(200)
            ->assertJsonPath('total_responses', 2);

        // Aggregate for Q2 (Rating: avg should be 4.5)
        $q2Aggregate = $this->getJson("/api/v1/business/organizations/{$orgId}/surveys/{$surveyId}/results/questions/{$q2Id}");
        $q2Aggregate->assertStatus(200)
            ->assertJsonPath('average', 4.5);

        // Export CSV
        $exportResp = $this->get("/api/v1/business/organizations/{$orgId}/surveys/{$surveyId}/results/export");
        $exportResp->assertStatus(200);
        $csvText = $exportResp->getContent();
        $this->assertIsString($csvText);
        $this->assertStringContainsString('Participation ID', $csvText);
        $this->assertStringContainsString('Metro', $csvText);

        // ---------------------------------------------------------
        // 7. Close Survey
        // ---------------------------------------------------------
        $closeResp = $this->postJson("/api/v1/business/organizations/{$orgId}/surveys/{$surveyId}/close");
        $closeResp->assertStatus(200)
            ->assertJsonPath('survey.status', 'closed');
    }
}
