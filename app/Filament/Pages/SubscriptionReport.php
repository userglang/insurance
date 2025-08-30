<?php

namespace App\Filament\Pages;

use App\Models\Branch;
use App\Models\Subscription;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Section;
use Filament\Forms\Form;
use Filament\Pages\Page;
use Filament\Tables\Actions\Action;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Log;
use Filament\Notifications\Notification;

class SubscriptionReport extends Page implements HasTable
{
    use InteractsWithTable;

    protected static ?string $navigationIcon = 'heroicon-o-chart-bar';
    protected static ?string $navigationLabel = 'Subscription Reports';
    protected static ?string $title = 'Subscription Reports';
    protected static string $view = 'filament.pages.subscription-report';
    protected static ?string $navigationGroup = 'Reports';

    public ?array $data = [];
    public $startDate;
    public $endDate;
    public $selectedBranch;

    public function mount(): void
    {
        $this->form->fill([
            'start_date' => now()->startOfMonth(),
            'end_date' => now()->endOfMonth(),
            'branch_id' => null,
        ]);
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('Report Filters')
                    ->schema([
                        DatePicker::make('start_date')
                            ->label('Start Date')
                            ->default(now()->startOfMonth())
                            ->required()
                            ->reactive()
                            ->afterStateUpdated(fn ($state) => $this->startDate = $state),

                        DatePicker::make('end_date')
                            ->label('End Date')
                            ->default(now()->endOfMonth())
                            ->required()
                            ->reactive()
                            ->afterStateUpdated(fn ($state) => $this->endDate = $state),

                        Select::make('branch_id')
                            ->label('Branch')
                            ->options(Branch::pluck('branch_name', 'branch_number'))
                            ->placeholder('All Branches')
                            ->reactive()
                            ->afterStateUpdated(fn ($state) => $this->selectedBranch = $state),
                    ])
                    ->columns(3)
            ])
            ->statePath('data');
    }

    public function table(Table $table): Table
    {
        return $table
            ->query($this->getTableQuery())
            ->columns([
                TextColumn::make('member.cid')->label('CID')->sortable()->searchable(),
                TextColumn::make('member.full_name')->label('Member Name')->sortable()->searchable(),
                TextColumn::make('member.branch.branch_name')->label('Branch')->sortable(),
                TextColumn::make('insurance.insurance_name')->label('Insurance')->sortable(),
                TextColumn::make('amount')->label('Amount')->money('PHP')->sortable(),
                TextColumn::make('payment_date')->label('Payment Date')->date()->sortable(),
                TextColumn::make('activated_at')->label('Activated')->date()->sortable(),
                TextColumn::make('expires_at')->label('Expires')->date()->sortable(),
                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->color(function (string $state, $column) {
                        $isActive = $column->getRecord()->member->is_active ?? true;

                        if (!$isActive) {
                            return 'gray';
                        }

                        return match ($state) {
                            'active' => 'success',
                            'expired' => 'danger',
                            'future' => 'warning',
                            default => 'gray',
                        };
                    })
                    ->formatStateUsing(function (string $state, $column) {
                        $isActive = $column->getRecord()->member->is_active ?? true;
                        return $isActive ? $state : 'archived';
                    }),
            ])
            ->filters([
                Filter::make('status')
                    ->form([
                        Select::make('status')
                            ->options([
                                'active' => 'Active',
                                'expired' => 'Expired',
                                'future' => 'Future',
                                'archived' => 'Archived',
                            ])
                            ->placeholder('All Statuses'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        if (!$data['status']) {
                            return $query;
                        }

                        $now = now();
                        return match ($data['status']) {
                            'active' => $query->where('activated_at', '<=', $now)
                                ->where('expires_at', '>', $now)
                                ->whereHas('member', fn ($q) => $q->where('is_active', true)),

                            'expired' => $query->where('expires_at', '<=', $now)
                                ->whereHas('member', fn ($q) => $q->where('is_active', true)),

                            'future' => $query->where('activated_at', '>', $now)
                                ->whereHas('member', fn ($q) => $q->where('is_active', true)),

                            'archived' => $query->whereHas('member', fn ($q) => $q->where('is_active', false)),

                            default => $query,
                        };
                    }),
            ])
            ->headerActions([
                Action::make('export_pdf')
                    ->label('Export PDF')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->action('exportPdf')
                    ->color('success'),

                Action::make('export_excel')
                    ->label('Export Excel')
                    ->icon('heroicon-o-table-cells')
                    ->action('exportExcel')
                    ->color('primary'),

                Action::make('export_summary_pdf')  // New button
                    ->label('Export Summary PDF')
                    ->icon('heroicon-o-document-text')
                    ->action('exportSummaryPdf')
                    ->color('success'),
            ])
            ->defaultSort('payment_date', 'desc')
            ->paginated([10, 25, 50, 100]);
    }

    protected function getTableQuery(): Builder
    {
        $query = Subscription::query()
            ->with(['member.branch', 'insurance', 'member'])
            ->whereHas('member');

        if ($this->data['start_date'] ?? null) {
            $query->where('payment_date', '>=', $this->data['start_date']);
        }

        if ($this->data['end_date'] ?? null) {
            $query->where('payment_date', '<=', $this->data['end_date']);
        }

        if ($this->data['branch_id'] ?? null) {
            $query->whereHas('member', function (Builder $q) {
                $q->where('branch_number', $this->data['branch_id']);
            });
        }

        return $query;
    }

    public function exportPdf()
    {
        try {
            $maxRows = 2000;

            $subscriptions = $this->getTableQuery()->take($maxRows + 1)->get(); // get one extra to check limit

            if ($subscriptions->count() > $maxRows) {
                Notification::make()
                    ->title('Too Much Data')
                    ->body("PDF export is limited to {$maxRows} rows. Please filter your data.")
                    ->danger()
                    ->send();
                return;
            }

            $reportData = $this->generateReportData($subscriptions);

            $pdf = Pdf::loadView('reports.subscription-report-pdf', [
                'subscriptions' => $subscriptions,
                'reportData' => $reportData,
                'filters' => [
                    'start_date' => $this->data['start_date'],
                    'end_date' => $this->data['end_date'],
                    'branch' => $this->data['branch_id']
                        ? Branch::find($this->data['branch_id'])?->branch_name
                        : 'All Branches',
                ],
                'generatedAt' => now()->format('F d, Y g:i A'),
            ]);

            $filename = 'subscription-report-' . now()->format('Y-m-d') . '.pdf';

            return response()->streamDownload(
                fn () => print($pdf->stream()),
                $filename
            );

        } catch (\Exception $e) {
            Notification::make()
                ->title('Export Failed')
                ->body('Failed to generate PDF report: ' . $e->getMessage())
                ->danger()
                ->send();
        }
    }

    public function exportExcel()
    {
        try {
            $subscriptions = $this->getTableQuery()->get();

            $headers = [
                'Content-Type' => 'text/csv',
                'Content-Disposition' => 'attachment; filename="subscription-report-' . now()->format('Y-m-d') . '.csv"',
            ];

            $callback = function () use ($subscriptions) {
                $file = fopen('php://output', 'w');

                fputcsv($file, [
                    'CID',
                    'Member Name',
                    'Branch',
                    'Insurance',
                    'Amount',
                    'Payment Date',
                    'Activated Date',
                    'Expires Date',
                    'Status',
                ]);

                foreach ($subscriptions as $subscription) {
                    $status = $subscription->member->is_active ?? true
                        ? $subscription->status
                        : 'archived';

                    fputcsv($file, [
                        $subscription->member->cid,
                        $subscription->member->full_name,
                        $subscription->member->branch?->branch_name,
                        $subscription->insurance?->insurance_name,
                        $subscription->amount,
                        $subscription->payment_date?->format('Y-m-d'),
                        $subscription->activated_at?->format('Y-m-d'),
                        $subscription->expires_at?->format('Y-m-d'),
                        $status,
                    ]);
                }

                fclose($file);
            };

            return response()->stream($callback, 200, $headers);

        } catch (\Exception $e) {
            Notification::make()
                ->title('Export Failed')
                ->body('Failed to generate Excel report: ' . $e->getMessage())
                ->danger()
                ->send();
        }
    }

    public function exportSummaryPdf()
    {
        try {
            // Get the filtered subscriptions (limited to max rows to avoid overload)
            $maxRows = 2000;
            $subscriptions = $this->getTableQuery()->take($maxRows)->get();

            // Generate the summary data
            $reportData = $this->generateReportData($subscriptions);

            $pdf = Pdf::loadView('reports.subscription-summary-pdf', [
                'reportData' => $reportData,
                'filters' => [
                    'start_date' => $this->data['start_date'],
                    'end_date' => $this->data['end_date'],
                    'branch' => $this->data['branch_id']
                        ? Branch::find($this->data['branch_id'])?->branch_name
                        : 'All Branches',
                ],
                'generatedAt' => now()->format('F d, Y g:i A'),
            ]);

            $filename = 'subscription-summary-' . now()->format('Y-m-d') . '.pdf';

            return response()->streamDownload(
                fn () => print($pdf->stream()),
                $filename
            );

        } catch (\Exception $e) {
            Notification::make()
                ->title('Export Failed')
                ->body('Failed to generate Summary PDF report: ' . $e->getMessage())
                ->danger()
                ->send();
        }
    }


    protected function generateReportData($subscriptions)
    {
        $totalAmount = $subscriptions->sum('amount');
        $totalSubscriptions = $subscriptions->count();
        $totalMembers = $subscriptions->unique('member_id')->count();

        $statusCounts = [
            'active' => 0,
            'expired' => 0,
            'future' => 0,
            'archived' => 0,
        ];

        $branchStats = [];
        $insuranceStats = [];

        foreach ($subscriptions as $subscription) {
            if (!$subscription->member->is_active) {
                $status = 'archived';
            } else {
                $status = $subscription->status;

                if (!array_key_exists($status, $statusCounts)) {
                    $statusCounts[$status] = 0;
                    Log::warning('Unexpected status found in subscription report', [
                        'subscription_id' => $subscription->id,
                        'status' => $status,
                    ]);
                }
            }

            $statusCounts[$status]++;

            $branchName = $subscription->member->branch?->branch_name ?? 'Unknown';
            if (!isset($branchStats[$branchName])) {
                $branchStats[$branchName] = [
                    'totalSubscriptions' => 0,
                    'totalMembers' => 0,
                    'active' => 0,
                    'expired' => 0,
                    'archived' => 0,
                    'future' => 0,
                    'amount' => 0,
                ];
            }

            $branchStats[$branchName]['totalSubscriptions']++;
            $branchStats[$branchName]['amount'] += $subscription->amount;
            if (!isset($branchStats[$branchName][$status])) {
                $branchStats[$branchName][$status] = 0;
            }
            $branchStats[$branchName][$status]++;

            $insuranceName = $subscription->insurance?->insurance_name ?? 'Unknown';
            if (!isset($insuranceStats[$insuranceName])) {
                $insuranceStats[$insuranceName] = [
                    'totalSubscriptions' => 0,
                    'totalMembers' => 0,
                    'active' => 0,
                    'expired' => 0,
                    'archived' => 0,
                    'future' => 0,
                    'amount' => 0,
                ];
            }

            $insuranceStats[$insuranceName]['totalSubscriptions']++;
            $insuranceStats[$insuranceName]['amount'] += $subscription->amount;
            if (!isset($insuranceStats[$insuranceName][$status])) {
                $insuranceStats[$insuranceName][$status] = 0;
            }
            $insuranceStats[$insuranceName][$status]++;
        }

        // ✅ Fix totalMembers per branch
        $subscriptionsByBranch = $subscriptions->groupBy(fn($s) => $s->member->branch?->branch_name ?? 'Unknown');
        foreach ($subscriptionsByBranch as $branchName => $branchSubs) {
            $branchStats[$branchName]['totalMembers'] = $branchSubs->unique('member_id')->count();
        }

        // ✅ Fix totalMembers per insurance
        $subscriptionsByInsurance = $subscriptions->groupBy(fn($s) => $s->insurance?->insurance_name ?? 'Unknown');
        foreach ($subscriptionsByInsurance as $insuranceName => $insuranceSubs) {
            $insuranceStats[$insuranceName]['totalMembers'] = $insuranceSubs->unique('member_id')->count();
        }

        // ✅ Calculate active + future total
        $activeTotal = ($statusCounts['active'] ?? 0) + ($statusCounts['future'] ?? 0);

        return [
            'totalAmount' => $totalAmount,
            'totalSubscriptions' => $totalSubscriptions,
            'totalMembers' => $totalMembers,
            'statusCounts' => $statusCounts,
            'activeTotal' => $activeTotal, // 👈 added
            'branchStats' => $branchStats,
            'insuranceStats' => $insuranceStats,
        ];
    }



    public function getReportSummary()
    {
        $subscriptions = $this->getTableQuery()->get();
        return $this->generateReportData($subscriptions);
    }
}
