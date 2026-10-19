<?php

namespace App\Modules\Moderation\Validators;

use App\Exceptions\ApiException;
use App\Modules\Survey\Models\Survey;
use App\Modules\Survey\Validators\QuestionSettingsValidator;
use App\Modules\Survey\Validators\SurveyLogicValidator;
use App\Modules\Survey\Validators\SurveyTranslationValidator;

class SurveyPublishValidator
{
    public function __construct(
        protected SurveyTranslationValidator $translationValidator,
        protected SurveyLogicValidator $logicValidator,
    ) {}

    public function validate(Survey $survey): void
    {
        $survey->loadMissing(['pages.questions.options']);

        if ($survey->pages->isEmpty()) {
            throw new ApiException(
                message: 'Survey must have at least one page to be submitted for review.',
                errorCode: 'VALIDATION_ERROR',
                statusCode: 422,
                fields: ['pages' => ['Survey must have at least one page.']],
            );
        }

        $totalQuestions = $survey->pages->sum(fn ($page) => $page->questions->count());
        if ($totalQuestions === 0) {
            throw new ApiException(
                message: 'Survey must have at least one question to be submitted for review.',
                errorCode: 'VALIDATION_ERROR',
                statusCode: 422,
                fields: ['questions' => ['Survey must have at least one question.']],
            );
        }

        // Validate individual question settings & option count
        foreach ($survey->pages as $page) {
            if ($page->questions->isEmpty()) {
                throw new ApiException(
                    message: 'Survey pages must contain at least one question.',
                    errorCode: 'VALIDATION_ERROR',
                    statusCode: 422,
                    fields: ["pages.{$page->id}.questions" => ['At least one question is required.']],
                );
            }

            foreach ($page->questions as $question) {
                QuestionSettingsValidator::validate($question->type, $question->settings);

                if ($question->type->requiresOptions() && $question->options->count() < 2) {
                    throw new ApiException(
                        message: "Question {$question->id} must have at least 2 options.",
                        errorCode: 'VALIDATION_ERROR',
                        statusCode: 422,
                        fields: ["questions.{$question->id}.options" => ['At least 2 options required.']],
                    );
                }

                if ($question->type->requiresOptions() && (int) ($question->settings['min_selections'] ?? 1) > $question->options->count()) {
                    throw new ApiException(
                        message: 'Minimum selections exceeds the available options.',
                        errorCode: 'INVALID_QUESTION_SETTINGS',
                        statusCode: 422,
                        fields: ["questions.{$question->id}.settings.min_selections" => ['Not enough options to satisfy the minimum.']],
                    );
                }
            }
        }

        // Validate translation completeness
        $this->translationValidator->validate($survey, throw: true);
        $this->logicValidator->validate($survey, $survey->logicRules()->get()->toArray());
    }
}
