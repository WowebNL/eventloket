<?php

namespace App\Models\Traits;

use Illuminate\Support\Str;

trait HasUuid
{
    /**
     * Field name in the DB of the uuid field
     *
     * @var string
     */
    protected static $uuidFieldName = 'uuid';

    /**
     * Boots uuid on model creation
     *
     * @return void
     */
    protected static function bootHasUuid()
    {
        static::creating(function ($model) {
            if (! $model->{self::$uuidFieldName}) {
                $model->{self::$uuidFieldName} = (string) Str::uuid();
            }
        });
    }

    /**
     * Returns the id of the model based on the uuid
     */
    protected static function idByUuuid(string $uuid): ?int
    {
        return self::where('uuid', $uuid)->select('id')->first()?->id;
    }
}
