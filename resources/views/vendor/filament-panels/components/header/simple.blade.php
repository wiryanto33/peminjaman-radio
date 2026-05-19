@props([
    'heading' => null,
    'logo' => true,
    'subheading' => null,
])

<header class="fi-simple-header flex flex-col items-center">
    @if ($logo)
        <x-filament-panels::logo class="mb-4" />
    @endif

    @php
        $authAppTitle = null;
        try {
            if (\Illuminate\Support\Facades\Schema::hasTable('settings')) {
                $authAppTitle = app(\App\Settings\KaidoSetting::class)->auth_app_title;
            }
        } catch (\Exception $e) {}
    @endphp

    @if (filled($authAppTitle))
        <h2 class="text-center text-xl font-bold tracking-tight text-gray-950 dark:text-white mb-4">
            {{ $authAppTitle }}
        </h2>
    @endif

    @if (filled($heading))
        <h1
            class="fi-simple-header-heading text-center text-2xl font-bold tracking-tight text-gray-950 dark:text-white"
        >
            {{ $heading }}
        </h1>
    @endif

    @if (filled($subheading))
        <p
            class="fi-simple-header-subheading mt-2 text-center text-sm text-gray-500 dark:text-gray-400"
        >
            {{ $subheading }}
        </p>
    @endif
</header>
