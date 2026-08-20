<?php

use App\Models\Application;
use App\Models\User;

/**
 * Read a throttle value from the environment, validated.
 *
 * An env value always arrives as a string, and (int) turns anything unusable
 * into 0. A maximum of 0 would lock the whole application out and a decay of 0
 * would disable the limiter silently, so a value that is not a number falls
 * back to the documented default rather than being clamped to the floor:
 * clamping a typo in LOGIN_MAX_ATTEMPTS down to 1 would lock every user out
 * after a single mistyped password. A numeric value below the floor is still
 * clamped, because that is a deliberate setting worth honouring as far as it
 * is safe to.
 *
 * Kept in sync with Login::throttleValue(), which repeats this on the call site
 * because Config::set() and a cached config artefact both bypass this file.
 */
$throttle = function (string $key, int $default, int $floor): int {
    $value = env($key, $default);

    return is_numeric($value) ? max($floor, (int) $value) : $default;
};

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

        'applications' => [
            'driver' => 'eloquent',
            'model' => Application::class,
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
    | Every value is validated by the $throttle helper above: a value that is
    | not a number falls back to the default, and a numeric value below the
    | floor is clamped. That way neither an empty nor a mistyped env value can
    | disable the limiter or turn it into an application-wide lockout.
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
            'max_attempts' => $throttle('LOGIN_MAX_ATTEMPTS', 5, 1),
            'decay_seconds' => $throttle('LOGIN_DECAY_SECONDS', 900, 60),
        ],
        // Looser: per account across all IPs, to catch distributed spraying on one
        // account without making it cheap to lock a known account out from anywhere.
        'login_account' => [
            'max_attempts' => $throttle('LOGIN_ACCOUNT_MAX_ATTEMPTS', 20, 1),
            'decay_seconds' => $throttle('LOGIN_ACCOUNT_DECAY_SECONDS', 3600, 60),
        ],
        // Backstop: per IP, wide enough for a shared office outbound address.
        'login_ip' => [
            'max_attempts' => $throttle('LOGIN_IP_MAX_ATTEMPTS', 50, 1),
            'decay_seconds' => $throttle('LOGIN_IP_DECAY_SECONDS', 900, 60),
        ],
        'mfa' => [
            'max_attempts' => $throttle('MFA_MAX_ATTEMPTS', 5, 1),
            'decay_seconds' => $throttle('MFA_DECAY_SECONDS', 900, 60),
        ],
        'password_reset_request' => [
            'max_attempts' => $throttle('PASSWORD_RESET_REQUEST_MAX_ATTEMPTS', 5, 1),
            'decay_seconds' => $throttle('PASSWORD_RESET_REQUEST_DECAY_SECONDS', 900, 60),
        ],
        'password_reset' => [
            'max_attempts' => $throttle('PASSWORD_RESET_MAX_ATTEMPTS', 5, 1),
            'decay_seconds' => $throttle('PASSWORD_RESET_DECAY_SECONDS', 900, 60),
        ],
        'registration' => [
            'max_attempts' => $throttle('REGISTRATION_MAX_ATTEMPTS', 5, 1),
            'decay_seconds' => $throttle('REGISTRATION_DECAY_SECONDS', 900, 60),
        ],
    ],

];
