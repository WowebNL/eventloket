<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        <title>{{ config('app.name') }}</title>

        <!-- Fonts -->
        <link href="{{ asset('css/fonts/satoshi.css') }}" rel="stylesheet" />

        <!-- Styles / Scripts -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="">
<header class="bg-white">
  <nav aria-label="Global" class="mx-auto flex max-w-7xl items-center justify-between p-6 lg:px-8">
    <div class="flex lg:flex-1">
      <a href="/" class="-m-1.5 p-1.5">
        <span class="sr-only">{{ config('app.name') }}</span>
        <img src="{{ asset('images/logos/logo-dark.svg') }}" alt="" class="h-8 w-auto" />
      </a>
    </div>
    <div class="hidden lg:flex lg:flex-1 lg:justify-end lg:gap-x-6">
        <a href="{{ route('filament.organiser.auth.register') }}" class="py-2 px-2.5 text-sm/6 font-semibold text-white bg-blue-600 hover:bg-blue-500 rounded">{{ __('welcome.register.label') }}</a>
      <a href="{{ route('filament.organiser.auth.login') }}" class="py-2 px-2.5 text-sm/6 font-semibold text-gray-900">{{ __('welcome.log_in.label') }} <span aria-hidden="true">&rarr;</span></a>
    </div>
  </nav>
</header>

<div class="relative isolate overflow-hidden bg-white px-6 py-24 sm:py-32 lg:overflow-visible lg:px-0">
  <div class="mx-auto grid max-w-2xl grid-cols-1 gap-x-8 gap-y-16 lg:mx-0 lg:max-w-none lg:grid-cols-2 lg:items-start lg:gap-y-10">
    <div class="lg:col-span-2 lg:col-start-1 lg:row-start-1 lg:mx-auto lg:grid lg:w-full lg:max-w-7xl lg:grid-cols-2 lg:gap-x-8 lg:px-8">
      <div class="lg:pr-4">
        <div class="lg:max-w-lg">
          <p class="text-base/7 font-semibold text-blue-600">{{ $tagline }}</p>
          <h1 class="mt-2 text-4xl font-semibold tracking-tight text-pretty text-gray-900 sm:text-5xl">{{  $title }}</h1>
                  <div class="mt-6 flex items-center gap-x-6">
          <a href="{{ route('filament.organiser.auth.register') }}" class="rounded-md bg-blue-600 px-3.5 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-blue-500 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-blue-600">{{ __('welcome.register.label') }}</a>
          <a href="{{ route('filament.organiser.auth.login') }}" class="text-sm/6 font-semibold text-gray-900">{{ __('welcome.log_in.label') }}<span aria-hidden="true"> →</span></a>
        </div>
          <div class="[&>p]:mt-6 text-xl/8 text-gray-700 [&>p]:mt-3 [&_a]:text-blue-600 [&_a]:underline [&>h2]:text-4xl [&>h3]:text-2xl [&>h2,&>h3]:font-semibold [&>h2]:tracking-tight [&>h2,&>h3]:text-gray-900 [&>h2,&>h3]:mt-2 [&>blockquote]:p-4 [&>blockquote]:my-4 [&>blockquote]:border-s-4 [&>blockquote]:border-gray-300 [&>blockquote]:bg-gray-50 [&>blockquote]:dark:border-gray-500 [&>blockquote]:dark:bg-gray-800 [&>ul]:list-disc [&>ol]:list-decimal [&>ul,&>ol]:ms-5 [&>ul]:mt-3 [&>ol]:mt-3 [&>li]:mt-1">
            {!! str($intro)->sanitizeHtml() !!}
          </div>
        </div>
      </div>
    </div>
    <div class="-mt-12 -ml-12 p-12 lg:sticky lg:top-4 lg:col-start-2 lg:row-span-2 lg:row-start-1 lg:overflow-hidden">
      @if($preview_image)
        <img src="{{  asset('storage/' . $preview_image) }}" alt="" class="w-3xl max-w-none rounded-xl bg-gray-900 shadow-xl ring-1 ring-gray-400/10 sm:w-228" />
      @endif
    </div>
    @if($usps || $outro)
    <div class="lg:col-span-2 lg:col-start-1 lg:row-start-2 lg:mx-auto lg:grid lg:w-full lg:max-w-7xl lg:grid-cols-2 lg:gap-x-8 lg:px-8">
      <div class="lg:pr-4">
        <div class="max-w-xl text-base/7 text-gray-600 lg:max-w-lg">
          @if($usps)
          <ul role="list" class="mt-8 space-y-8 text-gray-600">
            @foreach($usps as $usp)
              <li class="flex gap-x-3">
                  <x-dynamic-component :component="$usp['icon']" class="mt-1 size-5 flex-none text-blue-600" />
                  <span ><strong class="font-semibold text-gray-900">{{ $usp['title'] }}</strong>
                 {{ $usp['description'] }}</span>
              </li>
            @endforeach
          </ul>
          @endif
          @if($outro)
            <div class="mt-8 [&>p]:mt-3 [&_a]:text-blue-600 [&_a]:underline [&>h2]:text-4xl [&>h3]:text-2xl [&>h2,&>h3]:font-semibold [&>h2]:tracking-tight [&>h2,&>h3]:text-gray-900 [&>h2,&>h3]:mt-2 [&>blockquote]:p-4 [&>blockquote]:my-4 [&>blockquote]:border-s-4 [&>blockquote]:border-gray-300 [&>blockquote]:bg-gray-50 [&>blockquote]:dark:border-gray-500 [&>blockquote]:dark:bg-gray-800 [&>ul]:list-disc [&>ol]:list-decimal [&>ul,&>ol]:ms-5 [&>ul]:mt-3 [&>ol]:mt-3 [&>li]:mt-1">
              {!! str($outro)->sanitizeHtml() !!}
            </div>
          @endif
        </div>
      </div>
    </div>
    @endif
  </div>
