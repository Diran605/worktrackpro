@php
    $timezone = \Filament\Support\Facades\FilamentTimezone::get();
@endphp

<div class="space-y-4">
    <div class="text-sm text-gray-600 dark:text-gray-400 flex flex-wrap gap-x-4 gap-y-1">
        <span><strong class="text-gray-900 dark:text-white">From:</strong> {{ $message->sender?->name ?? 'System' }}</span>
        <span><strong class="text-gray-900 dark:text-white">Received:</strong> {{ $message->created_at?->timezone($timezone)->format('M j, Y H:i') }}</span>
    </div>

    {{-- Message bodies are plain text; escape and keep line breaks. --}}
    <div class="text-sm leading-relaxed text-gray-900 dark:text-gray-100 whitespace-pre-line rounded-lg border border-gray-200 dark:border-white/10 p-4">{{ $message->body }}</div>

    @if ($attachments->isNotEmpty())
        <div>
            <div class="text-sm font-semibold text-gray-900 dark:text-white mb-2">Attachments</div>
            <ul class="space-y-1">
                @foreach ($attachments as $file)
                    <li class="text-sm">
                        <a href="{{ $file['url'] }}" class="text-primary-600 hover:underline" target="_blank" rel="noopener">
                            📎 {{ $file['name'] }}
                        </a>
                        @if ($file['size'])
                            <span class="text-gray-500">({{ number_format($file['size'] / 1024, 0) }} KB)</span>
                        @endif
                    </li>
                @endforeach
            </ul>
        </div>
    @endif
</div>
