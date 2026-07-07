<?php

use App\Exceptions\InviteNotFoundException;
use Illuminate\Support\Facades\Log;

test('report logs an info line instead of reporting to Sentry', function () {
    Log::shouldReceive('info')
        ->once()
        ->with('Invite accept attempt with an invalid or expired token.');

    // Returning anything other than false tells Laravel's handler to skip the
    // default reporting (Sentry). A void report() returns null, so an invalid or
    // expired invite only produces the log line above.
    $result = (new InviteNotFoundException)->report();

    expect($result)->toBeNull();
});
