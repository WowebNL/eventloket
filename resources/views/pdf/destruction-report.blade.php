@php($isEventloketData = $report->type === \App\Enums\DestructionReportType::EventloketData)
<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="utf-8">
    <title>{{ $isEventloketData ? __('pdf/destruction-report.eventloket_data.title') : __('pdf/destruction-report.title') }} {{ $report->batch_number }}</title>
    <style>
        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 11px;
            color: #1f2937;
        }

        h1 {
            font-size: 18px;
            margin-bottom: 0;
        }

        h2 {
            font-size: 13px;
            margin-top: 24px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 8px;
        }

        th, td {
            border: 1px solid #d1d5db;
            padding: 4px 6px;
            text-align: left;
            vertical-align: top;
        }

        th {
            background-color: #f3f4f6;
        }

        .meta td {
            border: none;
            padding: 2px 6px 2px 0;
        }

        .meta td:first-child {
            font-weight: bold;
            width: 220px;
        }
    </style>
</head>
<body>
    <h1>{{ $isEventloketData ? __('pdf/destruction-report.eventloket_data.title') : __('pdf/destruction-report.title') }}</h1>
    <p>{{ $isEventloketData ? __('pdf/destruction-report.eventloket_data.intro') : __('pdf/destruction-report.intro') }}</p>

    <table class="meta">
        <tr>
            <td>{{ __('pdf/destruction-report.fields.batch_number') }}</td>
            <td>{{ $report->batch_number }}</td>
        </tr>
        <tr>
            <td>{{ __('pdf/destruction-report.fields.municipality') }}</td>
            <td>{{ $report->municipality->name }}</td>
        </tr>
        <tr>
            <td>{{ __('pdf/destruction-report.fields.destruction_date') }}</td>
            <td>{{ $report->destruction_date->format('d-m-Y H:i') }}</td>
        </tr>
        <tr>
            <td>{{ __('pdf/destruction-report.fields.destruction_method') }}</td>
            <td>{{ $report->destruction_method }}</td>
        </tr>
        @if($report->coordinator_name)
            <tr>
                <td>{{ __('pdf/destruction-report.fields.coordinator') }}</td>
                <td>{{ $report->coordinator_name }}@if($report->coordinator_function) ({{ $report->coordinator_function }})@endif</td>
            </tr>
        @endif
        <tr>
            <td>{{ __('pdf/destruction-report.fields.counts') }}</td>
            <td>
                @if($isEventloketData)
                    {{ __('pdf/destruction-report.eventloket_data.counts_value', ['total' => $report->total_count]) }}
                @else
                    {{ __('pdf/destruction-report.fields.counts_value', ['total' => $report->total_count, 'deleted' => $report->deleted_count, 'skipped' => $report->skipped_count, 'failed' => $report->failed_count]) }}
                @endif
            </td>
        </tr>
    </table>

    <h2>{{ $isEventloketData ? __('pdf/destruction-report.eventloket_data.items_heading') : __('pdf/destruction-report.items_heading') }}</h2>

    @if($isEventloketData)
        <table>
            <thead>
                <tr>
                    <th>{{ __('pdf/destruction-report.columns.zaaknummer') }}</th>
                    <th>{{ __('pdf/destruction-report.columns.zaaktype') }}</th>
                    <th>{{ __('pdf/destruction-report.fields.connection') }}</th>
                    <th>{{ __('pdf/destruction-report.fields.destroyed_at') }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($report->items as $item)
                    <tr>
                        <td>{{ $item['zaaknummer'] }}</td>
                        <td>{{ $item['zaaktype'] }}</td>
                        <td>{{ $item['zgw_connection'] }}</td>
                        <td>{{ \Illuminate\Support\Carbon::parse($item['destroyed_at'])->format('d-m-Y H:i') }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @else
        <table>
            <thead>
                <tr>
                    <th>{{ __('pdf/destruction-report.columns.zaaknummer') }}</th>
                    <th>{{ __('pdf/destruction-report.columns.zaaktype') }}</th>
                    <th>{{ __('pdf/destruction-report.columns.naam_evenement') }}</th>
                    <th>{{ __('pdf/destruction-report.columns.grondslag') }}</th>
                    <th>{{ __('pdf/destruction-report.columns.bewaartermijn') }}</th>
                    <th>{{ __('pdf/destruction-report.columns.archiefactiedatum') }}</th>
                    <th>{{ __('pdf/destruction-report.columns.status') }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($report->items as $item)
                    <tr>
                        <td>{{ $item['zaaknummer'] }}</td>
                        <td>{{ $item['zaaktype'] }}</td>
                        <td>{{ $item['naam_evenement'] }}</td>
                        <td>
                            {{ $item['selectielijst_categorie'] }}
                            @if(! empty($item['selectielijstklasse']))
                                <br><span style="font-size: 9px;">{{ $item['selectielijstklasse'] }}</span>
                            @endif
                        </td>
                        <td>{{ $item['bewaartermijn'] }}</td>
                        <td>{{ $item['archiefactiedatum'] }}</td>
                        <td>{{ __("enums/destruction_item_status.{$item['status']}.label") }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif
</body>
</html>
