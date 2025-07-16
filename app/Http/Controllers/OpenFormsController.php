<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class OpenFormsController extends Controller
{
    public function form(Request $request)
    {
        return view('open-form.form', [
            'formId' => 'b68c0bbd-11db-4468-bea9-f6e47f30b60f',
        ]);
    }
}
