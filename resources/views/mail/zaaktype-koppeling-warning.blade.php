<x-mail::message>
# {{ __("notification/zaaktype-koppeling-warning.mail.greeting.$issue") }}

{{ $body }}

@if (count($findingLines) > 0)
@foreach ($findingLines as $line)
- {{ $line }}
@endforeach
@endif

<x-mail::button :url="$viewUrl">
    {{ __('notification/zaaktype-koppeling-warning.mail.button') }}
</x-mail::button>
</x-mail::message>
