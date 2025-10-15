<x-mail::message>
# {{ __('notification/new-advice-thread-message.mail.greeting') }}

{{ __('notification/new-advice-thread-message.mail.body', ['sender' => $sender, 'advisory' => $advisory, 'event' => $event]) }}

<x-mail::panel>
    {{ $title }}
</x-mail::panel>

<x-mail::button :url="$viewUrl">
    {{ __('notification/new-advice-thread-message.mail.button') }}
</x-mail::button>
</x-mail::message>
