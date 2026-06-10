<?php

namespace App\Support\Openzaak;

use Woweb\Openzaak\Api\Actions\Delete;
use Woweb\Openzaak\Api\Endpoints\Zaken\Nested\Zaakeigenschappen;

class DeletableZaakeigenschappen extends Zaakeigenschappen
{
    use Delete;
}
