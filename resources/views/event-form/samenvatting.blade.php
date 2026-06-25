{{--
    Render-partial voor de Samenvatting-stap in het evenementformulier.
    Gemeenschappelijke vorm met de submission-PDF (zelfde sections-shape
    uit SubmissionReport): per sectie een tabel, met aparte takken voor
    `table`-entries (zoals het tijden-overzicht) en `sub`-entries (uit
    Repeater-rijen). Een entry met `svg`-key (Map-state met geojson)
    rendert het kaartje in plaats van de raw geojson-tekst — net als
    in de PDF.

    Inline styling i.p.v. CSS-classes: Filament rendert deze HTML via
    een TextEntry-state, zonder access tot het organiser-thema.
--}}
@php
    $tableStyle = 'width: 100%; border-collapse: collapse; margin-bottom: 1rem;';
    $cellStyle = 'padding: 0.4rem 0.5rem; border-bottom: 1px solid #eee; vertical-align: top;';
    $labelStyle = $cellStyle.'color: #555; width: 40%;';
    $valueStyle = $cellStyle;
    $subTableStyle = 'width: 100%; border-collapse: collapse;';
    $subCellStyle = 'padding: 0.3rem 0.5rem; border-bottom: 1px dashed #eee; vertical-align: top; font-size: 0.9rem;';
    $tijdenStyle = 'width: 100%; border-collapse: collapse; margin: 0.25rem 0 0.5rem 0;';
    $tijdenCellStyle = 'padding: 0.35rem 0.6rem; border: 1px solid #ddd; text-align: left;';
    $tijdenHeadStyle = $tijdenCellStyle.'background: #f0f0f0; font-weight: 600;';
@endphp

<h2 style="font-size: 1.25rem; font-weight: 700; margin: 0 0 1rem 0;">Samenvatting</h2>

@if (! empty($risicoClassificatie))
    <div style="margin-bottom: 0.5rem; padding: 0.5rem 0.75rem; background: #f9f9f9; border-left: 3px solid #ccc; font-size: 0.9rem;">
        <strong>Risicoclassificatie:</strong> {{ $risicoClassificatie }}
        @if (! empty($indieningstermijnStatus))
            — indieningstermijn: <strong>{{ $indieningstermijnStatus['weeks'] }} weken</strong>
        @endif
    </div>
@endif

@if (! empty($indieningstermijnStatus))
    @if ($indieningstermijnStatus['withinDeadline'])
        <div style="margin-bottom: 0.5rem; padding: 0.5rem 0.75rem; background: #f0fdf4; border-left: 3px solid #16a34a; font-size: 0.9rem; color: #15803d;">
            Uw aanvraag valt binnen de indieningstermijn van <strong>{{ $indieningstermijnStatus['weeks'] }} weken</strong> voor de startdatum.
        </div>
    @else
        <div style="margin-bottom: 0.5rem; padding: 0.5rem 0.75rem; background: #fffbeb; border-left: 3px solid #d97706; font-size: 0.9rem; color: #92400e;">
            Let op: uw aanvraag valt buiten de indieningstermijn van <strong>{{ $indieningstermijnStatus['weeks'] }} weken</strong> voor de startdatum. U kunt de aanvraag nog steeds indienen, maar de kans op afwijzing is groter.
        </div>
    @endif
@endif

@php
    $heeftTermijnen = ! empty($gemeenteNaam) && (! empty($indieningstermijnen['a']) || ! empty($indieningstermijnen['b']) || ! empty($indieningstermijnen['c']));
