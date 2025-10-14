<x-mail::message>
# {{ __('notification/zaak-status-changed.mail.greeting', ['event' => $event]) }}

{{
    __('notification/zaak-status-changed.mail.body', [
        'event' => $event,
        'municipality' => $municipality,
        'old_status' => $oldStatus,
        'new_status' => $newStatus
    ])
}}

<x-mail::button :url="$viewUrl">
    {{ __('notification/zaak-status-changed.mail.button') }}
</x-mail::button>
</x-mail::message>
