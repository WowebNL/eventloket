<?php

namespace App\Models\Threads;

use App\Enums\ThreadType;
use App\Models\Advisory;
use App\Models\Thread;
use App\Models\Zaak;
use App\Observers\OrganiserThreadObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;

/**
 * @property ThreadType $type
 * @property Advisory $advisory
 * @property Zaak $zaak
 */
#[ObservedBy(OrganiserThreadObserver::class)]
class OrganiserThread extends Thread {}
