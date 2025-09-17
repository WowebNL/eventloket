<x-dynamic-component
    :component="$getEntryWrapperView()"
    :entry="$entry"
>
    <div {{ $getExtraAttributeBag() }}>
        @if(!$getState())
            <p>Er zijn nog geen berichten</p>
        @else
            <ul role="list" class="space-y-6">
                @foreach ($getState() as $message)
                    <li>
                        <x-filament::section id="message-{{ $message->id }}">
                            <div>
                                <div class="flex space-x-3">
                                    <div class="shrink-0">
                                        <img
                                            src="{{ \Filament\Facades\Filament::getUserAvatarUrl($message->user) }}"
                                            alt="{{ $message->user->name }} avatar"
                                            class="size-10 rounded-full"
                                        />
                                    </div>
                                    <div class="min-w-0 flex-1">
                                        <p class="text-sm font-medium text-gray-900">
                                            {{ $message->user->name }}
                                        </p>
                                        <p class="text-sm text-gray-500">
                                            @switch($message->user->role)
                                                @case(\App\Enums\Role::Admin)
                                                    Platformbeheerder
                                                    @break

                                                @case(\App\Enums\Role::MunicipalityAdmin)
                                                @case(\App\Enums\Role::ReviewerMunicipalityAdmin)
                                                    Gemeentelijk beheerder
                                                    bij {{ $message->user->municipalities->pluck('name')->join(', ') }}
                                                    @break

                                                @case(\App\Enums\Role::Reviewer)
                                                    Behandelaar
                                                    bij {{ $message->user->municipalities->pluck('name')->join(', ') }}
                                                    @break

                                                @case(\App\Enums\Role::Advisor)
                                                    Adviseur bij {{ $message->user->advisories->pluck('name')->join(', ') }}
                                                    @break

                                                @case(\App\Enums\Role::Organiser)
                                                    Organisator
                                                    bij {{ $message->user->organisations->pluck('name')->join(', ') }}
                                                    @break

                                                @default
                                                    Default case...
                                            @endswitch
                                        </p>
                                    </div>
                                    <div>
                                        <time datetime="{{ $message->created_at }}">
                                            {{ $message->created_at->format('M d H:m') }}
                                        </time>
                                    </div>
                                </div>
                            </div>
                            <div class="mt-4 prose prose-sm max-w-none">
                                {!! str($message->body)->sanitizeHtml() !!}
                            </div>
                        </x-filament::section>
                    </li>
                @endforeach
            </ul>
        @endif

        <div class="my-6">
            <livewire:thread.message-form :thread="$record"/>
        </div>
    </div>
</x-dynamic-component>
