<x-mail::message>
# {{ __('mail/new-advice-thread.greeting') }}

{{ __('mail/new-advice-thread.body', ['advisory' => $advisory, 'municipality' => $municipality, 'event' => $event]) }}

<x-mail::panel>
{{ $title }}
</x-mail::panel>

<x-mail::button :url="$viewUrl">
    {{ __('mail/new-advice-thread.button') }}
</x-mail::button>
</x-mail::message>
