<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('survey_logic_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('survey_id')->constrained()->cascadeOnDelete();
            $table->string('action');
            $table->unsignedBigInteger('source_page_id')->nullable();
            $table->unsignedBigInteger('target_page_id')->nullable();
            $table->unsignedBigInteger('target_question_id')->nullable();
            $table->unsignedInteger('priority')->default(0);
            $table->string('match');
            $table->jsonb('conditions');
            $table->timestamps();
            $table->index(['survey_id', 'priority']);
        });

        Schema::table('participations', function (Blueprint $table) {
            $table->jsonb('page_history')->default('[]');
        });
    }

    public function down(): void
    {
        Schema::table('participations', function (Blueprint $table) {
            $table->dropColumn('page_history');
        });
        Schema::dropIfExists('survey_logic_rules');
    }
};
