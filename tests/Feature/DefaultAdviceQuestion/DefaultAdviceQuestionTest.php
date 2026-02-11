<?php

use App\Models\Advisory;
use App\Models\DefaultAdviceQuestion;
use App\Models\Municipality;

beforeEach(function () {
    $this->municipality = Municipality::factory()->create();
    $this->advisory = Advisory::factory()->create();
    $this->advisory->municipalities()->attach($this->municipality);
});

test('can create default advice question', function () {
    $question = DefaultAdviceQuestion::create([
        'municipality_id' => $this->municipality->id,
        'advisory_id' => $this->advisory->id,
        'risico_classificatie' => 'A',
        'title' => 'Test Question',
        'description' => 'Test Description',
        'response_deadline_days' => 14,
    ]);

    expect($question)
        ->municipality_id->toBe($this->municipality->id)
        ->advisory_id->toBe($this->advisory->id)
        ->risico_classificatie->toBe('A')
        ->title->toBe('Test Question')
        ->description->toBe('Test Description')
        ->response_deadline_days->toBe(14);
});

test('belongs to municipality', function () {
    $question = DefaultAdviceQuestion::factory()->create([
        'municipality_id' => $this->municipality->id,
    ]);

    expect($question->municipality)
        ->toBeInstanceOf(Municipality::class)
        ->id->toBe($this->municipality->id);
});

test('belongs to advisory', function () {
    $question = DefaultAdviceQuestion::factory()->create([
        'advisory_id' => $this->advisory->id,
    ]);

    expect($question->advisory)
        ->toBeInstanceOf(Advisory::class)
        ->id->toBe($this->advisory->id);
});

test('can filter by municipality and risk classification', function () {
    $municipality2 = Municipality::factory()->create();

    DefaultAdviceQuestion::factory()->create([
        'municipality_id' => $this->municipality->id,
        'risico_classificatie' => 'A',
    ]);

    DefaultAdviceQuestion::factory()->create([
        'municipality_id' => $this->municipality->id,
        'risico_classificatie' => 'B',
    ]);

    DefaultAdviceQuestion::factory()->create([
        'municipality_id' => $municipality2->id,
        'risico_classificatie' => 'A',
    ]);

    $questions = DefaultAdviceQuestion::query()
        ->where('municipality_id', $this->municipality->id)
        ->where('risico_classificatie', 'A')
        ->get();

    expect($questions)->toHaveCount(1)
        ->first()->risico_classificatie->toBe('A');
});

test('deletes when municipality is deleted', function () {
    $question = DefaultAdviceQuestion::factory()->create([
        'municipality_id' => $this->municipality->id,
    ]);

    $this->municipality->delete();

    expect(DefaultAdviceQuestion::find($question->id))->toBeNull();
});

test('deletes when advisory is force deleted', function () {
    $question = DefaultAdviceQuestion::factory()->create([
        'advisory_id' => $this->advisory->id,
    ]);

    $this->advisory->forceDelete();

    expect(DefaultAdviceQuestion::find($question->id))->toBeNull();
});
