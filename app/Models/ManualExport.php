<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;

/**
 * A generated manual PDF (the whole manual or selected sections).
 */
class ManualExport extends Model
{
    public const STATUS_QUEUED = 'queued';
    public const STATUS_PROCESSING = 'processing';
    public const STATUS_READY = 'ready';
    public const STATUS_FAILED = 'failed';

    public const APPENDIX_CONTACTS = 'contacts';
    public const APPENDIX_CHANGELOG = 'changelog';
    public const APPENDICES = [self::APPENDIX_CONTACTS, self::APPENDIX_CHANGELOG];

    protected $fillable = [
        'system_id',
        'requested_by',
        'manual_version',
        'fingerprint',
        'section_ids',
        'options',
        'status',
        'file_path',
        'page_count',
        'meta',
        'error',
        'completed_at',
    ];

    protected $casts = [
        'section_ids' => 'array',
        'options' => 'array',
        'meta' => 'array',
        'completed_at' => 'datetime',
    ];

    public function system(): BelongsTo
    {
        return $this->belongsTo(System::class);
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function isReady(): bool
    {
        return $this->status === self::STATUS_READY && $this->file_path;
    }

    public function isFullManual(): bool
    {
        return empty($this->section_ids);
    }

    public function downloadName(): string
    {
        $scope = $this->isFullManual() ? 'manual' : 'manual-extract';

        return Str::slug($this->system?->name ?? 'system') . "-{$scope}-v{$this->manual_version}.pdf";
    }

    public function signedDownloadUrl(int $minutes = 30): string
    {
        return URL::temporarySignedRoute('kb.export', now()->addMinutes($minutes), ['export' => $this->id]);
    }
}
