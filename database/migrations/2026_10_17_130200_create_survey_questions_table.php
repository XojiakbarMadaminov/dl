<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('survey_questions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('page_id')->constrained('survey_pages')->cascadeOnDelete();
            $table->string('type');
            $table->jsonb('title');
            $table->jsonb('description')->nullable();
            $table->boolean('is_required')->default(false);
            $table->integer('position');
            $table->jsonb('settings')->default('{}');
            $table->timestamps();

            $table->index(['page_id', 'position']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('survey_questions');
    }
};
