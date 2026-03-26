<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Municipality;
use App\Models\ReportQuestion;
use Illuminate\Http\Request;

class ReportQuestionController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(Request $request, Municipality $municipality)
    {
        // Check if municipality uses the new report questions system
        if (! $municipality->use_new_report_questions) {
            return response()->json([
                'data' => [],
            ]);
        }

        // Get active questions ordered by order field
        $questions = $municipality->reportQuestions()
            ->where('is_active', true)
            ->orderBy('order')
            ->get()
            ->map(function (ReportQuestion $question) {
                return [
                    'question' => $question->placeholder_value ? str_replace('XX', $question->placeholder_value, $question->question) : $question->question,
                    'order' => $question->order,
                ];
            })
            ->pluck('question', 'order');

        return response()->json([
            'data' => $questions,
        ]);
    }
}
