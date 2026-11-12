@extends('docs.layout', ['title' => 'Access codes', 'noindex' => true])

@section('content')
<div class="max-w-3xl mx-auto px-4 py-10">
    <h1 class="text-3xl font-bold text-gray-900">Access codes</h1>
    <p class="mt-2 text-gray-600">
        Some manuals and chapters are restricted. If {{ $docs->organisation->name }} gave you an access code, enter it here.
        It stays active in this browser for {{ (int) config('docs.code_session_days', 30) }} days, or until the code expires.
    </p>

    <div class="mt-6 bg-white rounded-lg border border-gray-200 shadow-sm p-6">
        <form method="post" action="{{ $docs->url('docs/access') }}" class="flex flex-col sm:flex-row gap-3">
            @csrf
            @if (request('return'))
                <input type="hidden" name="return" value="{{ request('return') }}">
            @endif
            <label class="flex-1">
                <span class="sr-only">Access code</span>
                <input type="text" name="code" value="{{ old('code') }}" required maxlength="40" autocomplete="off" autofocus placeholder="XXXX-XXXX-XXXX"
                       class="w-full px-4 py-2.5 font-mono tracking-wider uppercase border border-gray-300 rounded focus:outline-none focus:ring-2 focus:ring-(--docs-accent)">
            </label>
            <button type="submit" class="px-6 py-2.5 rounded docs-accent-bg text-white font-semibold hover:opacity-90">Unlock</button>
        </form>
        @error('code')<p class="mt-2 text-sm text-red-600">{{ $message }}</p>@enderror
        @unless ($docsAccess->isStaff())
            <p class="mt-4 text-sm text-gray-600">Staff do not need a code: <a href="{{ $staffSignIn }}" class="docs-accent underline font-semibold">sign in</a> instead.</p>
        @endunless
    </div>

    @if ($codes->isNotEmpty())
        <h2 class="mt-10 text-xl font-bold text-gray-900">Active in this browser</h2>
        <ul class="mt-4 space-y-3">
            @foreach ($codes as $code)
                <li class="bg-white rounded-lg border border-gray-200 p-5">
                    <div class="flex flex-wrap items-center gap-2">
                        <span class="font-semibold text-gray-900">{{ $code->name }}</span>
                        @if ($code->expires_at)
                            <span class="text-xs text-gray-500">expires {{ $docs->date($code->expires_at) }}</span>
                        @endif
                    </div>
                    <ul class="mt-2 flex flex-wrap gap-2">
                        @foreach ($code->grants as $grant)
                            @if ($grant->system?->isInLibrary())
                                <li>
                                    <a href="{{ $docs->system($grant->system) }}" class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full bg-gray-100 text-sm text-gray-800 hover:bg-gray-200">
                                        {{ $grant->system->name }}
                                        @if ($grant->audiences->isNotEmpty())
                                            <span class="text-xs text-gray-500">· {{ $grant->audiences->pluck('name')->join(', ') }}</span>
                                        @endif
                                        @if ($grant->include_restricted)
                                            <span class="text-xs text-gray-500">(incl. restricted chapters)</span>
                                        @endif
                                    </a>
                                </li>
                            @endif
                        @endforeach
                    </ul>
                </li>
            @endforeach
        </ul>
        <form method="post" action="{{ $docs->url('docs/access/forget') }}" class="mt-4">
            @csrf
            <button type="submit" class="text-sm text-gray-600 underline hover:text-gray-900">Remove all codes from this browser</button>
        </form>
    @endif
</div>
@endsection
