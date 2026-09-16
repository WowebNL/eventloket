<?php

namespace Database\Factories\Archiving;

use App\Enums\DestructionItemStatus;
use App\Models\Archiving\DestructionList;
use App\Models\Archiving\DestructionListItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DestructionListItem>
 */
class DestructionListItemFactory extends Factory
{
    protected $model = DestructionListItem::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'destruction_list_id' => DestructionList::factory(),
            'zgw_zaak_url' => fake()->unique()->url(),
            'zaaknummer' => 'ZAAK-'.fake()->unique()->randomNumber(5),
            'zaaktype_naam' => 'Evenementenvergunning',
            'naam_evenement' => fake()->sentence(3),
            'archiefnominatie' => 'vernietigen',
            'archiefactiedatum' => now()->subDay(),
            'bewaartermijn' => 'P1Y',
            'status' => DestructionItemStatus::Pending,
        ];
    }
}
