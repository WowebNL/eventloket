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
      <a href="#" class="-m-1.5 p-1.5">
        <span class="sr-only">{{ config('app.name') }}</span>
        <img src="{{ asset('images/logos/logo-dark.svg') }}" alt="" class="h-8 w-auto" />
      </a>
    </div>
    <div class="hidden lg:flex lg:flex-1 lg:justify-end lg:gap-x-6">
        <a href="#" class="py-2 px-2.5 text-sm/6 font-semibold text-white bg-indigo-600 hover:bg-indigo-500 rounded">Maak account aan</a>
      <a href="#" class="py-2 px-2.5 text-sm/6 font-semibold text-gray-900">Log in <span aria-hidden="true">&rarr;</span></a>
    </div>
  </nav>
</header>

<div class="relative isolate overflow-hidden bg-white px-6 py-24 sm:py-32 lg:overflow-visible lg:px-0">
  <div class="absolute inset-0 -z-10 overflow-hidden">
    <svg aria-hidden="true" class="absolute top-0 left-[max(50%,25rem)] h-256 w-512 -translate-x-1/2 mask-[radial-gradient(64rem_64rem_at_top,white,transparent)] stroke-gray-200">
      <defs>
        <pattern id="e813992c-7d03-4cc4-a2bd-151760b470a0" width="200" height="200" x="50%" y="-1" patternUnits="userSpaceOnUse">
          <path d="M100 200V.5M.5 .5H200" fill="none" />
        </pattern>
      </defs>
      <svg x="50%" y="-1" class="overflow-visible fill-gray-50">
        <path d="M-100.5 0h201v201h-201Z M699.5 0h201v201h-201Z M499.5 400h201v201h-201Z M-300.5 600h201v201h-201Z" stroke-width="0" />
      </svg>
      <rect width="100%" height="100%" fill="url(#e813992c-7d03-4cc4-a2bd-151760b470a0)" stroke-width="0" />
    </svg>
  </div>
  <div class="mx-auto grid max-w-2xl grid-cols-1 gap-x-8 gap-y-16 lg:mx-0 lg:max-w-none lg:grid-cols-2 lg:items-start lg:gap-y-10">
    <div class="lg:col-span-2 lg:col-start-1 lg:row-start-1 lg:mx-auto lg:grid lg:w-full lg:max-w-7xl lg:grid-cols-2 lg:gap-x-8 lg:px-8">
      <div class="lg:pr-4">
        <div class="lg:max-w-lg">
          <p class="text-base/7 font-semibold text-indigo-600">Snel en eenvoudig uw evenement regelen in Zuid-Limburg</p>
          <h1 class="mt-2 text-4xl font-semibold tracking-tight text-pretty text-gray-900 sm:text-5xl">{{  config('app.name') }}</h1>
                  <div class="mt-6 flex items-center gap-x-6">
          <a href="#" class="rounded-md bg-indigo-600 px-3.5 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600">Maak account aan</a>
          <a href="#" class="text-sm/6 font-semibold text-gray-900">Log in<span aria-hidden="true">→</span></a>
        </div>
          <p class="mt-6 text-xl/8 text-gray-700">Aliquet nec orci mattis amet quisque ullamcorper neque, nibh sem. At arcu, sit dui mi, nibh dui, diam eget aliquam. Quisque id at vitae feugiat egestas.</p>
        </div>
      </div>
    </div>
    <div class="-mt-12 -ml-12 p-12 lg:sticky lg:top-4 lg:col-start-2 lg:row-span-2 lg:row-start-1 lg:overflow-hidden">
      <img src="{{ asset('images/screenshot-temporary.png') }}" alt="" class="w-3xl max-w-none rounded-xl bg-gray-900 shadow-xl ring-1 ring-gray-400/10 sm:w-228" />
    </div>
    <div class="lg:col-span-2 lg:col-start-1 lg:row-start-2 lg:mx-auto lg:grid lg:w-full lg:max-w-7xl lg:grid-cols-2 lg:gap-x-8 lg:px-8">
      <div class="lg:pr-4">
        <div class="max-w-xl text-base/7 text-gray-600 lg:max-w-lg">
          <p>Faucibus commodo massa rhoncus, volutpat. Dignissim sed eget risus enim. Mattis mauris semper sed amet vitae sed turpis id. Id dolor praesent donec est. Odio penatibus risus viverra tellus varius sit neque erat velit. Faucibus commodo massa rhoncus, volutpat. Dignissim sed eget risus enim. Mattis mauris semper sed amet vitae sed turpis id.</p>
          <ul role="list" class="mt-8 space-y-8 text-gray-600">
            <li class="flex gap-x-3">
                <svg  xmlns="http://www.w3.org/2000/svg" class="mt-1 size-5 flex-none text-indigo-600" width="24"  height="24"  viewBox="0 0 24 24"  fill="none"  stroke="currentColor"  stroke-width="2"  stroke-linecap="round"  stroke-linejoin="round"  class="icon icon-tabler icons-tabler-outline icon-tabler-license"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M15 21h-9a3 3 0 0 1 -3 -3v-1h10v2a2 2 0 0 0 4 0v-14a2 2 0 1 1 2 2h-2m2 -4h-11a3 3 0 0 0 -3 3v11" /><path d="M9 7l4 0" /><path d="M9 11l4 0" /></svg>
              <span><strong class="font-semibold text-gray-900">Evenement of vooraankondiging </strong> Eenvoudig en snel een evenementenvergunning aanvragen of een vooraankondiging doen voor de evenementenkalender.</span>
            </li>
            <li class="flex gap-x-3">
                <svg  xmlns="http://www.w3.org/2000/svg"  class="mt-1 size-5 flex-none text-indigo-600" width="24"  height="24"  viewBox="0 0 24 24"  fill="none"  stroke="currentColor"  stroke-width="2"  stroke-linecap="round"  stroke-linejoin="round"  class="icon icon-tabler icons-tabler-outline icon-tabler-progress"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M10 20.777a8.942 8.942 0 0 1 -2.48 -.969" /><path d="M14 3.223a9.003 9.003 0 0 1 0 17.554" /><path d="M4.579 17.093a8.961 8.961 0 0 1 -1.227 -2.592" /><path d="M3.124 10.5c.16 -.95 .468 -1.85 .9 -2.675l.169 -.305" /><path d="M6.907 4.579a8.954 8.954 0 0 1 3.093 -1.356" /></svg>
              <span><strong class="font-semibold text-gray-900">Voortgang inzien</strong> Anim aute id magna aliqua ad ad non deserunt sunt. Qui irure qui lorem cupidatat commodo.</span>
            </li>
            <li class="flex gap-x-3">
