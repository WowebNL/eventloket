<x-filament-widgets::widget>
    <x-filament::section>
        <div class="[&>p]:mt-3 [&_a]:text-blue-600 [&_a]:underline [&>h2]:text-4xl [&>h3]:text-2xl [&>h2,&>h3]:font-semibold [&>h2]:tracking-tight [&>h2,&>h3]:text-gray-900 [&>h2,&>h3]:mt-2 [&>blockquote]:p-4 [&>blockquote]:my-4 [&>blockquote]:border-s-4 [&>blockquote]:border-gray-300 [&>blockquote]:bg-gray-50 [&>blockquote]:dark:border-gray-500 [&>blockquote]:dark:bg-gray-800 [&>ul]:list-disc [&>ol]:list-decimal [&>ul,&>ol]:ms-5 [&>ul]:mt-3 [&>ol]:mt-3 [&>li]:mt-1">
        {!! str($introContent)->sanitizeHtml() !!}
        </div>
    </x-filament::section>
</x-filament-widgets::widget>
