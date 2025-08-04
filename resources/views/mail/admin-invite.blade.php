<x-mail::message>
# {{ __('mail/admin-invite.greeting', ['role' => $role]) }}

{{ __('mail/admin-invite.body', ['role' => $role, 'name' => $municipalities->pluck('name')->join(', ')]) }}

<x-mail::button :url="$acceptUrl">
{{ __('mail/admin-invite.button') }}
</x-mail::button>
</x-mail::message>
