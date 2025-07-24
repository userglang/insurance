<?php

namespace App\Filament\Pages;

use App\Filament\Widgets\BranchSubscriptionTable;
use App\Filament\Widgets\MemberStats;
use App\Filament\Widgets\SubscriptionTable;
use App\Models\Branch;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Form;
use Filament\Pages\Dashboard as BaseDashboard;
use Filament\Pages\Dashboard\Concerns\HasFiltersForm;
use Filament\Widgets\AccountWidget;

class Dashboard extends BaseDashboard
{
    use HasFiltersForm;

//    protected function filtersForm(Form $form): Form
//    {
//        return $form->schema([
//            Section::make('Filters')
//                ->schema([
//                    Select::make('branch_id')
//                        ->label('Branch')
//                        ->options(
//                            Branch::query()
//                                ->where('is_active', true)
//                                ->orderBy('branch_name')
//                                ->pluck('branch_name', 'id')
//                        )
//                        ->searchable()
//                        ->preload()
//                        ->native(false),
//
//                    DatePicker::make('startDate')->label('Start Date'),
//                    DatePicker::make('endDate')->label('End Date'),
//                ])
//                ->columns(3),
//        ]);
//    }

    public function getWidgets(): array
    {
        return [
            AccountWidget::class,
            MemberStats::class,
            SubscriptionTable::class,
//            BranchSubscriptionTable::class,
        ];
    }
}
