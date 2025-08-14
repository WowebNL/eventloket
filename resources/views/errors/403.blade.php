@extends('errors::custom-layout')

@section('title', __('Forbidden'))
@section('code', '403')
@if(auth()->check() )
    @section('message')
    <small>Bevraagde url: {{ request()->fullUrl() }}</small>
        <br>{{ __('errors/403.message') }}
        <div class="mt-3 grid gap-y-3">
            <a href="{{ route('welcome') }}" class="rounded-md bg-blue-600 px-3.5 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-blue-500 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-blue-600">{{ __('errors/403.home') }}</a>
            <a href="{{ route('filament.admin.tenant') }}" class="rounded-md bg-blue-600 px-3.5 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-blue-500 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-blue-600">{{ __('errors/403.admin') }}</a>
            <a href="{{ route('filament.advisor.tenant') }}" class="rounded-md bg-blue-600 px-3.5 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-blue-500 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-blue-600">{{ __('errors/403.advisor') }}</a>
            <a href="{{ route('filament.organiser.tenant') }}" class="rounded-md bg-blue-600 px-3.5 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-blue-500 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-blue-600">{{ __('errors/403.organiser') }}</a>

        </div>

    @endsection
@else
    @section('message', __($exception->getMessage() ?: 'Forbidden'))
@endif