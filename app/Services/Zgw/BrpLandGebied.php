<?php

declare(strict_types=1);

namespace App\Services\Zgw;

use Illuminate\Support\Str;

/**
 * The BRP Land/Gebied table (RvIG "Tabel 34 Landentabel"), which the ZGW Zaken
 * API references for `SubVerblijfBuitenland.lndLandcode` and `.lndLandnaam`.
 *
 * A foreign address can only be sent to a ZGW backend as a subVerblijfBuitenland,
 * and that object requires both the code and the name from this table. The event
 * form asks for a country as free text, so the typed value has to be resolved
 * against the table before an address may be built from it; a value that cannot
 * be resolved yields no code and therefore no foreign address at all, which is
 * the same outcome as before this table existed.
 *
 * Only entries that are currently in force are listed (the table also carries
 * historic countries with an end date, which must not be sent for a present-day
 * address), keyed by their four-character code and holding the ISO 3166-1
 * alpha-2 code and the Dutch name the table publishes.
 */
final class BrpLandGebied
{
    /**
     * code => [ISO 3166-1 alpha-2, name], ordered by name. PHP narrows the
     * numeric keys to integers, so a code read from this table is cast back to
     * the string the wire format wants.
     *
     * @var array<int, array{0: string, 1: string}>
     */
    private const TABLE = [
        '6023' => ['AF', 'Afghanistan'],
        '5034' => ['AL', 'Albanië'],
        '6047' => ['DZ', 'Algerije'],
        '8002' => ['AS', 'Amerikaans-Samoa'],
        '8047' => ['UM', 'Amerikaanse Kleinere Afgelegen Eilanden'],
        '7088' => ['VI', 'Amerikaanse Maagdeneilanden'],
        '7005' => ['AD', 'Andorra'],
        '5026' => ['AO', 'Angola'],
        '8036' => ['AI', 'Anguilla'],
        '8045' => ['AG', 'Antigua en Barbuda'],
        '7015' => ['AR', 'Argentinië'],
        '5054' => ['AM', 'Armenië'],
        '5095' => ['AW', 'Aruba'],
        '6016' => ['AU', 'Australië'],
        '5097' => ['AZ', 'Azerbeidzjan'],
        '6033' => ['BS', 'Bahama\'s'],
        '5057' => ['BH', 'Bahrein'],
        '7084' => ['BD', 'Bangladesh'],
        '7004' => ['BB', 'Barbados'],
        '5098' => ['BY', 'Belarus'],
        '5010' => ['BE', 'België'],
        '8017' => ['BZ', 'Belize'],
        '8023' => ['BJ', 'Benin'],
        '9048' => ['BM', 'Bermuda'],
        '5058' => ['BT', 'Bhutan'],
        '6015' => ['BO', 'Bolivia'],
        '5106' => ['', 'Bonaire'],
        '9089' => ['DE', 'Bondsrepubliek Duitsland'],
        '6065' => ['BA', 'Bosnië en Herzegovina'],
        '5011' => ['BW', 'Botswana'],
        '6008' => ['BR', 'Brazilië'],
        '7096' => ['IO', 'Brits Indische Oceaanterritorium'],
        '7030' => ['VG', 'Britse Maagdeneilanden'],
        '5042' => ['BN', 'Brunei'],
        '7024' => ['BG', 'Bulgarije'],
        '5096' => ['BF', 'Burkina Faso'],
        '6001' => ['BI', 'Burundi'],
        '6031' => ['KH', 'Cambodja'],
        '5001' => ['CA', 'Canada'],
        '9086' => ['CF', 'Centraal-Afrikaanse Republiek'],
        '5021' => ['CL', 'Chili'],
        '6022' => ['CN', 'China'],
        '8012' => ['CX', 'Christmaseiland'],
        '8013' => ['CC', 'Cocoseilanden'],
        '5033' => ['CO', 'Colombia'],
        '5060' => ['KM', 'Comoren'],
        '7097' => ['CK', 'Cookeilanden'],
        '7007' => ['CR', 'Costa Rica'],
        '5006' => ['CU', 'Cuba'],
        '5107' => ['CW', 'Curaçao'],
        '5040' => ['CY', 'Cyprus'],
        '6069' => ['CD', 'Democratische Republiek Congo'],
        '5015' => ['DK', 'Denemarken'],
        '9087' => ['DJ', 'Djibouti'],
        '8030' => ['DM', 'Dominica'],
        '7027' => ['DO', 'Dominicaanse Republiek'],
        '7039' => ['EC', 'Ecuador'],
        '7014' => ['EG', 'Egypte'],
        '7032' => ['SV', 'El Salvador'],
        '9043' => ['GQ', 'Equatoriaal-Guinea'],
        '9003' => ['ER', 'Eritrea'],
        '7065' => ['EE', 'Estland'],
        '9038' => ['SZ', 'Eswatini'],
        '5020' => ['ET', 'Ethiopië'],
        '8014' => ['FO', 'Faeröer'],
        '5061' => ['FK', 'Falklandeilanden'],
        '6032' => ['FJ', 'Fiji'],
        '5027' => ['PH', 'Filipijnen'],
        '6002' => ['FI', 'Finland'],
        '5002' => ['FR', 'Frankrijk'],
        '5062' => ['GF', 'Frans-Guyana'],
        '6054' => ['PF', 'Frans-Polynesië'],
        '6048' => ['GA', 'Gabon'],
        '7008' => ['GM', 'Gambia'],
        '5112' => ['PS', 'Gazastrook en Westelijke Jordaanoever, met'],
        '6064' => ['GE', 'Georgië'],
        '5024' => ['GH', 'Ghana'],
        '6055' => ['GI', 'Gibraltar'],
        '8008' => ['GD', 'Grenada'],
        '6003' => ['GR', 'Griekenland'],
        '5065' => ['GL', 'Groenland'],
        '5066' => ['GP', 'Guadeloupe'],
        '8001' => ['GU', 'Guam'],
        '6004' => ['GT', 'Guatemala'],
        '8051' => ['GG', 'Guernsey'],
        '7040' => ['GN', 'Guinee'],
        '5072' => ['GW', 'Guinee-Bissau'],
        '6025' => ['GY', 'Guyana'],
        '6041' => ['HT', 'Haïti'],
        '7017' => ['HN', 'Honduras'],
        '5017' => ['HU', 'Hongarije'],
        '6007' => ['IE', 'Ierland'],
        '6011' => ['IS', 'IJsland'],
        '7046' => ['IN', 'India'],
        '6024' => ['ID', 'Indonesië'],
        '9999' => ['', 'Internationaal gebied'],
        '5043' => ['IQ', 'Irak'],
        '5012' => ['IR', 'Iran'],
        '6034' => ['IL', 'Israël'],
        '7044' => ['IT', 'Italië'],
        '5030' => ['CI', 'Ivoorkust'],
        '6017' => ['JM', 'Jamaica'],
        '7035' => ['JP', 'Japan'],
        '5048' => ['YE', 'Jemen'],
        '8052' => ['JE', 'Jersey'],
        '6042' => ['JO', 'Jordanië'],
        '7092' => ['KY', 'Kaaimaneilanden'],
        '8025' => ['CV', 'Kaapverdië'],
        '5035' => ['CM', 'Kameroen'],
        '5099' => ['KZ', 'Kazachstan'],
        '7002' => ['KE', 'Kenia'],
        '6021' => ['KG', 'Kirgizië'],
        '8027' => ['KI', 'Kiribati'],
        '7045' => ['KW', 'Koeweit'],
        '5105' => ['', 'Kosovo'],
        '5051' => ['HR', 'Kroatië'],
        '5025' => ['LA', 'Laos'],
        '7016' => ['LS', 'Lesotho'],
        '7064' => ['LV', 'Letland'],
        '7043' => ['LB', 'Libanon'],
        '5019' => ['LR', 'Liberia'],
        '6006' => ['LY', 'Libië'],
        '6012' => ['LI', 'Liechtenstein'],
        '7066' => ['LT', 'Litouwen'],
        '6018' => ['LU', 'Luxemburg'],
        '9010' => ['MG', 'Madagaskar'],
        '5005' => ['MW', 'Malawi'],
        '7041' => ['MV', 'Maldiven'],
        '7026' => ['MY', 'Maleisië'],
        '5029' => ['ML', 'Mali'],
        '7003' => ['MT', 'Malta'],
        '8035' => ['IM', 'Man'],
        '5022' => ['MA', 'Marokko'],
        '9056' => ['MH', 'Marshalleilanden'],
        '5069' => ['MQ', 'Martinique'],
        '6020' => ['MR', 'Mauritanië'],
        '5044' => ['MU', 'Mauritius'],
        '5084' => ['YT', 'Mayotte'],
        '7006' => ['MX', 'Mexico'],
        '9094' => ['FM', 'Micronesië'],
        '6000' => ['MD', 'Moldavië'],
        '5032' => ['MC', 'Monaco'],
        '7052' => ['MN', 'Mongolië'],
        '5104' => ['ME', 'Montenegro'],
        '8015' => ['MS', 'Montserrat'],
        '5070' => ['MZ', 'Mozambique'],
        '5047' => ['MM', 'Myanmar'],
        '9023' => ['NA', 'Namibië'],
        '7057' => ['NR', 'Nauru'],
        '6030' => ['NL', 'Nederland'],
        '6035' => ['NP', 'Nepal'],
        '7018' => ['NI', 'Nicaragua'],
        '7099' => ['NC', 'Nieuw-Caledonië'],
        '5013' => ['NZ', 'Nieuw-Zeeland'],
        '6040' => ['NE', 'Niger'],
        '6005' => ['NG', 'Nigeria'],
        '9091' => ['NU', 'Niue'],
        '6049' => ['KP', 'Noord-Korea'],
        '8009' => ['MP', 'Noordelijke Marianen'],
        '6027' => ['NO', 'Noorwegen'],
        '8016' => ['NF', 'Norfolk'],
        '7001' => ['UG', 'Oeganda'],
        '6038' => ['UA', 'Oekraïne'],
        '6050' => ['UZ', 'Oezbekistan'],
        '7051' => ['OM', 'Oman'],
        '5101' => ['TL', 'Oost-Timor'],
        '5009' => ['AT', 'Oostenrijk'],
        '7020' => ['PK', 'Pakistan'],
        '8044' => ['PW', 'Palau'],
        '7037' => ['PA', 'Panama'],
        '8021' => ['PG', 'Papoea-Nieuw-Guinea'],
        '5038' => ['PY', 'Paraguay'],
        '7049' => ['PE', 'Peru'],
        '5071' => ['PN', 'Pitcairneilanden'],
        '7028' => ['PL', 'Polen'],
        '7050' => ['PT', 'Portugal'],
        '8020' => ['PR', 'Puerto Rico'],
        '9037' => ['QA', 'Qatar'],
        '9008' => ['CG', 'Republiek Congo'],
        '5113' => ['MK', 'Republiek Noord-Macedonië'],
        '7047' => ['RO', 'Roemenië'],
        '5053' => ['RU', 'Rusland'],
        '6009' => ['RW', 'Rwanda'],
        '5073' => ['RE', 'Réunion'],
        '5108' => ['', 'Saba'],
        '8037' => ['KN', 'Saint Kitts en Nevis'],
        '8029' => ['LC', 'Saint Lucia'],
        '5092' => ['VC', 'Saint Vincent en de Grenadines'],
        '8049' => ['BL', 'Saint-Barthélemy'],
        '8050' => ['MF', 'Saint-Martin'],
        '5074' => ['PM', 'Saint-Pierre en Miquelon'],
        '8022' => ['SB', 'Salomonseilanden'],
        '7053' => ['WS', 'Samoa'],
        '6028' => ['SM', 'San Marino'],
        '6059' => ['ST', 'Sao Tomé en Principe'],
        '5018' => ['SA', 'Saoedi-Arabië'],
        '7021' => ['SN', 'Senegal'],
        '5103' => ['RS', 'Servië'],
        '8026' => ['SC', 'Seychellen'],
        '6051' => ['SL', 'Sierra Leone'],
        '5037' => ['SG', 'Singapore'],
        '5109' => ['', 'Sint Eustatius'],
        '5110' => ['SX', 'Sint Maarten'],
        '8046' => ['SH', 'Sint-Helena, Ascension en Tristan da Cunha'],
        '5049' => ['SI', 'Slovenië'],
        '6067' => ['SK', 'Slowakije'],
        '7034' => ['SD', 'Soedan'],
        '6013' => ['SO', 'Somalië'],
        '6037' => ['ES', 'Spanje'],
        '5093' => ['SJ', 'Spitsbergen'],
        '7033' => ['LK', 'Sri Lanka'],
        '5007' => ['SR', 'Suriname'],
        '7009' => ['SY', 'Syrië'],
        '6057' => ['TJ', 'Tadzjikistan'],
        '5052' => ['TW', 'Taiwan'],
        '7031' => ['TZ', 'Tanzania'],
        '7042' => ['TH', 'Thailand'],
        '7055' => ['', 'Tibet'],
        '5023' => ['TG', 'Togo'],
        '7098' => ['TK', 'Tokelau'],
        '5076' => ['TO', 'Tonga'],
        '6044' => ['TT', 'Trinidad en Tobago'],
        '6019' => ['TD', 'Tsjaad'],
        '6066' => ['CZ', 'Tsjechië'],
        '5008' => ['TN', 'Tunesië'],
        '6043' => ['TR', 'Turkije'],
        '6063' => ['TM', 'Turkmenistan'],
        '8019' => ['TC', 'Turks- en Caicoseilanden'],
        '8028' => ['TV', 'Tuvalu'],
        '7038' => ['UY', 'Uruguay'],
        '9090' => ['VU', 'Vanuatu'],
        '5045' => ['VA', 'Vaticaanstad'],
        '6010' => ['VE', 'Venezuela'],
        '6039' => ['GB', 'Verenigd Koninkrijk'],
        '7054' => ['AE', 'Verenigde Arabische Emiraten'],
        '6014' => ['US', 'Verenigde Staten van Amerika'],
        '8024' => ['VN', 'Vietnam'],
        '5077' => ['WF', 'Wallis en Futuna'],
        '9093' => ['EH', 'Westelijke Sahara'],
        '5028' => ['ZM', 'Zambia'],
        '8031' => ['ZW', 'Zimbabwe'],
        '5014' => ['ZA', 'Zuid-Afrika'],
        '8048' => ['GS', 'Zuid-Georgia en de Zuidelijke Sandwicheilanden'],
        '6036' => ['KR', 'Zuid-Korea'],
        '5111' => ['SS', 'Zuid-Soedan'],
        '5039' => ['SE', 'Zweden'],
        '5003' => ['CH', 'Zwitserland'],
        '8053' => ['AX', 'Åland'],
    ];

