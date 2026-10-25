<?php

namespace Database\Seeders;

use App\Models\User;
use App\Modules\Identity\Enums\PlatformLocale;
use App\Modules\Identity\Enums\PlatformRole;
use App\Modules\Identity\Enums\UserStatus;
use App\Modules\Moderation\Enums\ModerationDecision;
use App\Modules\Moderation\Models\SurveyModeration;
use App\Modules\Organization\Enums\OrganizationRole;
use App\Modules\Organization\Enums\OrganizationStatus;
use App\Modules\Organization\Models\Organization;
use App\Modules\Organization\Models\OrganizationMember;
use App\Modules\Participation\Enums\ParticipationStatus;
use App\Modules\Participation\Models\Answer;
use App\Modules\Participation\Models\AnswerOption;
use App\Modules\Participation\Models\Participation;
use App\Modules\Survey\Enums\QuestionType;
use App\Modules\Survey\Enums\SurveyStatus;
use App\Modules\Survey\Models\Survey;
use App\Modules\Survey\Models\SurveyPage;
use App\Modules\Survey\Models\SurveyQuestion;
use App\Modules\Survey\Models\SurveyQuestionOption;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed predictable demo/test data for frontend integration.
     */
    public function run(): void
    {
        // 1. Admin User
        $admin = User::firstOrCreate(
            ['email' => 'admin@dialog.uz'],
            [
                'name' => 'Platform Administrator',
                'password' => Hash::make('Secret123!'),
                'locale' => PlatformLocale::UZ,
                'platform_role' => PlatformRole::ADMIN,
                'status' => UserStatus::ACTIVE,
            ]
        );

        // 2. Business User (Organization Owner)
        $owner = User::firstOrCreate(
            ['email' => 'owner@dialog.uz'],
            [
                'name' => 'Dilshod Karimov',
                'password' => Hash::make('Secret123!'),
                'locale' => PlatformLocale::UZ,
                'platform_role' => PlatformRole::USER,
                'status' => UserStatus::ACTIVE,
            ]
        );

        // 3. Respondent User
        $respondent = User::firstOrCreate(
            ['email' => 'respondent@dialog.uz'],
            [
                'name' => 'Anvar Alimov',
                'password' => Hash::make('Secret123!'),
                'locale' => PlatformLocale::UZ,
                'platform_role' => PlatformRole::USER,
                'status' => UserStatus::ACTIVE,
            ]
        );

        // 4. Demo Organization
        $org = Organization::firstOrCreate(
            ['name' => 'Dialog Analytics Group'],
            [
                'inn' => '123456789',
                'created_by' => $owner->id,
                'status' => OrganizationStatus::ACTIVE,
            ]
        );

        OrganizationMember::firstOrCreate(
            ['organization_id' => $org->id, 'user_id' => $owner->id],
            ['role' => OrganizationRole::OWNER, 'joined_at' => now()]
        );

        // 5. Demo Active Multilingual Survey
        $survey = Survey::firstOrCreate(
            ['organization_id' => $org->id, 'status' => SurveyStatus::ACTIVE],
            [
                'created_by' => $owner->id,
                'default_locale' => 'uz',
                'locales' => ['uz', 'ru', 'en'],
                'title' => [
                    'uz' => 'Mijozlar qoniqishi so‘rovi',
                    'ru' => 'Опрос удовлетворенности клиентов',
                    'en' => 'Customer Satisfaction Survey',
                ],
                'description' => [
                    'uz' => 'Xizmatlarimiz sifatini oshirish bo‘yicha fikringiz muhim',
                    'ru' => 'Ваше мнение важно для улучшения качества услуг',
                    'en' => 'Your feedback helps us improve our service quality',
                ],
                'welcome_message' => [
                    'uz' => 'Assalomu alaykum! So‘rovnomaga xush kelibsiz.',
                    'ru' => 'Здравствуйте! Добро пожаловать в наш опрос.',
                    'en' => 'Hello and welcome to our satisfaction survey.',
                ],
                'thank_you_message' => [
                    'uz' => 'Fikringiz uchun tashakkur!',
                    'ru' => 'Благодарим за ваше время и ответы!',
                    'en' => 'Thank you for taking the time to share your feedback!',
                ],
                'published_at' => now()->subDay(),
            ]
        );

        // Page 1
        $page1 = SurveyPage::firstOrCreate(
            ['survey_id' => $survey->id, 'position' => 1],
            [
                'title' => [
                    'uz' => 'Xizmatlardan foydalanish',
                    'ru' => 'Использование сервисов',
                    'en' => 'Service Usage',
                ],
            ]
        );

        // Question 1: Single choice
        $q1 = SurveyQuestion::firstOrCreate(
            ['page_id' => $page1->id, 'position' => 1],
            [
                'type' => QuestionType::SINGLE_CHOICE,
                'title' => [
                    'uz' => 'Xizmatimizdan qanchalik tez-tez foydalanasiz?',
                    'ru' => 'Как часто вы пользуетесь нашим сервисом?',
                    'en' => 'How often do you use our service?',
                ],
                'is_required' => true,
            ]
        );

        $opt1 = SurveyQuestionOption::firstOrCreate(
            ['question_id' => $q1->id, 'position' => 1],
            ['label' => ['uz' => 'Har kuni', 'ru' => 'Каждый день', 'en' => 'Daily']]
        );

        $opt2 = SurveyQuestionOption::firstOrCreate(
            ['question_id' => $q1->id, 'position' => 2],
            ['label' => ['uz' => 'Haftada bir necha marta', 'ru' => 'Несколько раз в неделю', 'en' => 'Several times a week']]
        );

        // Page 2
        $page2 = SurveyPage::firstOrCreate(
            ['survey_id' => $survey->id, 'position' => 2],
            [
                'title' => [
                    'uz' => 'Baholash',
                    'ru' => 'Оценка',
                    'en' => 'Rating & Comments',
                ],
            ]
        );

        // Question 2: Rating
        $q2 = SurveyQuestion::firstOrCreate(
            ['page_id' => $page2->id, 'position' => 1],
            [
                'type' => QuestionType::RATING,
                'title' => [
                    'uz' => 'Xizmat ko‘rsatish sifatini baholang (1-5)',
                    'ru' => 'Оцените качество обслуживания (1-5)',
                    'en' => 'Rate our overall service quality (1-5)',
                ],
                'is_required' => true,
                'settings' => ['min' => 1, 'max' => 5],
            ]
        );

        // Moderation record
        SurveyModeration::firstOrCreate(
            ['survey_id' => $survey->id, 'decision' => ModerationDecision::APPROVED],
            [
                'moderator_id' => $admin->id,
                'comment' => 'Tasdiqlandi. Standartlarga javob beradi.',
                'decided_at' => now()->subDay(),
            ]
        );

        // 6. Demo Completed Participation for Respondent
        $participation = Participation::firstOrCreate(
            ['survey_id' => $survey->id, 'user_id' => $respondent->id],
            [
                'locale' => 'uz',
                'status' => ParticipationStatus::COMPLETED,
                'current_page_id' => $page2->id,
                'started_at' => now()->subHours(2),
                'completed_at' => now()->subHour(),
                'last_activity_at' => now()->subHour(),
            ]
        );

        $ans1 = Answer::firstOrCreate(
            ['participation_id' => $participation->id, 'question_id' => $q1->id],
            ['text_value' => 'Har kuni']
        );
        AnswerOption::firstOrCreate(
            ['answer_id' => $ans1->id, 'option_id' => $opt1->id]
        );

        Answer::firstOrCreate(
            ['participation_id' => $participation->id, 'question_id' => $q2->id],
            ['number_value' => 5]
        );

        // 7. Demo Draft Survey for Builder testing
        Survey::firstOrCreate(
            ['organization_id' => $org->id, 'status' => SurveyStatus::DRAFT],
            [
                'created_by' => $owner->id,
                'default_locale' => 'uz',
                'locales' => ['uz', 'ru'],
                'title' => [
                    'uz' => 'Yangi mahsulot bo‘yicha fikrlar (Qoralama)',
                    'ru' => 'Отзывы о новом продукте (Черновик)',
                ],
            ]
        );
    }
}
