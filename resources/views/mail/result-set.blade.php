<x-mail::message>

{!! str($content)->sanitizeHtml() !!}

<x-mail::button :url="$url">
{{ __('Bekijk in :app_name', ['app_name' => config('app.name')]) }}
</x-mail::button>
</x-mail::message>
