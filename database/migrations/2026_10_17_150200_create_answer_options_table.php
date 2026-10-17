<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('answer_options', function (Blueprint $table) {
            $table->foreignId('answer_id')->constrained('answers')->cascadeOnDelete();
            $table->foreignId('option_id')->constrained('survey_question_options')->cascadeOnDelete();

            $table->primary(['answer_id', 'option_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('answer_options');
    }
};
