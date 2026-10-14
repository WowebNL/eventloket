<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Thrown when a municipality tries to add more form questions than
 * `config('extra-questions.max_per_municipality')` allows.
 */
class MunicipalityFormQuestionLimitReached extends RuntimeException {}
