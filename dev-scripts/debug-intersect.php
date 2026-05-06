<?php

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

use App\EventForm\Rules\AlsReductieVan1BeginnendBij0IsGelijkA;
use App\EventForm\Rules\Rule6f1046a6;
use App\EventForm\Services\ServiceFetcher;
use App\EventForm\State\FormState;
use Illuminate\Contracts\Console\Kernel;

// Polygon ergens midden in Maastricht — coördinaten ongeveer Vrijthof.
$polygon = [
    'type' => 'Polygon',
    'coordinates' => [[
        [5.685, 50.847],
        [5.692, 50.847],
        [5.692, 50.853],
        [5.685, 50.853],
        [5.685, 50.847],
    ]],
];

$state = new FormState(values: [
    'locatieSOpKaart' => [
        [
            'naamVanDeLocatieKaart' => 'Vrijthof',
            'buitenLocatieVanHetEvenement' => [
                'lat' => 50.85,
                'lng' => 5.69,
                'geojson' => [
                    'type' => 'FeatureCollection',
                    'features' => [
                        [
                            'type' => 'Feature',
                            'properties' => new stdClass,
                            'geometry' => $polygon,
                        ],
                    ],
                ],
            ],
        ],
    ],
]);

app(ServiceFetcher::class)->fetch('inGemeentenResponse', $state);

$response = $state->get('inGemeentenResponse');
echo "inGemeentenResponse:\n";
var_dump($response);

echo "\nall.items:\n";
var_dump($response['all']['items'] ?? null);

echo "\nall.within:\n";
var_dump($response['all']['within'] ?? null);

// Run de rule die evenementInGemeentenNamen bouwt
app(Rule6f1046a6::class)->apply($state);
echo "\nevenementInGemeentenNamen:\n";
var_dump($state->get('evenementInGemeentenNamen'));

// En de rule die evenementInGemeente zet bij count=1
$ruleAuto = new AlsReductieVan1BeginnendBij0IsGelijkA;
echo "\nAuto-set rule applies? ";
var_dump($ruleAuto->applies($state));
if ($ruleAuto->applies($state)) {
    $ruleAuto->apply($state);
    echo 'evenementInGemeente: ';
    var_dump($state->get('evenementInGemeente'));
}
