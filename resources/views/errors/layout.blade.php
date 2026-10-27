@php
    use App\Support\Locales;

    // An unmatched URL never reaches the locale middleware, so the language is
    // taken from the first path segment when it names a supported locale.
    $segment = request()->segment(1);
    $locale = Locales::isSupported($segment) ? $segment : app()->getLocale();
    app()->setLocale($locale);
@endphp
<!DOCTYPE html>
<html lang="{{ Locales::htmlLang($locale) }}" dir="{{ Locales::direction($locale) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>@yield('title') — {{ config('app.name') }}</title>
    @vite(['resources/css/app.css'])
</head>
<body class="grid min-h-screen place-items-center bg-surface px-4 text-ink">
    <main class="w-full max-w-md text-center">
        <p class="numeric text-5xl font-bold text-brand-700">@yield('code')</p>
        <h1 class="mt-4 text-xl font-semibold">@yield('title')</h1>
        {{-- Only this message is shown: exception details stay in the log. --}}
        <p class="mt-2 text-sm text-ink-muted">@yield('message')</p>

        <a
            href="{{ url('/'.$locale) }}"
            class="mt-8 inline-block rounded-lg bg-brand-700 px-5 py-2.5 text-sm font-medium text-white transition hover:bg-brand-800"
        >
            {{ __('storefront.errors.back_home') }}
        </a>
    </main>
</body>
</html>
