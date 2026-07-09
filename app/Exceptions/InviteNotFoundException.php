<?php

namespace App\Exceptions;

use Exception;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

class InviteNotFoundException extends Exception
{
    /**
     * Report the exception.
     *
     * Opening an invalid or expired invite link is a benign, visitor-triggered
     * event that already shows a friendly 404 page via render(). Returning void
     * (anything other than false) suppresses the default reporting to Sentry, so
     * we only leave a log line instead of raising an error alert.
     */
    public function report(): void
    {
        Log::info('Invite accept attempt with an invalid or expired token.');
    }

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
