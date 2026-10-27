@php
    use App\Support\Locales;

    $locale = app()->getLocale();
    $seo = data_get($page, 'props.seo', []);
    $site = data_get($page, 'props.site', []);

    $siteName = data_get($site, 'name', config('app.name'));
    $title = data_get($seo, 'title');
    $fullTitle = filled($title) && $title !== $siteName ? $title.' — '.$siteName : $siteName;
    $description = data_get($seo, 'description') ?? data_get($site, 'meta_description');
    $canonical = data_get($seo, 'canonical', url()->current());
    $alternates = data_get($seo, 'alternates', []);
    $image = data_get($seo, 'image');
    $schema = data_get($seo, 'schema');
@endphp
<!DOCTYPE html>
<html lang="{{ Locales::htmlLang($locale) }}" dir="{{ Locales::direction($locale) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    {{-- Rendered on the server so that crawlers and link previews, which do
         not run JavaScript, still receive the metadata of this exact URL. --}}
    <title>{{ $fullTitle }}</title>
    @if ($description)
        <meta name="description" content="{{ $description }}">
    @endif
    <link rel="canonical" href="{{ $canonical }}">

    @foreach ($alternates as $code => $href)
        <link rel="alternate" hreflang="{{ $code }}" href="{{ $href }}">
    @endforeach
    @if (! empty($alternates))
        <link rel="alternate" hreflang="x-default" href="{{ $alternates[Locales::fallback()] ?? $canonical }}">
    @endif

    <meta property="og:type" content="website">
    <meta property="og:site_name" content="{{ $siteName }}">
    <meta property="og:title" content="{{ $fullTitle }}">
    @if ($description)
        <meta property="og:description" content="{{ $description }}">
    @endif
    <meta property="og:url" content="{{ $canonical }}">
    <meta property="og:locale" content="{{ $locale }}">
    @if ($image)
        <meta property="og:image" content="{{ $image }}">
        <meta name="twitter:card" content="summary_large_image">
    @else
        <meta name="twitter:card" content="summary">
    @endif

    @if ($schema)
        <script type="application/ld+json">{!! json_encode($schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}</script>
    @endif

    <link rel="icon" href="/favicon.ico" sizes="any">

    @vite(['resources/css/app.css', 'resources/js/app.ts'])
    @inertiaHead
</head>
<body class="min-h-screen bg-surface text-ink antialiased">
    @inertia
</body>
</html>
