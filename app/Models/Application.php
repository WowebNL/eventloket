<?php

namespace App\Models;

use App\Models\Traits\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\Relations\Relation;
use Laravel\Passport\Client;
use Laravel\Passport\Token;

/**
 * The application model is used for register applications which have access to the api
 * We don't use apitokens on the user model due to a user is seen as a Filament user.
 */
class Application extends Model
{
    use HasUuid;

    protected $fillable = [
        'name',
        'all_endpoints',
    ];

    public function clients()
    {
        return $this->morphMany(Client::class, 'owner');
    }

    public function tokens(): HasManyThrough
    {
        return $this->hasManyThrough(Token::class, Client::class, 'owner_id')->where(
            'owner_type',
            array_search(static::class, Relation::morphMap()) ?: static::class
        );
    }
}
