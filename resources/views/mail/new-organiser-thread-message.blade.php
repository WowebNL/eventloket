<x-mail::message>
# {{ __('mail/new-organiser-thread-message.greeting') }}

{{ __('mail/new-organiser-thread-message.body', ['sender' => $sender, 'organisation' => $organisation, 'event' => $event]) }}

<x-mail::panel>
    {{ $title }}
</x-mail::panel>

<x-mail::button :url="$viewUrl">
    {{ __('mail/new-organiser-thread-message.button') }}
</x-mail::button>
</x-mail::message>
