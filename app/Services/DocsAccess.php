<?php

namespace App\Services;

use App\Models\DocsAccessCode;
use App\Models\ManualSection;
use App\Models\System;
use App\Models\User;
use App\Support\DocsContext;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cookie;

/**
 * What the current visitor of the documentation library may read.
 *
 * - Public manuals: everyone (restricted chapters excepted).
 * - Restricted manuals and restricted chapters: staff of the organisation (signed in) and
 *   readers who entered an access code that unlocks them.
 * - Manuals "not in the library": nobody here (staff read them in the staff portal).
 *
 * Codes entered in this browser are re-checked on every request, so deactivating a code or
 * reaching its expiry date locks the content again straight away.
 */
class DocsAccess
{
    private ?User $staff;

    /** @var Collection<int, DocsAccessCode> */
    private Collection $codes;

    /** @var array<int, array{restricted: bool, audiences: array<int, int>}> system id => what the visitor's codes open */
    private array $grants = [];

    public function __construct(private readonly DocsContext $context)
    {
        $user = auth('web')->user();
        $this->staff = $user && $user->is_active && $user->organisation_id === $context->organisation->id ? $user : null;
        $this->loadCodes();
    }

    public function isStaff(): bool
    {
        return $this->staff !== null;
    }

    public function staffUser(): ?User
    {
        return $this->staff;
    }

    /** @return Collection<int, DocsAccessCode> */
    public function codes(): Collection
    {
        return $this->codes;
    }

    public function hasCodes(): bool
    {
        return $this->codes->isNotEmpty();
    }

    public function canListManual(System $system): bool
    {
        return $system->organisation_id === $this->context->organisation->id && $system->isInLibrary();
    }

    public function canOpenManual(System $system): bool
    {
        if (!$this->canListManual($system)) {
            return false;
        }

        return $system->library_visibility === System::LIBRARY_PUBLIC
            || $this->isStaff()
            || array_key_exists($system->id, $this->grants);
    }

    public function canOpenRestrictedIn(System $system): bool
    {
        return $this->canOpenManual($system) && ($this->isStaff() || ($this->grants[$system->id]['restricted'] ?? false));
    }

    /**
     * IDs of sections that need more than access to the manual: restricted chapters and chapters
     * meant for particular audiences, each with everything under it. Never in the sitemap.
     *
     * @param Collection<int, ManualSection> $outline published sections in order (with sort_key)
     * @return array<int, true>
     */
    public function protectedSectionIds(Collection $outline): array
    {
        return $this->withDescendants($outline, $outline->filter(
            fn (ManualSection $s) => $s->isRestricted() || $this->audienceIds($s) !== []
        ));
    }

    /**
     * Sections in this outline the visitor may not read (empty if they can read everything).
     *
     * A section is locked when it, or a chapter above it, is
     * - restricted, and the visitor's codes for this manual don't include restricted chapters, or
     * - meant for audiences, and the visitor's codes for this manual open none of them.
     * Staff of the organisation can read everything.
     *
     * @return array<int, true>
     */
    public function lockedSectionIds(System $system, Collection $outline): array
    {
        if (!$this->canOpenManual($system)) {
            return $outline->mapWithKeys(fn (ManualSection $s) => [$s->id => true])->all();
        }

        if ($this->isStaff()) {
            return [];
        }

        $grant = $this->grants[$system->id] ?? ['restricted' => false, 'audiences' => []];

        return $this->withDescendants($outline, $outline->filter(function (ManualSection $s) use ($grant) {
            $audiences = $this->audienceIds($s);

            return ($s->isRestricted() && !$grant['restricted'])
                || ($audiences !== [] && array_intersect($audiences, $grant['audiences']) === []);
        }));
    }

    /**
     * Audiences the visitor's codes open in this manual (staff: all of them).
     *
     * @return array<int, int>
     */
    public function audienceIdsFor(System $system): array
    {
        return $this->grants[$system->id]['audiences'] ?? [];
    }

    /** @return array<int, int> */
    private function audienceIds(ManualSection $section): array
    {
        return $section->relationLoaded('audiences')
            ? $section->audiences->pluck('id')->map(fn ($id) => (int) $id)->all()
            : $section->audiences()->pluck('docs_audiences.id')->map(fn ($id) => (int) $id)->all();
    }

    /**
     * The given sections plus everything under them, as an id map.
     *
     * @return array<int, true>
     */
    private function withDescendants(Collection $outline, Collection $roots): array
    {
        $keys = $roots->pluck('sort_key')->all();

        if ($keys === []) {
            return [];
        }

        return $outline
            ->filter(fn (ManualSection $s) => collect($keys)->contains(
                fn ($key) => $s->sort_key === $key || str_starts_with((string) $s->sort_key, $key . '.')
            ))
            ->mapWithKeys(fn (ManualSection $s) => [$s->id => true])
            ->all();
    }

    /**
     * Try a code typed by a visitor. Returns the code when it's valid for this organisation.
     */
    public function unlock(string $code): ?DocsAccessCode
    {
        $match = DocsAccessCode::query()
            ->where('organisation_id', $this->context->organisation->id)
            ->where('code_hash', DocsAccessCode::hashCode($code))
            ->first();

        if (!$match || !$match->isUsable()) {
            return null;
        }

        $this->storeIds(collect($this->storedIds())->push($match->id)->unique()->values()->all());

        $match->forceFill(['last_used_at' => now(), 'use_count' => $match->use_count + 1])->saveQuietly();

        $this->loadCodes();

        return $match;
    }

    public function forgetCodes(): void
    {
        $this->storeIds([]);
        $this->loadCodes();
    }

    private function loadCodes(): void
    {
        $ids = $this->storedIds();

        $this->codes = empty($ids) ? new \Illuminate\Database\Eloquent\Collection() : DocsAccessCode::query()
            ->with('grants.audiences')
            ->where('organisation_id', $this->context->organisation->id)
            ->whereIn('id', $ids)
            ->get()
            ->filter(fn (DocsAccessCode $c) => $c->isUsable())
            ->values();

        // Drop codes that are no longer valid from the browser.
        if (count($ids) !== $this->codes->count()) {
            $this->storeIds($this->codes->pluck('id')->all());
        }

        // Several codes for the same manual add up.
        $this->grants = [];
        foreach ($this->codes as $code) {
            foreach ($code->grants as $grant) {
                $current = $this->grants[$grant->system_id] ?? ['restricted' => false, 'audiences' => []];
                $this->grants[$grant->system_id] = [
                    'restricted' => $current['restricted'] || $grant->include_restricted,
                    'audiences' => array_values(array_unique([...$current['audiences'], ...$grant->audiences->pluck('id')->map(fn ($id) => (int) $id)->all()])),
                ];
            }
        }
    }

    /**
     * Codes entered in this browser. Kept in the session and in an encrypted cookie, so they keep
     * working for docs.code_session_days even after the session ends.
     *
     * @return array<int, int>
     */
    private function storedIds(): array
    {
        $fromCookie = json_decode((string) request()->cookie($this->storageKey()), true);

        return collect(session($this->storageKey(), []))
            ->merge(is_array($fromCookie) ? $fromCookie : [])
            ->map(fn ($id) => (int) $id)
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    private function storeIds(array $ids): void
    {
        session([$this->storageKey() => $ids]);

        $ids === []
            ? Cookie::queue(Cookie::forget($this->storageKey()))
            : Cookie::queue($this->storageKey(), json_encode($ids), 60 * 24 * (int) config('docs.code_session_days', 30));
    }

    private function storageKey(): string
    {
        return 'docs_codes_' . $this->context->organisation->id;
    }
}
