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
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

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
            'branch_id' => $this->getDefaultBranchId(),
        ]);
    }

    protected function getDefaultBranchId(): ?string
    {
        $user = Auth::user();

        if ($user->hasRole('super_admin')) {
            return null;
        }

        return $user->branch->branch_number ?? null;
    }

    protected function isSuperAdmin(): bool
    {
        return Auth::user()->hasRole('super_admin');
    }

    protected function getUserBranchNumber(): ?string
    {
        return Auth::user()->branch->branch_number ?? null;
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
                            ->native(true)
                            ->required()
                            ->reactive()
                            ->afterStateUpdated(fn ($state) => $this->startDate = $state)
                            ->helperText('Format: DD/MM/YYYY.'),

                        DatePicker::make('end_date')
                            ->label('End Date')
                            ->default(now()->endOfMonth())
                            ->native(true)
                            ->required()
                            ->reactive()
                            ->afterStateUpdated(function ($state) {
                                $this->endDate = $state;

                                // Ensure that the end date is greater than or equal to the start date
                                if ($this->startDate && $state && $state < $this->startDate) {
                                    // Show a validation error or update helper text if end date is before start date
                                    session()->flash('error', 'End Date should be greater than or equal to Start Date.');
                                }
                            })
                            ->helperText('Format: DD/MM/YYYY. End date must be after or the same as the start date.'),

                        Select::make('branch_id')
                            ->label('Branch')
                            ->options($this->getBranchOptions())
                            ->placeholder($this->isSuperAdmin() ? 'All Branches' : 'Select Branch')
                            ->reactive()
                            ->afterStateUpdated(fn ($state) => $this->selectedBranch = $state)
                            ->visible($this->isSuperAdmin()),
                    ])
                    ->columns($this->isSuperAdmin() ? 3 : 2)
            ])
            ->statePath('data');
    }

    protected function getBranchOptions(): array
    {
        if ($this->isSuperAdmin()) {
            return Branch::pluck('branch_name', 'branch_number')->toArray();
        }

        $userBranch = $this->getUserBranchNumber();
        if ($userBranch) {
            return Branch::where('branch_number', $userBranch)
                ->pluck('branch_name', 'branch_number')
                ->toArray();
        }

        return [];
    }

    public function table(Table $table): Table
    {
        return $table
            ->query($this->getTableQuery())
            ->columns([
                TextColumn::make('member.cid')->label('CID')->sortable()->searchable()
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->placeholder('Unknown'),
                TextColumn::make('member.full_name')->label('Member Name')->sortable()->searchable(),
                TextColumn::make('member.branch.branch_name')
                    ->label('Branch')
                    ->sortable()
                    ->visible($this->isSuperAdmin()),
                TextColumn::make('insurance.insurance_name')->label('Insurance')->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('amount')->label('Amount')->money('PHP')->sortable(),
                TextColumn::make('payment_date')->label('Payment Date')->date()->sortable(),
                TextColumn::make('activated_at')->label('Activated')->date()->sortable(),
                TextColumn::make('expires_at')->label('Expires')->date()->sortable(),
                TextColumn::make('status')
                    ->toggleable(isToggledHiddenByDefault: true)
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

                Action::make('export_summary_pdf')
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

        // Check branch filter based on user role
        $branchNumber = null;
        if (!$this->isSuperAdmin()) {
            $branchNumber = $this->getUserBranchNumber();
        } elseif ($this->data['branch_id'] ?? null) {
            $branchNumber = $this->data['branch_id'];
        }

        // Apply branch filter if applicable
        if ($branchNumber) {
            $query->whereHas('member', function (Builder $q) use ($branchNumber) {
                $q->where('branch_number', $branchNumber);
            });
        }

        // Apply date filters if available
        if ($startDate = $this->data['start_date'] ?? null) {
            $query->where('payment_date', '>=', $startDate);
        }

        if ($endDate = $this->data['end_date'] ?? null) {
            $query->where('payment_date', '<=', $endDate);
        }

        return $query;
    }

    public function exportPdf()
    {
        try {
            $maxRows = 2000;
            $subscriptions = $this->getTableQuery()->take($maxRows + 1)->get();

            if ($subscriptions->count() > $maxRows) {
                Notification::make()
                    ->title('Too Much Data')
                    ->body("PDF export is limited to {$maxRows} rows. Please filter your data.")
                    ->danger()
                    ->send();
                return;
            }

            $reportData = $this->generateReportData($this->getTableQuery());

            $pdf = Pdf::loadView('reports.subscription-report-pdf', [
                'subscriptions' => $subscriptions,
                'reportData' => $reportData,
                'filters' => [
                    'start_date' => $this->data['start_date'],
                    'end_date' => $this->data['end_date'],
                    'branch' => $this->getBranchNameForReport(),
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

            $subscriptions = $subscriptions->sortBy(function ($subscription) {
                return $subscription->member->last_name; // Assuming last_name exists on member
            });

            $headers = [
                'Content-Type' => 'text/csv',
                'Content-Disposition' => 'attachment; filename="subscription-report-' . now()->format('Y-m-d') . '.csv"'
            ];

            $callback = function () use ($subscriptions) {
                $file = fopen('php://output', 'w');

                // Define CSV headers
                $csvHeaders = [
                    'ID', 'CID', 'Member Name', 'Branch', 'Email', 'Phone', 'Address', 'Age', 'Gender', 'Marital Status',
                    'Occupation', 'Joined Date', 'Insurance', 'Status', 'Account Name', 'Account Number', 'Amount',
                    'Payment Date', 'Expires Date', 'Activated Date', 'Note: Date Format'
                ];
                fputcsv($file, $csvHeaders);

                // Prepare member and subscription data for each row
                foreach ($subscriptions as $subscription) {
                    $member = $subscription->member;
                    $productAccount = $subscription->productAccount;

                    $status = $member->is_active ?? true
                        ? $subscription->status
                        : 'archived';

                    // Row data preparation
                    $row = [
                        $member->id,
                        $member->cid,
                        $member->full_name,
                        $member->branch?->branch_name,
                        $member->email,
                        $member->contact_number,
                        $member->full_address,
                        $member->age,
                        $member->gender_label,
                        $member->marital_status_label,
                        $member->occupation,
                        $member->created_at->format('m/d/Y'),
                        $subscription->insurance?->insurance_name,
                        $status,
                        $productAccount?->product_name,
                        $productAccount?->account_number,
                        $subscription->amount,
                        $subscription->payment_date?->format('m/d/Y'),
                        $subscription->expires_at?->format('m/d/Y'),
                        $subscription->activated_at?->format('m/d/Y'),
                        'month/day/Year (12/18/2025)', // Static date format note
                    ];

                    fputcsv($file, $row);
                }

                fclose($file);
            };

            return response()->stream($callback, 200, $headers);
        } catch (\Exception $e) {
            Notification::make()
                ->title('Export Failed')
                ->body('Failed to generate CSV report: ' . $e->getMessage())
                ->danger()
                ->send();
        }
    }


    public function exportSummaryPdf()
    {
        try {
            $reportData = $this->generateReportData($this->getTableQuery());

            $pdf = Pdf::loadView('reports.subscription-summary-pdf', [
                'reportData' => $reportData,
                'filters' => [
                    'start_date' => $this->data['start_date'],
                    'end_date' => $this->data['end_date'],
                    'branch' => $this->getBranchNameForReport(),
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

    protected function getBranchNameForReport(): string
    {
        if ($this->isSuperAdmin()) {
            if ($this->data['branch_id'] ?? null) {
                return Branch::find($this->data['branch_id'])?->branch_name ?? 'Unknown Branch';
            }
            return 'All Branches';
        }

        $userBranch = $this->getUserBranchNumber();
        if ($userBranch) {
            return Branch::where('branch_number', $userBranch)->first()?->branch_name ?? 'Unknown Branch';
        }

        return 'No Branch Assigned';
    }

    protected function generateReportData($subscriptionsQuery)
    {
        $totals = $subscriptionsQuery->clone()
            ->selectRaw('SUM(amount) as totalAmount, COUNT(*) as totalSubscriptions')
            ->first();

        $totalMembers = $subscriptionsQuery->clone()
            ->distinct('member_id')
            ->count('member_id');

        $statusCounts = $subscriptionsQuery->clone()
            ->selectRaw("
                SUM(CASE WHEN members.is_active = 0 THEN 1 ELSE 0 END) as archived,
                SUM(CASE WHEN subscriptions.activated_at <= NOW() AND subscriptions.expires_at > NOW() AND members.is_active = 1 THEN 1 ELSE 0 END) as active,
                SUM(CASE WHEN subscriptions.expires_at <= NOW() AND members.is_active = 1 THEN 1 ELSE 0 END) as expired,
                SUM(CASE WHEN subscriptions.activated_at > NOW() AND members.is_active = 1 THEN 1 ELSE 0 END) as future
            ")
            ->join('members', 'subscriptions.member_id', '=', 'members.id')
            ->first();

        $branchStats = $subscriptionsQuery->clone()
            ->join('members', 'subscriptions.member_id', '=', 'members.id')
            ->join('branches', 'members.branch_number', '=', 'branches.branch_number')
            ->selectRaw("
                branches.branch_name,
                COUNT(subscriptions.id) as totalSubscriptions,
                COUNT(DISTINCT subscriptions.member_id) as totalMembers,
                SUM(subscriptions.amount) as amount,
                SUM(CASE WHEN members.is_active = 0 THEN 1 ELSE 0 END) as archived,
                SUM(CASE WHEN subscriptions.activated_at <= NOW() AND subscriptions.expires_at > NOW() AND members.is_active = 1 THEN 1 ELSE 0 END) as active,
                SUM(CASE WHEN subscriptions.expires_at <= NOW() AND members.is_active = 1 THEN 1 ELSE 0 END) as expired,
                SUM(CASE WHEN subscriptions.activated_at > NOW() AND members.is_active = 1 THEN 1 ELSE 0 END) as future
            ")
            ->groupBy('branches.branch_name')
            ->get()
            ->keyBy('branch_name')
            ->toArray();

        return [
            'totalAmount'        => $totals->totalAmount ?? 0,
            'totalSubscriptions' => $totals->totalSubscriptions ?? 0,
            'totalMembers'       => $totalMembers,
            'statusCounts'       => [
                'active'   => $statusCounts->active ?? 0,
                'expired'  => $statusCounts->expired ?? 0,
                'future'   => $statusCounts->future ?? 0,
                'archived' => $statusCounts->archived ?? 0,
            ],
            'activeTotal'  => ($statusCounts->active ?? 0) + ($statusCounts->future ?? 0),
            'branchStats'  => $branchStats,
        ];
    }

    public function getReportSummary()
    {
        return $this->generateReportData($this->getTableQuery());
    }

    public function applyFilters(): void
    {
        Notification::make()
            ->title('Filters Applied')
            ->body('Your report has been refreshed with the selected filters.')
            ->success()
            ->send();
    }
}