@endphp
@if ($heeftTermijnen)
    <div style="margin-bottom: 1rem; padding: 0.5rem 0.75rem; background: #f9f9f9; border-left: 3px solid #ccc; font-size: 0.85rem; color: #555;">
        Indieningstermijnen {{ $gemeenteNaam }}:
        @if (! empty($indieningstermijnen['a']))A: <strong>{{ (int) $indieningstermijnen['a'] }} weken</strong>@endif
        @if (! empty($indieningstermijnen['a']) && (! empty($indieningstermijnen['b']) || ! empty($indieningstermijnen['c']))) · @endif
        @if (! empty($indieningstermijnen['b']))B: <strong>{{ (int) $indieningstermijnen['b'] }} weken</strong>@endif
        @if (! empty($indieningstermijnen['b']) && ! empty($indieningstermijnen['c'])) · @endif
        @if (! empty($indieningstermijnen['c']))C: <strong>{{ (int) $indieningstermijnen['c'] }} weken</strong>@endif
    </div>
@endif

@forelse ($sections as $section)
    <h3 style="margin-top: 1.5rem; font-size: 1rem; font-weight: 600;">{{ $section['title'] }}</h3>
    <table style="{{ $tableStyle }}">
        @foreach ($section['entries'] as $entry)
            @if (! empty($entry['table']))
                <tr>
                    <td colspan="2" style="padding: 0.5rem 0;">
                        <strong style="display: block; margin-bottom: 0.25rem;">{{ strip_tags((string) $entry['label']) }}</strong>
                        <table style="{{ $tijdenStyle }}">
                            <thead>
                                <tr>
                                    @foreach ($entry['table']['header'] as $kop)
                                        <th style="{{ $tijdenHeadStyle }}">{{ $kop }}</th>
                                    @endforeach
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($entry['table']['rows'] as $rij)
                                    <tr>
                                        @foreach ($rij as $i => $cel)
                                            @if ($i === 0)
                                                <th style="{{ $tijdenHeadStyle }}">{{ $cel }}</th>
                                            @else
                                                <td style="{{ $tijdenCellStyle }}">{{ $cel === '' ? '—' : $cel }}</td>
                                            @endif
                                        @endforeach
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </td>
                </tr>
            @elseif (! empty($entry['sub']))
                <tr>
                    <td colspan="2" style="padding: 0.5rem 0;">
                        <strong style="display: block; margin-bottom: 0.25rem;">{{ strip_tags((string) $entry['label']) }}</strong>
                        <table style="{{ $subTableStyle }}">
                            @foreach ($entry['sub'] as $subEntry)
                                <tr>
                                    <td style="{{ $subCellStyle }} color: #555; width: 40%;">{{ strip_tags((string) $subEntry['label']) }}</td>
                                    <td style="{{ $subCellStyle }}">
                                        @if (! empty($subEntry['svg']))
                                            {!! $subEntry['svg'] !!}
                                        @else
                                            {!! nl2br(e($subEntry['value'])) !!}
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </table>
                    </td>
                </tr>
            @else
                <tr>
                    <td style="{{ $labelStyle }}">{{ strip_tags((string) $entry['label']) }}</td>
                    <td style="{{ $valueStyle }}">
                        @if (! empty($entry['svg']))
                            {!! $entry['svg'] !!}
                        @elseif (! empty($entry['files']))
                            <ul style="margin: 0; padding-left: 1.1em;">
                                @foreach ($entry['files'] as $bestand)
                                    <li>{{ $bestand }}</li>
                                @endforeach
                            </ul>
                        @elseif (! empty($entry['list']))
                            @php $lijstTelt = count($entry['list']); @endphp
                            <ul style="margin: 0; padding-left: {{ $lijstTelt > 1 ? '1.1em' : '0' }}; list-style: {{ $lijstTelt > 1 ? 'disc' : 'none' }};">
                                @foreach ($entry['list'] as $item)
                                    <li>{{ $item }}</li>
                                @endforeach
                            </ul>
                        @else
                            {!! nl2br(e($entry['value'])) !!}
                        @endif
                    </td>
                </tr>
            @endif
        @endforeach
    </table>
@empty
    <p>U heeft nog geen velden ingevuld.</p>
@endforelse
