<?php

use App\Models\Municipality;
use App\Models\ReportQuestion;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $defaultQuestions = config('report-questions.defaults', []);

        // Seed report questions for all existing municipalities
        Municipality::all()->each(function ($municipality) use ($defaultQuestions) {
            foreach ($defaultQuestions as $order => $question) {
                ReportQuestion::create([
                    'municipality_id' => $municipality->id,
                    'order' => $order,
                    'question' => $question,
                    'is_active' => true,
                ]);
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Remove all report questions
        ReportQuestion::truncate();
    }
};
