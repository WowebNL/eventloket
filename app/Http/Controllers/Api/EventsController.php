<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\CheckEventsRequest;
use App\Models\Zaak;

class EventsController extends Controller
{
    public function check(CheckEventsRequest $request)
    {
        $data = $request->validated();
        $zaken = Zaak::whereBetween('reference_data->start_evenement', [$data['start_date'], $data['end_date']])
            ->orWhereBetween('reference_data->eind_evenement', [$data['start_date'], $data['end_date']])
            ->whereHas('municipality', function ($query) use ($data) {
                $query->where('brk_identification', $data['municipality']);
            })
            ->limit(10)->get();

        $zakenNameString = $zaken->isNotEmpty() ? $zaken->pluck('reference_data.naam_evenement')->join(', ') : '';

        return response()->json([
            'event_names' => $zakenNameString,
            'event_count' => $zaken->count(),
        ]);
    }
}
