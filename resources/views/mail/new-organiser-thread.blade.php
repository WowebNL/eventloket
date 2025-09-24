<x-mail::message>
# {{ __('mail/new-organiser-thread.greeting') }}

{{ __('mail/new-organiser-thread.body', ['organisation' => $organisation, 'municipality' => $municipality, 'event' => $event]) }}

<x-mail::panel>
{{ $title }}
</x-mail::panel>

<x-mail::button :url="$viewUrl">
    {{ __('mail/new-organiser-thread.button') }}
</x-mail::button>
</x-mail::message>
