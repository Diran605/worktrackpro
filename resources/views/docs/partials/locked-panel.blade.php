{{-- "This content is restricted": staff sign-in, access code, request access. Expects: $system --}}
<div class="border border-amber-200 bg-amber-50 rounded-lg p-6">
    <div class="flex items-center gap-2 text-amber-900">
        @include('docs.partials.lock', ['class' => 'w-6 h-6'])
        <h2 class="text-lg font-bold">This content is restricted</h2>
    </div>
    <p class="mt-2 text-sm text-amber-900">
        You can read it if you are a member of staff, or if {{ $docs->organisation->name }} has given you an access code.
    </p>

    <div class="mt-6 grid grid-cols-1 md:grid-cols-2 gap-6">
        <div class="bg-white rounded-md border border-gray-200 p-5">
            <h3 class="font-semibold text-gray-900">I have an access code</h3>
            <form method="post" action="{{ $docs->url('docs/access') }}" class="mt-3 space-y-3">
                @csrf
                <input type="hidden" name="return" value="{{ request()->getRequestUri() }}">
                <label class="block">
                    <span class="sr-only">Access code</span>
                    <input type="text" name="code" value="{{ old('code') }}" required maxlength="40" autocomplete="off" placeholder="XXXX-XXXX-XXXX"
                           class="w-full px-3 py-2 font-mono tracking-wider uppercase border border-gray-300 rounded focus:outline-none focus:ring-2 focus:ring-(--docs-accent)">
                </label>
                @error('code')<p class="text-sm text-red-600">{{ $message }}</p>@enderror
                <button type="submit" class="px-4 py-2 rounded docs-accent-bg text-white font-semibold hover:opacity-90">Unlock</button>
            </form>
            @unless ($docsAccess->isStaff())
                <p class="mt-4 text-sm text-gray-600">Staff member? <a href="{{ url('/staff/login') }}" class="docs-accent underline font-semibold">Sign in</a></p>
            @endunless
        </div>

        <div class="bg-white rounded-md border border-gray-200 p-5">
            <h3 class="font-semibold text-gray-900">Ask for access</h3>
            @if (session('docs_request_sent'))
                <p class="mt-3 text-sm text-green-700">Your request was sent to the people responsible for this manual. They will contact you by email.</p>
            @else
                <form method="post" action="{{ $docs->system($system, 'request-access') }}" class="mt-3 space-y-3">
                    @csrf
                    <input type="text" name="name" value="{{ old('name') }}" required maxlength="255" placeholder="Your name" aria-label="Your name"
                           class="w-full px-3 py-2 text-sm border border-gray-300 rounded focus:outline-none focus:ring-2 focus:ring-(--docs-accent)">
                    @error('name')<p class="text-sm text-red-600">{{ $message }}</p>@enderror
                    <input type="email" name="email" value="{{ old('email') }}" required maxlength="255" placeholder="Your email" aria-label="Your email"
                           class="w-full px-3 py-2 text-sm border border-gray-300 rounded focus:outline-none focus:ring-2 focus:ring-(--docs-accent)">
                    @error('email')<p class="text-sm text-red-600">{{ $message }}</p>@enderror
                    <textarea name="reason" rows="2" maxlength="2000" placeholder="Why do you need it? (optional)" aria-label="Reason"
                              class="w-full px-3 py-2 text-sm border border-gray-300 rounded focus:outline-none focus:ring-2 focus:ring-(--docs-accent)">{{ old('reason') }}</textarea>
                    <button type="submit" class="px-4 py-2 rounded border border-gray-300 text-gray-800 font-semibold hover:bg-gray-50">Send request</button>
                </form>
            @endif
        </div>
    </div>
</div>
