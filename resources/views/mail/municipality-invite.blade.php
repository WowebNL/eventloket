<x-mail::message>
# {{ __('mail/municipality-invite.greeting', ['role' => $role]) }}

{{ __('mail/municipality-invite.body', ['role' => $role, 'name' => $municipalities->pluck('name')->join(', ')]) }}

<x-mail::button :url="$acceptUrl">
{{ __('mail/municipality-invite.button') }}
</x-mail::button>
</x-mail::message>
