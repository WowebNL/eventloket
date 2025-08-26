<?php

namespace App\Models;

use App\Models\Traits\HasUuid;
use Illuminate\Database\Eloquent\Model;

class FormSubmission extends Model
{
    use HasUuid;

    protected $fillable = [
        'submission',
    ];
}
