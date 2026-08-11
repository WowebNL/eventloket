<?php

use App\Models\User;

return [

    /*
    |--------------------------------------------------------------------------
    | Authentication Defaults
    |--------------------------------------------------------------------------
    |
    | This option defines the default authentication "guard" and password
    | reset "broker" for your application. You may change these values
    | as required, but they're a perfect start for most applications.
    |
    */

    'defaults' => [
        'guard' => env('AUTH_GUARD', 'web'),
        'passwords' => env('AUTH_PASSWORD_BROKER', 'users'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Authentication Guards
    |--------------------------------------------------------------------------
    |
    | Next, you may define every authentication guard for your application.
    | Of course, a great default configuration has been defined for you
    | which utilizes session storage plus the Eloquent user provider.
    |
    | All authentication guards have a user provider, which defines how the
    | users are actually retrieved out of your database or other storage
    | system used by the application. Typically, Eloquent is utilized.
    |
    | Supported: "session"
    |
    */

    'guards' => [
        'web' => [
            'driver' => 'session',
            'provider' => 'users',
        ],
        'api' => [
            'driver' => 'passport',
            'provider' => 'applications',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | User Providers
    |--------------------------------------------------------------------------
    |
    | All authentication guards have a user provider, which defines how the
    | users are actually retrieved out of your database or other storage
    | system used by the application. Typically, Eloquent is utilized.
    |
    | If you have multiple user tables or models you may configure multiple
    | providers to represent the model / table. These providers may then
    | be assigned to any extra authentication guards you have defined.
    |
    | Supported: "database", "eloquent"
    |
    */

    'providers' => [
        'users' => [
            'driver' => 'case-insensitive-eloquent',
            'model' => env('AUTH_MODEL', User::class),
        ],

        // 'users' => [
        //     'driver' => 'database',
        //     'table' => 'users',
        // ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Resetting Passwords
    |--------------------------------------------------------------------------
    |
    | These configuration options specify the behavior of Laravel's password
    | reset functionality, including the table utilized for token storage
    | and the user provider that is invoked to actually retrieve users.
    |
    | The expiry time is the number of minutes that each reset token will be
    | considered valid. This security feature keeps tokens short-lived so
    | they have less time to be guessed. You may change this as needed.
    |
    | The throttle setting is the number of seconds a user must wait before
    | generating more password reset tokens. This prevents the user from
    | quickly generating a very large amount of password reset tokens.
    |
    */

    'passwords' => [
        'users' => [
            'provider' => 'users',
            'table' => env('AUTH_PASSWORD_RESET_TOKEN_TABLE', 'password_reset_tokens'),
            'expire' => 60,
            'throttle' => 60,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Password Confirmation Timeout
    |--------------------------------------------------------------------------
    |
    | Here you may define the number of seconds before a password confirmation
    | window expires and users are asked to re-enter their password via the
    | confirmation screen. By default, the timeout lasts for three hours.
    |
    */

    'password_timeout' => env('AUTH_PASSWORD_TIMEOUT', 10800),

    /*
    |--------------------------------------------------------------------------
    | Login Rate Limiting
    |--------------------------------------------------------------------------
    |
    | Configure how many failed login attempts are allowed before a lockout,
    | and how long the lockout period lasts in seconds. Defaults enforce a
    | 15-minute lockout after 5 failed attempts (OWASP recommendation for
    | government-facing applications).
    |
    | Every value is cast to an integer and clamped to a safe floor. An env
    | value always arrives as a string, and an unset or non-numeric value casts
    | to 0. A decay of 0 would disable the limiter silently, and a maximum of 0
    | would lock everyone out, so both are clamped towards the strict side.
    |
    | The login limiters key on request()->ip(), and that is the real client
    | address because nothing proxies production: TrustProxies is deliberately
    | not configured, so a client cannot forge X-Forwarded-For to escape the
    | counters. Putting a CDN, WAF or load balancer in front of the application
    | changes that assumption: every request would then arrive from one address,
    | which turns the login_ip backstop into an application-wide lockout and
    | strips the IP component out of the strict login limiter. Configure
    | TrustProxies first if that ever happens, and revisit these numbers.
    |
    */

    'throttle' => [
        // Strict: per account and IP. This is the limit the OWASP note above describes.
        'login' => [
            'max_attempts' => max(1, (int) env('LOGIN_MAX_ATTEMPTS', 5)),
            'decay_seconds' => max(60, (int) env('LOGIN_DECAY_SECONDS', 900)),
        ],
        // Looser: per account across all IPs, to catch distributed spraying on one
        // account without making it cheap to lock a known account out from anywhere.
        'login_account' => [
            'max_attempts' => max(1, (int) env('LOGIN_ACCOUNT_MAX_ATTEMPTS', 20)),
            'decay_seconds' => max(60, (int) env('LOGIN_ACCOUNT_DECAY_SECONDS', 3600)),
        ],
        // Backstop: per IP, wide enough for a shared office outbound address.
        'login_ip' => [
            'max_attempts' => max(1, (int) env('LOGIN_IP_MAX_ATTEMPTS', 50)),
            'decay_seconds' => max(60, (int) env('LOGIN_IP_DECAY_SECONDS', 900)),
        ],
        'mfa' => [
            'max_attempts' => max(1, (int) env('MFA_MAX_ATTEMPTS', 5)),
            'decay_seconds' => max(60, (int) env('MFA_DECAY_SECONDS', 900)),
        ],
        'password_reset_request' => [
            'max_attempts' => max(1, (int) env('PASSWORD_RESET_REQUEST_MAX_ATTEMPTS', 5)),
            'decay_seconds' => max(60, (int) env('PASSWORD_RESET_REQUEST_DECAY_SECONDS', 900)),
        ],
        'password_reset' => [
            'max_attempts' => max(1, (int) env('PASSWORD_RESET_MAX_ATTEMPTS', 5)),
            'decay_seconds' => max(60, (int) env('PASSWORD_RESET_DECAY_SECONDS', 900)),
        ],
        'registration' => [
            'max_attempts' => max(1, (int) env('REGISTRATION_MAX_ATTEMPTS', 5)),
            'decay_seconds' => max(60, (int) env('REGISTRATION_DECAY_SECONDS', 900)),
        ],
    ],

];