    /**
     * The code of the Netherlands, the one entry that means "not a foreign
     * address at all".
     */
    public const NETHERLANDS = '6030';

    /**
     * Resolve a country as typed in the form to its table entry.
     *
     * Three steps, from the most to the least certain: a two-letter value is
     * read as an ISO 3166-1 alpha-2 code, then the normalised name is matched
     * exactly, and finally the name is accepted when it occurs inside exactly
     * one entry (so a country published under a longer official name is still
     * found under its everyday name). An input matching several entries is
     * ambiguous and resolves to nothing rather than to a guess.
     *
     * @return array{code: string, naam: string}|null
     */
    public static function resolve(?string $input): ?array
    {
        $normalised = self::normalise((string) $input);

        if ($normalised === '') {
            return null;
        }

        if (strlen($normalised) === 2) {
            foreach (self::TABLE as $code => [$iso, $naam]) {
                if ($iso !== '' && self::normalise($iso) === $normalised) {
                    return ['code' => (string) $code, 'naam' => $naam];
                }
            }
        }

        $partial = [];

        foreach (self::TABLE as $code => [, $naam]) {
            $candidate = self::normalise($naam);

            if ($candidate === $normalised) {
                return ['code' => (string) $code, 'naam' => $naam];
            }

            if (str_contains($candidate, $normalised)) {
                $partial[] = ['code' => (string) $code, 'naam' => $naam];
            }
        }

        return count($partial) === 1 ? $partial[0] : null;
    }

    /**
     * Fold a country name to a comparable form: accents removed, case and
     * punctuation dropped, so "Bosnie-Herzegovina" and "bosnie herzegovina"
     * are the same value.
     */
    private static function normalise(string $value): string
    {
        $ascii = Str::ascii(trim($value));

        return trim((string) preg_replace('/[^a-z0-9]+/', ' ', mb_strtolower($ascii)));
    }
}
