<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class FormSubmissionController extends Controller
{
    public function store(Request $request)
    {

        // check what is comming from open forms but save all for now
        $formSubmission = \App\Models\FormSubmission::create([
            'submission' => json_encode($request->all()),
        ]);

        return response()->json(['message' => 'Form submission saved successfully', 'id' => $formSubmission->id], 201);
    }
}
