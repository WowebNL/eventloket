<?php

namespace App\Exceptions;

use Exception;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class InviteNotFoundException extends Exception
{
    /**
     * Render the exception as an HTTP response.
     */
    public function render(Request $request): Response
    {
        return response()->view('errors.invite-not-found', [
            'heading' => __('errors/invite-not-found.heading'),
            'subheading' => __('errors/invite-not-found.subheading', ['days' => config('invites.expiration_days')]),
        ], 404);
    }
}