</div>
@if($faqs)
<section class="py-24">
      <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
        <div
          class="flex flex-col justify-center  gap-x-16 gap-y-5 xl:gap-28 lg:flex-row lg:justify-between max-lg:max-w-2xl mx-auto max-w-full"
        >
          <div class="w-full lg:w-1/2">
          <h2 class="mt-2 text-4xl font-semibold tracking-tight text-pretty text-gray-900 sm:text-5xl">{{  __('welcome.faq.title') }}</h2>
          </div>
          <div class="w-full lg:w-1/2">
            <div class="lg:max-w-xl">
              <div class="accordion-group" data-accordion="default-accordion">
                @foreach($faqs as $faq)
                <div
                  class=" pb-8 border-b border-solid border-gray-200 "
                >
                    <h5 class="leading-8 text-gray-600 text-xl mt-2">{{ $faq['question'] }}</h5>
                    <div class="text-base font-normal text-gray-600 [&>p]:mt-3 [&_a]:text-blue-600 [&_a]:underline [&>h2]:text-4xl [&>h3]:text-2xl [&>h2,&>h3]:font-semibold [&>h2]:tracking-tight [&>h2,&>h3]:text-gray-900 [&>h2,&>h3]:mt-2 [&>blockquote]:p-4 [&>blockquote]:my-4 [&>blockquote]:border-s-4 [&>blockquote]:border-gray-300 [&>blockquote]:bg-gray-50 [&>blockquote]:dark:border-gray-500 [&>blockquote]:dark:bg-gray-800 [&>ul]:list-disc [&>ol]:list-decimal [&>ul,&>ol]:ms-5 [&>ul]:mt-3 [&>ol]:mt-3 [&>li]:mt-1">
                      {!! str($faq['answer'])->sanitizeHtml() !!}
                    </div>
                </div>
                @endforeach

              </div>
            </div>
          </div>
        </div>
      </div>
    </section>
    @endif


<footer class="bg-white rounded-lg shadow-sm m-4 dark:bg-gray-800">
    <div class="w-full mx-auto max-w-screen-xl p-4 md:flex md:items-center md:justify-between">
      <span class="text-sm text-gray-500 sm:text-center dark:text-gray-400">© {{ date('Y') }} {{ config('app.name') }}
    </span>
    <ul class="flex flex-wrap items-center mt-3 text-sm font-medium text-gray-500 dark:text-gray-400 sm:mt-0">
        <li>
            <a href="{{ route('filament.advisor.auth.login') }}" class="hover:underline me-4 md:me-6">{{ __('welcome.footer.advice_login.label') }}</a>
        </li>
        <li>
            <a href="{{ route('filament.municipality.auth.login') }}" class="hover:underline me-4 md:me-6">{{ __('welcome.footer.handler_login.label') }}</a>
        </li>
        <li>
            <a href="{{ route('filament.admin.auth.login') }}" class="hover:underline me-4 md:me-6">{{ __('welcome.footer.admin_login.label') }}</a>
        </li>
    </ul>
    </div>
</footer>



    </body>
</html>
