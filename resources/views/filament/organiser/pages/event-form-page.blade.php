<x-filament-panels::page>
    {{--
        Autosave-indicator, direct onder de paginatitel zodat de
        organisator meteen ziet dat z'n antwoorden bewaard blijven.
        `savedAt` start met de laatste save van het geladen concept en
        wordt bijgewerkt via het `event-form-draft-saved`-browser-event
        dat persistDraft() dispatcht.
    --}}
    <div
        x-data="{ savedAt: @js($this->lastSavedLabel) }"
        x-on:event-form-draft-saved.window="savedAt = $event.detail.time"
        class="event-form-autosave"
    >
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="event-form-autosave-icon" aria-hidden="true">
            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.857-9.809a.75.75 0 00-1.214-.882l-3.483 4.79-1.88-1.88a.75.75 0 10-1.06 1.061l2.5 2.5a.75.75 0 001.137-.089l4-5.5z" clip-rule="evenodd" />
        </svg>
        <span x-show="savedAt" x-cloak>Automatisch opgeslagen om <span x-text="savedAt"></span></span>
        <span x-show="! savedAt">Uw antwoorden worden automatisch opgeslagen</span>
        <x-filament::icon-button
            icon="heroicon-o-information-circle"
            color="gray"
            size="sm"
            label="Uitleg over automatisch opslaan"
            x-on:click="$dispatch('open-modal', { id: 'autosave-uitleg' })"
        />
    </div>

    {{ $this->form }}

    <x-filament::modal id="autosave-uitleg" width="lg">
        <x-slot name="heading">
            Automatisch opslaan
        </x-slot>

        <div class="event-form-autosave-uitleg">
            <p>
                Uw antwoorden worden automatisch opgeslagen als concept. Dat gebeurt
                regelmatig tijdens het invullen, en in elk geval telkens wanneer u
                naar een volgende of vorige stap gaat. U hoeft dus niets te doen om
                uw voortgang te bewaren.
            </p>
            <p>
                Sluit u het formulier, gaat u naar een andere pagina of logt u uit?
                Dan kunt u later verdergaan waar u gebleven was. Via
                <strong>Nieuwe aanvraag</strong> in het menu vindt u al uw
                opgeslagen concepten terug en kiest u met welk concept u verdergaat.
            </p>
            <ul>
                <li>U kunt maximaal {{ \App\EventForm\Persistence\DraftStore::MAX_DRAFTS }} concepten tegelijk hebben, bijvoorbeeld voor meerdere evenementen.</li>
                <li>Concepten die {{ \App\EventForm\Persistence\DraftStore::EXPIRY_MONTHS }} maanden niet bewerkt zijn, worden automatisch verwijderd.</li>
                <li>Na het indienen van uw aanvraag wordt het concept opgeruimd.</li>
                <li>Wilt u dit concept weggooien? Gebruik dan <strong>Concept verwijderen</strong> bovenaan deze pagina.</li>
            </ul>
        </div>
    </x-filament::modal>
</x-filament-panels::page>

@push('styles')
    <style>
        .event-form-autosave {
            display: flex;
            align-items: center;
            gap: 0.375rem;
            font-size: 0.875rem;
            color: var(--gray-500);
            /* Compenseer de standaard sectie-gap van de page zodat de
               indicator dicht onder de titel hangt. */
            margin-top: -0.5rem;
        }

        .event-form-autosave-icon {
            width: 1.125rem;
            height: 1.125rem;
            flex: 0 0 auto;
            color: var(--gray-400);
        }

        .event-form-autosave-uitleg {
            display: flex;
            flex-direction: column;
            gap: 0.75rem;
            font-size: 0.875rem;
            color: var(--gray-600);
        }

        :is(.dark .event-form-autosave-uitleg) {
            color: var(--gray-300);
        }

        .event-form-autosave-uitleg ul {
            list-style: disc;
            padding-inline-start: 1.25rem;
            display: flex;
            flex-direction: column;
            gap: 0.375rem;
        }
    </style>
@endpush
