<x-filament-panels::page>
    {{ $this->table }}

    <p class="fi-ta-text text-sm" style="color: var(--gray-500);">
        Concepten die {{ \App\EventForm\Persistence\DraftStore::EXPIRY_MONTHS }} maanden niet bewerkt zijn, worden automatisch verwijderd.
    </p>
</x-filament-panels::page>
