<?php

namespace App\Services;

use App\Models\Organisation;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * Handles generation and validation of short-lived HMAC tokens
 * for authenticating Eventloket users in Open Forms.
 */
class EventloketTokenService
{
    public function generate(string $userUuid, string $organisationUuid): string
    {
        $timestamp = time();
        $nonce = Str::random(16);
        $payload = "{$userUuid}|{$organisationUuid}|{$timestamp}|{$nonce}";

        $signature = hash_hmac('sha256', $payload, $this->secret());
        $token = base64_encode("{$payload}|{$signature}");

        Cache::put("eventloket_token:{$signature}", [
            'user_uuid' => $userUuid,
            'organisation_uuid' => $organisationUuid,
        ], now()->addMinutes(5));

        return $token;
    }

    /**
     * Validate a token and return the associated user and organisation, or null if invalid.
     *
     * @return array{user: User, organisation: Organisation}|null
     */
    public function validate(string $token): ?array
    {
        $payload = $this->verifyAndConsumeToken($token);

        if (! $payload) {
            return null;
        }

        return $this->resolveModels($payload['user_uuid'], $payload['organisation_uuid']);
    }

    /**
     * Verify HMAC signature, timestamp, and single-use constraint.
     *
     * @return array{user_uuid: string, organisation_uuid: string}|null
     */
    private function verifyAndConsumeToken(string $token): ?array
    {
        $decoded = base64_decode($token, true);
        if (! $decoded) {
            return null;
        }

        $parts = explode('|', $decoded);
        if (count($parts) !== 5) {
            return null;
        }

        [$userUuid, $organisationUuid, $timestamp, $nonce, $signature] = $parts;

        if (abs(time() - (int) $timestamp) > 300) {
            return null;
        }

        $payload = "{$userUuid}|{$organisationUuid}|{$timestamp}|{$nonce}";
        if (! hash_equals(hash_hmac('sha256', $payload, $this->secret()), $signature)) {
            return null;
        }

        $cacheKey = "eventloket_token:{$signature}";
        if (! Cache::get($cacheKey)) {
            return null;
        }

        return [
            'user_uuid' => $userUuid,
            'organisation_uuid' => $organisationUuid,
        ];
    }

    /**
     * Resolve UUIDs to Eloquent models.
     *
     * @return array{user: User, organisation: Organisation}|null
     */
    private function resolveModels(string $userUuid, string $organisationUuid): ?array
    {
        $user = User::where('uuid', $userUuid)->first();
        $organisation = Organisation::where('uuid', $organisationUuid)->first();

        if (! $user || ! $organisation) {
            return null;
        }

        return [
            'user' => $user,
            'organisation' => $organisation,
        ];
    }

    private function secret(): string
    {
        return config('services.open_forms.token_signing_key');
    }
}
