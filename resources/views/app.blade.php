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

    @php
        $user = auth()->user();
        $primary = $user?->organisation?->primary_color;
        $secondary = $user?->organisation?->secondary_color;
    @endphp

    @if($primary || $secondary)
    <style>
        :root {
            @if($primary)
            --color-teal-400: {{ $primary }} !important; /* light approximation */
            --color-teal-500: {{ $primary }} !important;
            --color-teal-600: {{ $primary }} !important;
            --color-teal-700: {{ $primary }} !important;
            @endif

            @if($secondary)
            --color-indigo-400: {{ $secondary }} !important;
            --color-indigo-500: {{ $secondary }} !important;
            --color-indigo-600: {{ $secondary }} !important;
            --color-indigo-700: {{ $secondary }} !important;
            @endif
        }
        
        /* Direct class overrides to guarantee Tailwind precedence */
        @if($primary)
        .bg-teal-600, .bg-teal-500 { background-color: {{ $primary }} !important; }
        .text-teal-600, .text-teal-500 { color: {{ $primary }} !important; }
        .ring-teal-500 { --tw-ring-color: {{ $primary }} !important; }
        .border-teal-500, .border-teal-600 { border-color: {{ $primary }} !important; }
        @endif

        @if($secondary)
        .bg-indigo-600, .bg-indigo-500 { background-color: {{ $secondary }} !important; }
        .text-indigo-600, .text-indigo-500 { color: {{ $secondary }} !important; }
        @endif
    </style>
    @endif
</head>
<body class="bg-gray-50 text-gray-900 font-sans antialiased">
    <div id="app"></div>
</body>
</html>
