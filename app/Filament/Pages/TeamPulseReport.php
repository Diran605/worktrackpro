<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;
use App\Models\User;
use Carbon\Carbon;

class TeamPulseReport extends Page
{
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-document-chart-bar';

    protected string $view = 'filament.pages.team-pulse-report';
    
    protected static \UnitEnum|string|null $navigationGroup = 'Reporting';
    
    protected static ?string $navigationLabel = 'Reports & Summaries';
    
    protected static ?string $title = 'Team & Worker Reports';
    
    public static ?int $navigationSort = 1;

    public $reportPeriod;
    public $reportDate;

    public function mount()
    {
        $this->reportDate = Carbon::now()->format('Y-m-d');
        $this->reportPeriod = 'week';
    }

    public static function canAccess(): bool
    {
        // Only super admin or admin can see reporting
        return auth()->user()?->hasRole(['super_admin', 'admin']) ?? false;
    }

    public function getViewData(): array
    {
        $admin = auth()->user();
        if ($admin->hasRole('super_admin')) {
            $users = User::where('organisation_id', $admin->organisation_id)
                ->where('is_active', true)
                ->orderBy('name')
                ->get();
        } else {
            $users = User::where('department_id', $admin->department_id)
                ->where('is_active', true)
                ->orderBy('name')
                ->get();
        }

        return [
            'users' => $users
        ];
    }
}
