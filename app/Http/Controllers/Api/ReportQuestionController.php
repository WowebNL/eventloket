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
        // Return all configured municipality questions ordered by order.
        $questions = $municipality->reportQuestions()
            ->orderBy('order')
            ->where('is_active', true)
            ->get()
            ->values()
            ->mapWithKeys(fn (ReportQuestion $question, int $index) => [
                $index + 1 => $question->question,
            ]);

        return response()->json([
            'data' => $questions,
        ]);
    }
}