<svg  xmlns="http://www.w3.org/2000/svg" class="mt-1 size-5 flex-none text-indigo-600" width="24"  height="24"  viewBox="0 0 24 24"  fill="none"  stroke="currentColor"  stroke-width="2"  stroke-linecap="round"  stroke-linejoin="round"  class="icon icon-tabler icons-tabler-outline icon-tabler-users"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M9 7m-4 0a4 4 0 1 0 8 0a4 4 0 1 0 -8 0" /><path d="M3 21v-2a4 4 0 0 1 4 -4h4a4 4 0 0 1 4 4v2" /><path d="M16 3.13a4 4 0 0 1 0 7.75" /><path d="M21 21v-2a4 4 0 0 0 -3 -3.85" /></svg>
              <span><strong class="font-semibold text-gray-900">Contact met behandelaar</strong> Ac tincidunt sapien vehicula erat auctor pellentesque rhoncus. Et magna sit morbi lobortis.</span>
            </li>
            <li class="flex gap-x-3">
<svg  xmlns="http://www.w3.org/2000/svg" class="mt-1 size-5 flex-none text-indigo-600" width="24"  height="24"  viewBox="0 0 24 24"  fill="none"  stroke="currentColor"  stroke-width="2"  stroke-linecap="round"  stroke-linejoin="round"  class="icon icon-tabler icons-tabler-outline icon-tabler-calendar"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M4 7a2 2 0 0 1 2 -2h12a2 2 0 0 1 2 2v12a2 2 0 0 1 -2 2h-12a2 2 0 0 1 -2 -2v-12z" /><path d="M16 3v4" /><path d="M8 3v4" /><path d="M4 11h16" /><path d="M11 15h1" /><path d="M12 15v3" /></svg>
              <span><strong class="font-semibold text-gray-900">Evenementenkalender</strong> Ac tincidunt sapien vehicula erat auctor pellentesque rhoncus. Et magna sit morbi lobortis.</span>
            </li>
          </ul>
          <p class="mt-8">Et vitae blandit facilisi magna lacus commodo. Vitae sapien duis odio id et. Id blandit molestie auctor fermentum dignissim. Lacus diam tincidunt ac cursus in vel. Mauris varius vulputate et ultrices hac adipiscing egestas. Iaculis convallis ac tempor et ut. Ac lorem vel integer orci.</p>
          <h2 class="mt-16 text-2xl font-bold tracking-tight text-gray-900">Lorem impsum</h2>
          <p class="mt-6">Id orci tellus laoreet id ac. Dolor, aenean leo, ac etiam consequat in. Convallis arcu ipsum urna nibh. Pharetra, euismod vitae interdum mauris enim, consequat vulputate nibh. Maecenas pellentesque id sed tellus mauris, ultrices mauris. Tincidunt enim cursus ridiculus mi. Pellentesque nam sed nullam sed diam turpis ipsum eu a sed convallis diam.</p>
        </div>
      </div>
    </div>
  </div>
