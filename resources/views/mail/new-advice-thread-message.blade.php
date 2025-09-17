<x-mail::message>
# {{ __('mail/new-advice-thread-message.greeting') }}

{{ __('mail/new-advice-thread-message.body', ['advisory' => $advisory, 'municipality' => $municipality, 'event' => $event]) }}

<x-mail::panel>
    {{ $title }}
</x-mail::panel>

<x-mail::button :url="$viewUrl">
    {{ __('mail/new-advice-thread-message.button') }}
</x-mail::button>
</x-mail::message>
