<?php

namespace App\Modules\Survey\Validators;

use App\Exceptions\ApiException;
use App\Modules\Survey\Models\Survey;

class SurveyTranslationValidator
{
    /**
     * @return array<string, array<int, string>>
     */
    public function validate(Survey $survey, bool $throw = true): array
    {
        $survey->loadMissing(['pages.questions.options']);
        $locales = $survey->locales;
        $errors = [];

        // 1. Survey Title
        foreach ($locales as $locale) {
            if (empty($survey->title[$locale]) || trim((string) $survey->title[$locale]) === '') {
                $errors["title.{$locale}"] = ["Survey title is missing for locale '{$locale}'."];
            }
        }

        // 2. Questions & Options
        foreach ($survey->pages as $page) {
            foreach ($page->questions as $question) {
                foreach ($locales as $locale) {
                    if (empty($question->title[$locale]) || trim((string) $question->title[$locale]) === '') {
                        $errors["questions.{$question->id}.title.{$locale}"] = [
                            "Question {$question->id} title is missing for locale '{$locale}'.",
                        ];
                    }
                }

                if ($question->type->requiresOptions()) {
                    if ($question->options->count() < 2) {
                        $errors["questions.{$question->id}.options"] = [
                            "Question {$question->id} must have at least 2 options.",
                        ];
                    }

                    foreach ($question->options as $option) {
                        foreach ($locales as $locale) {
                            if (empty($option->label[$locale]) || trim((string) $option->label[$locale]) === '') {
                                $errors["options.{$option->id}.label.{$locale}"] = [
                                    "Option {$option->id} label is missing for locale '{$locale}'.",
                                ];
                            }
                        }
                    }
                }
            }
        }

        if ($throw && ! empty($errors)) {
            throw new ApiException(
                message: 'Survey translations are incomplete.',
                errorCode: 'TRANSLATION_INCOMPLETE',
                statusCode: 422,
                fields: $errors,
            );
        }

        return $errors;
    }
}
