<?php

namespace App\Filament\Pages;

use App\Models\Department;
use App\Models\User;
use App\Services\ReadinessService;
use App\Support\Modules;
use App\Support\Permissions;
use Filament\Pages\Page;

/**
 * Who is ready on which product: people × products, coloured by level, and the products that
 * depend on fewer than two ready people.
 */
class Readiness extends Page
{
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-squares-2x2';

    protected string $view = 'filament.pages.readiness';

    protected static \UnitEnum|string|null $navigationGroup = 'Competency';

    protected static ?string $navigationLabel = 'Readiness';

    protected static ?string $title = 'Readiness';

    protected static ?int $navigationSort = 3;

    public ?string $department = null;

    public static function canAccess(): bool
    {
        $user = auth()->user();

        if (!$user || !Modules::allows(Modules::COMPETENCY)) {
            return false;
        }

        return $user->hasRole('super_admin')
            || $user->can(Permissions::MANAGE_COMPETENCY)
            || $user->can(Permissions::REVIEW_COMPETENCY)
            || $user->competencyReviewSystemIds() !== [];
    }

    /** Owners and reviewers see only their products; managers see all. */
    private function seesAllProducts(): bool
    {
        $user = auth()->user();

        return $user->hasRole('super_admin') || $user->can(Permissions::MANAGE_COMPETENCY) || $user->can(Permissions::REVIEW_COMPETENCY);
    }

    public function getViewData(): array
    {
        $user = auth()->user();
        $organisationId = (int) $user->organisation_id;

        $users = User::query()
            ->where('organisation_id', $organisationId)
            ->where('is_active', true)
            ->when($this->department, fn ($q) => $q->where('department_id', $this->department))
            ->with(['roles', 'department:id,name'])
            ->orderBy('name')
            ->get();

        $service = app(ReadinessService::class);
        $matrix = $service->matrix($organisationId, $users);
        $risks = $service->coverageRisks($organisationId);

        if (!$this->seesAllProducts()) {
            $mine = $user->competencyReviewSystemIds();
            $matrix['systems'] = $matrix['systems']->filter(fn ($s) => in_array($s->id, $mine, true))->values();
            $risks = $risks->filter(fn ($r) => in_array($r['system']->id, $mine, true))->values();
        }

        // Only people with at least one product where readiness applies.
        $rows = collect($matrix['rows'])->filter(fn ($row) => $matrix['systems']->contains(
            fn ($s) => $row['cells'][$s->id]['level'] !== ReadinessService::NOT_REQUIRED
        ))->values();

        return [
            'systems' => $matrix['systems'],
            'rows' => $rows,
            'risks' => $risks,
            'departments' => Department::query()->where('organisation_id', $organisationId)->orderBy('name')->pluck('name', 'id'),
            'levels' => ReadinessService::LEVELS,
        ];
    }
}
