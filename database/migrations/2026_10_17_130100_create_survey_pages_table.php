<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('survey_pages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('survey_id')->constrained('surveys')->cascadeOnDelete();
            $table->integer('position');
            $table->jsonb('title')->nullable();
            $table->jsonb('description')->nullable();
            $table->timestamps();

            $table->index(['survey_id', 'position']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('survey_pages');
    }
};
