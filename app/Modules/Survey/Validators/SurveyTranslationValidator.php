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
            if (! is_string($survey->title[$locale] ?? null) || trim($survey->title[$locale]) === '') {
                $errors["title.{$locale}"] = ["Survey title is missing for locale '{$locale}'."];
            }
        }

        // 2. Questions & Options
        foreach ($survey->pages as $page) {
            $errors = array_merge($errors, $this->validateOptionalMap($page->title, "pages.{$page->id}.title", $locales));

            foreach ($page->questions as $question) {
                foreach (['min_label', 'max_label'] as $label) {
                    $errors = array_merge($errors, $this->validateOptionalMap(
                        $question->settings[$label] ?? null,
                        "questions.{$question->id}.settings.{$label}",
                        $locales,
                    ));
                }

                foreach ($locales as $locale) {
                    if (! is_string($question->title[$locale] ?? null) || trim($question->title[$locale]) === '') {
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
                            if (! is_string($option->label[$locale] ?? null) || trim($option->label[$locale]) === '') {
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

    /**
     * @param  array<string, string>|null  $translations
     * @param  array<int, string>  $locales
     * @return array<string, array<int, string>>
     */
    private function validateOptionalMap(?array $translations, string $field, array $locales): array
    {
        if ($translations === null || $translations === []) {
            return [];
        }

        $errors = [];
        foreach ($locales as $locale) {
            if (! is_string($translations[$locale] ?? null) || trim($translations[$locale]) === '') {
                $errors["{$field}.{$locale}"] = ["Translation is missing for locale '{$locale}'."];
            }
        }

        return $errors;
    }
}