</div>
                                                <section class="py-24">
      <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
        <div
          class="flex flex-col justify-center  gap-x-16 gap-y-5 xl:gap-28 lg:flex-row lg:justify-between max-lg:max-w-2xl mx-auto max-w-full"
        >
          <div class="w-full lg:w-1/2">
                      <p class="text-base/7 font-semibold text-indigo-600">Lorem ipsum</p>
          <h2 class="mt-2 text-4xl font-semibold tracking-tight text-pretty text-gray-900 sm:text-5xl">{{  __('Veel gestelde vragen') }}</h2>
          </div>
          <div class="w-full lg:w-1/2">
            <div class="lg:max-w-xl">
              <div class="accordion-group" data-accordion="default-accordion">
                <div
                  class=" pb-8 border-b border-solid border-gray-200 "
                >
                    <h5 class="leading-8 text-gray-600 text-xl">Lorem ipsum?</h5>
                    <p class="text-base font-normal text-gray-600 ">
                      To create an account, find the 'Sign up' or 'Create
                      account' button, fill out the registration form with your
                      personal information, and click 'Create account' or 'Sign
                      up.' Verify your email address if needed, and then log in
                      to start using the platform.
                    </p>
                </div>
                <div
                  class="accordion py-8 border-b border-solid border-gray-200 "
                  id="basic-heading-two-with-arrow-always-open"
                >
                  <button
                    class="accordion-toggle group inline-flex items-center justify-between font-normal text-xl leading-8 text-gray-600 w-full transition duration-500 hover:text-indigo-600 accordion-active:text-indigo-600 accordion-active:font-medium"
                    aria-controls="basic-collapse-two-with-arrow-always-open"
                  >
                    <h5>Have any trust issue?</h5>
                    <svg
                      class="text-gray-900 transition duration-500 group-hover:text-indigo-600 accordion-active:text-indigo-600 accordion-active:rotate-180"
                      width="22"
                      height="22"
                      viewBox="0 0 22 22"
                      fill="none"
                      xmlns="http://www.w3.org/2000/svg"
                    >
                      <path
                        d="M16.5 8.25L12.4142 12.3358C11.7475 13.0025 11.4142 13.3358 11 13.3358C10.5858 13.3358 10.2525 13.0025 9.58579 12.3358L5.5 8.25"
                        stroke="currentColor"
                        stroke-width="1.6"
                        stroke-linecap="round"
                        stroke-linejoin="round"
                      ></path>
                    </svg>
                  </button>
                  <div
                    id="basic-collapse-two-with-arrow-always-open"
                    class="accordion-content w-full px-0 overflow-hidden pr-4"
                    aria-labelledby="basic-heading-two-with-arrow-always-open"
                    style=""
                  >
                    <p class="text-base text-gray-500 font-normal">
                      Our focus on providing robust and user-friendly content
                      management capabilities ensures that you can manage your
                      content with confidence, and achieve your content
                      marketing goals with ease.
                    </p>
                  </div>
                </div>
                <div
                  class="accordion py-8 border-b border-solid border-gray-200"
                  id="basic-heading-three-with-arrow-always-open"
                >
                  <button
                    class="accordion-toggle group inline-flex items-center justify-between text-xl font-normal leading-8 text-gray-600 w-full transition duration-500 hover:text-indigo-600 accordion-active:font-medium accordion-active:text-indigo-600"
                    aria-controls="basic-collapse-three-with-arrow-always-open"
                  >
                    <h5>How can I reset my password?</h5>
                    <svg
                      class="text-gray-900 transition duration-500 group-hover:text-indigo-600 accordion-active:text-indigo-600 accordion-active:rotate-180"
                      width="22"
                      height="22"
                      viewBox="0 0 22 22"
                      fill="none"
                      xmlns="http://www.w3.org/2000/svg"
                    >
                      <path
                        d="M16.5 8.25L12.4142 12.3358C11.7475 13.0025 11.4142 13.3358 11 13.3358C10.5858 13.3358 10.2525 13.0025 9.58579 12.3358L5.5 8.25"
                        stroke="currentColor"
                        stroke-width="1.6"
                        stroke-linecap="round"
                        stroke-linejoin="round"
                      ></path>
                    </svg>
                  </button>
                  <div
                    id="basic-collapse-three-with-arrow-always-open"
                    class="accordion-content w-full px-0 overflow-hidden pr-4"
                    aria-labelledby="basic-heading-three-with-arrow-always-open"
                  >
                    <p class="text-base text-gray-500 font-normal">
                      Our focus on providing robust and user-friendly content
                      management capabilities ensures that you can manage your
                      content with confidence, and achieve your content
                      marketing goals with ease.
                    </p>
                  </div>
                </div>
                <div
                  class="accordion py-8 "
                  id="basic-heading-four-with-arrow-always-open"
                >
                  <button
                    class="accordion-toggle group inline-flex items-center justify-between text-xl font-normal leading-8 text-gray-600 w-full transition duration-500 hover:text-indigo-600 accordion-active:font-medium accordion-active:text-indigo-600"
                    aria-controls="basic-collapse-four-with-arrow-always-open"
                  >
                    <h5>What is the payment process?</h5>
                    <svg
                      class="text-gray-900 transition duration-500 group-hover:text-indigo-600 accordion-active:text-indigo-600 accordion-active:rotate-180"
                      width="22"
                      height="22"
                      viewBox="0 0 22 22"
                      fill="none"
                      xmlns="http://www.w3.org/2000/svg"
                    >
                      <path
                        d="M16.5 8.25L12.4142 12.3358C11.7475 13.0025 11.4142 13.3358 11 13.3358C10.5858 13.3358 10.2525 13.0025 9.58579 12.3358L5.5 8.25"
                        stroke="currentColor"
                        stroke-width="1.6"
                        stroke-linecap="round"
                        stroke-linejoin="round"
                      ></path>
                    </svg>
                  </button>
                  <div
                    id="basic-collapse-four-with-arrow-always-open"
                    class="accordion-content w-full px-0 overflow-hidden pr-4"
                    aria-labelledby="basic-heading-four-with-arrow-always-open"
                  >
                    <p class="text-base text-gray-500 font-normal">
                      Our focus on providing robust and user-friendly content
                      management capabilities ensures that you can manage your
                      content with confidence, and achieve your content
                      marketing goals with ease.
                    </p>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </section>
           

<footer class="bg-white rounded-lg shadow-sm m-4 dark:bg-gray-800">
    <div class="w-full mx-auto max-w-screen-xl p-4 md:flex md:items-center md:justify-between">
      <span class="text-sm text-gray-500 sm:text-center dark:text-gray-400">© {{ date('Y') }} {{ config('app.name') }}. 
    </span>
    <ul class="flex flex-wrap items-center mt-3 text-sm font-medium text-gray-500 dark:text-gray-400 sm:mt-0">
        <li>
            <a href="#" class="hover:underline me-4 md:me-6">Adviesdienst</a>
        </li>
        <li>
            <a href="#" class="hover:underline me-4 md:me-6">Behandelaar</a>
        </li>
    </ul>
    </div>
</footer>
                                 


    </body>
</html>
