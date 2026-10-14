<?php

namespace Database\Factories;

use App\Enums\MunicipalityFormQuestionType;
use App\Models\Municipality;
use App\Models\MunicipalityFormQuestion;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MunicipalityFormQuestion>
 */
class MunicipalityFormQuestionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'municipality_id' => Municipality::factory(),
            'order' => 1,
            'type' => MunicipalityFormQuestionType::Text,
            'label' => fake()->sentence().'?',
            'helper_text' => null,
            'options' => null,
            'is_required' => false,
            'is_active' => true,
            'show_for_aanvraag_types' => null,
        ];
    }

    /**
     * @param  list<string>  $options
     */
    public function radio(array $options = ['Ja', 'Nee']): static
    {
        return $this->state(fn (): array => [
            'type' => MunicipalityFormQuestionType::Radio,
            'options' => $options,
        ]);
    }

    /**
     * @param  list<string>  $options
     */
    public function checkboxes(array $options = ['Optie A', 'Optie B']): static
    {
        return $this->state(fn (): array => [
            'type' => MunicipalityFormQuestionType::Checkboxes,
            'options' => $options,
        ]);
    }

    public function required(): static
    {
        return $this->state(fn (): array => ['is_required' => true]);
    }

    public function inactive(): static
    {
        return $this->state(fn (): array => ['is_active' => false]);
    }

    /**
     * @param  list<string>  $types
     */
    public function forAanvraagTypes(array $types): static
    {
        return $this->state(fn (): array => ['show_for_aanvraag_types' => $types]);
    }
}
