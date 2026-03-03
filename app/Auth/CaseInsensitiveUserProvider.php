<?php

namespace App\Auth;

use Illuminate\Auth\EloquentUserProvider;
use Illuminate\Contracts\Auth\Authenticatable;

class CaseInsensitiveUserProvider extends EloquentUserProvider
{
    /**
     * Retrieve a user by the given credentials.
     *
     * @return ($credentials is non-empty-array ? (Authenticatable&\Illuminate\Database\Eloquent\Model)|null : null)
     */
    public function retrieveByCredentials(array $credentials): ?Authenticatable
    {
        // Filter out password fields
        $credentials = array_filter($credentials, function ($key) {
            return ! str_contains($key, 'password');
        }, ARRAY_FILTER_USE_KEY);

        if (empty($credentials)) {
            return null;
        }

        // Normalize email to lowercase for case-insensitive lookup
        if (isset($credentials['email'])) {
            $credentials['email'] = strtolower($credentials['email']);
        }

        /** @var (Authenticatable&\Illuminate\Database\Eloquent\Model)|null */
        return $this->newModelQuery()->where($credentials)->first();
    }
}
