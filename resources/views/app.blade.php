<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>WorkTrack Pro</title>

    <!-- Google Fonts for typography (Inter) -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">

    <!-- CSS & JS Assets (Vite) -->
    @vite(['resources/css/app.css', 'resources/js/app.js'])

    {{-- The organisation's brand colours before the app loads (the app refreshes them once it knows who is signed in). --}}
    <style id="org-theme-initial">{!! \App\Support\Branding::css(\App\Support\Branding::organisationFor(auth()->user())) !!}</style>
</head>
<body class="bg-gray-50 text-gray-900 font-sans antialiased">
    <div id="app"></div>
</body>
</html>
