<?php

namespace App\Models;

use Database\Factories\ReportQuestionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReportQuestion extends Model
{
    /** @use HasFactory<ReportQuestionFactory> */
    use HasFactory;

    protected $fillable = [
        'municipality_id',
        'order',
        'question',
        'is_active',
        'placeholder_value',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'order' => 'integer',
        ];
    }

    public function municipality(): BelongsTo
    {
        return $this->belongsTo(Municipality::class);
    }

    public static function defaultQuestions(): array
    {
        return [
            1 => 'Is het aantal aanwezigen bij uw evenement minder dan XX personen?',
            2 => 'Het evenement vindt plaats op een maandag, dinsdag, woensdag, donderdag tussen 09.00 - 22.00 uur.',
            3 => 'Het evenement vindt plaats op een vrijdag of zaterdag tussen 09.00 en 24.00 uur.',
            4 => 'Het evenement vindt plaats op een zon- en of feestdag tussen 09.00 en 23.00 uur.',
            5 => 'Is de geluidsproductie lager dan 80 dB(A) bronvermogen, gemeten op 3 meter afstand van de bron?',
            6 => 'Er vinden GEEN activiteiten plaats op de rijbaan, (brom)fietspad of parkeerplaats of anderszins een belemmering vormen voor het verkeer en de hulpdiensten? Kies "Ja" als dit niet het geval is.',
            7 => 'Indien er objecten geplaatst worden, zijn deze dan kleiner XX m2?',
            8 => 'Er worden geen gebiedsontsluitingswegen en/of doorgaande wegen afgesloten voor het verkeer? Kies "Ja", als dit niet het geval is.',
            9 => 'Het evenement valt niet onder de categorie een braderie, snuffelmarkt of optocht. Kies "Ja" als dit niet het geval is.',
            10 => 'Er wordt geen vuurwerk afgestoken. Kies "Ja" als dit niet het geval is.',
        ];
    }
}
