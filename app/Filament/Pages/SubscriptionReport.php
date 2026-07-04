<?php

namespace App\Filament\Pages;

use App\Models\Branch;
use App\Models\Subscription;
use Barryvdh\DomPDF\Facade\Pdf;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Form;
use Filament\Forms\Set;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Tables\Actions\Action;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

class SubscriptionReport extends Page implements HasTable
{
    use InteractsWithTable;

    protected static ?string $navigationIcon  = 'heroicon-o-chart-bar';
    protected static ?string $navigationLabel = 'Subscription Reports';
    protected static ?string $title           = 'Subscription Reports';
    protected static ?string $navigationGroup = 'Reports';
    protected static string  $view            = 'filament.pages.subscription-report';

    public ?array $data = [];
    public $startDate;
    public $endDate;
    public $selectedBranch;

    // -------------------------------------------------------------------------
    // Lifecycle
    // -------------------------------------------------------------------------

    public function mount(): void
    {
        $this->form->fill([
            'start_date' => now()->startOfMonth(),
            'end_date'   => now()->endOfMonth(),
            'branch_id'  => $this->isSuperAdmin() ? null : $this->getUserBranchNumber(),
        ]);
    }

    // -------------------------------------------------------------------------
    // Form
    // -------------------------------------------------------------------------

    public function form(Form $form): Form
    {
        $isSuperAdmin = $this->isSuperAdmin();

        return $form
            ->schema([
                Section::make('Report Filters')
                    ->columns($isSuperAdmin ? 3 : 2)
                    ->schema([
                        DatePicker::make('start_date')
                            ->label('Start Date')
                            ->default(now()->startOfMonth())
                            ->native(true)
                            ->required()
                            ->reactive()
                            ->afterStateUpdated(fn ($state) => $this->startDate = $state)
                            ->rules(['date']),

                        DatePicker::make('end_date')
                            ->label('End Date')
                            ->default(now()->endOfMonth())
                            ->native(true)
                            ->required()
                            ->reactive()
                            ->afterStateUpdated(function ($state, Set $set) {
                                if (!$state) return;

                                $parsed = \Carbon\Carbon::createFromFormat('Y-m-d', $state);
                                if (!$parsed || $parsed->format('Y-m-d') !== $state) {
                                    $set('end_date', null);
                                    session()->flash('error', 'End Date is not a valid date.');
                                    return;
                                }

                                $this->endDate = $state;

                                if ($this->startDate && $state < $this->startDate) {
                                    session()->flash('error', 'End Date must be on or after the Start Date.');
                                }
                            })
                            ->rules([
                                'date',
                                function (string $attribute, mixed $value, \Closure $fail) {
                                    if (!$value) return;

                                    $parsed = \Carbon\Carbon::createFromFormat('Y-m-d', $value);
                                    if (!$parsed || $parsed->format('Y-m-d') !== $value) {
                                        $fail('The end date is not a valid date.');
                                        return;
                                    }

                                    if ($this->startDate && $value < $this->startDate) {
                                        $fail('The end date must be on or after the start date.');
                                    }
                                },
                            ])
                            ->helperText('Must be on or after the start date.'),

                        Select::make('branch_id')
                            ->label('Branch')
                            ->options($this->getBranchOptions())
                            ->placeholder('All Branches')
                            ->reactive()
                            ->afterStateUpdated(fn ($state) => $this->selectedBranch = $state)
                            ->visible($isSuperAdmin),
                    ]),
            ])
            ->statePath('data');
    }

    // -------------------------------------------------------------------------
    // Table
    // -------------------------------------------------------------------------

    public function table(Table $table): Table
    {
        return $table
            ->query($this->getTableQuery())
            ->columns([
                TextColumn::make('member.cid')
                    ->label('CID')
                    ->sortable()
                    ->searchable()
                    ->placeholder('Unknown')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('member.full_name')
                    ->label('Member Name')
                    ->sortable()
                    ->searchable(),

                TextColumn::make('member.branch.branch_name')
                    ->label('Branch')
                    ->sortable()
                    ->visible($this->isSuperAdmin()),

                TextColumn::make('insurance.insurance_name')
                    ->label('Insurance')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('amount')
                    ->label('Amount')
                    ->money('PHP')
                    ->sortable(),

                TextColumn::make('payment_date')->label('Payment Date')->date()->sortable(),
                TextColumn::make('activated_at')->label('Activated')->date()->sortable(),
                TextColumn::make('expires_at')->label('Expires')->date()->sortable(),

                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->color(function (string $state, $column) {
                        $isActive = $column->getRecord()->member->is_active ?? true;

                        if (! $isActive) return 'gray';

                        return match ($state) {
                            'active'  => 'success',
                            'expired' => 'danger',
                            'future'  => 'warning',
                            default   => 'gray',
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
                                'active'   => 'Active',
                                'expired'  => 'Expired',
                                'future'   => 'Future',
                                'archived' => 'Archived',
                            ])
                            ->placeholder('All Statuses'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        $status = $data['status'] ?? null;

                        if (! $status) return $query;

                        $now = now();

                        return match ($status) {
                            'active' => $query
                                ->where('activated_at', '<=', $now)
                                ->where('expires_at', '>', $now)
                                ->whereHas('member', fn ($q) => $q->where('is_active', true)),

                            'expired' => $query
                                ->where('expires_at', '<=', $now)
                                ->whereHas('member', fn ($q) => $q->where('is_active', true)),

                            'future' => $query
                                ->where('activated_at', '>', $now)
                                ->whereHas('member', fn ($q) => $q->where('is_active', true)),

                            'archived' => $query
                                ->whereHas('member', fn ($q) => $q->where('is_active', false)),

                            default => $query,
                        };
                    }),
            ])
            ->headerActions([
                Action::make('export_pdf')
                    ->label('Export PDF')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('success')
                    ->action('exportPdf'),

                Action::make('export_excel')
                    ->label('Export Excel')
                    ->icon('heroicon-o-table-cells')
                    ->color('primary')
                    ->action('exportExcel'),

                Action::make('export_summary_pdf')
                    ->label('Export Summary PDF')
                    ->icon('heroicon-o-document-text')
                    ->color('success')
                    ->action('exportSummaryPdf'),
            ])
            ->defaultSort('payment_date', 'desc')
            ->paginated([10, 25, 50, 100]);
    }

    // -------------------------------------------------------------------------
    // Exports
    // -------------------------------------------------------------------------

    public function exportPdf()
    {
        try {
            $maxRows      = 2000;
            $query        = $this->getTableQuery();
            $subscriptions = $query->take($maxRows + 1)->get();

            if ($subscriptions->count() > $maxRows) {
                Notification::make()
                    ->title('Too Much Data')
                    ->body("PDF export is limited to {$maxRows} rows. Please narrow your filters.")
                    ->danger()
                    ->send();
                return;
            }

            $pdf = Pdf::loadView('reports.subscription-report-pdf', [
                'subscriptions' => $subscriptions,
                'reportData'    => $this->generateReportData($query),
                'filters'       => $this->currentFilters(),
                'generatedAt'   => now()->format('F d, Y g:i A'),
            ]);

            return response()->streamDownload(
                fn () => print($pdf->stream()),
                'subscription-report-' . now()->format('Y-m-d') . '.pdf'
            );
        } catch (\Exception $e) {
            $this->exportFailedNotification('PDF', $e);
        }
    }

    public function exportExcel()
    {
        try {
            $subscriptions = $this->getTableQuery()->get()->sortBy('member.last_name');

            $filename = 'subscription-report-' . now()->format('Y-m-d') . '.xlsx';
            $filepath = storage_path('app/temp/' . $filename);

            // Ensure temp directory exists
            if (!file_exists(storage_path('app/temp'))) {
                mkdir(storage_path('app/temp'), 0755, true);
            }

            $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
            $sheet = $spreadsheet->getActiveSheet();

            // Headers
            $headers = [
                'ID', 'CID', 'Member Name', 'Branch', 'Email', 'Phone', 'Address',
                'Age', 'Birth Date', 'Gender', 'Marital Status', 'Occupation',
                'Insurance', 'Status', 'Joined Date', 'Account Name', 'Account Number', 'Amount',
                'Payment Date', 'Subscription Date', 'Remarks', 'Expires Date', 'Note: Date Format',
            ];

            // Create headers
            foreach ($headers as $colIndex => $header) {
                $cell = $sheet->getCellByColumnAndRow($colIndex + 1, 1);
                $cell->setValue($header);

                // Make all headers bold
                $sheet->getStyleByColumnAndRow($colIndex + 1, 1)
                    ->getFont()
                    ->setBold(true);
            }

            // Highlight ONLY selected headers (P–U)
            $highlightColumns = ['P', 'Q', 'R', 'S', 'T', 'U'];

            foreach ($highlightColumns as $column) {
                $sheet->getStyle($column . '1')->applyFromArray([
                    'fill' => [
                        'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                        'startColor' => [
                            'argb' => 'C6EFCE', // Light Green
                        ],
                    ],
                    'font' => [
                        'bold' => true,
                        'color' => [
                            'argb' => '006100', // Dark Green Text
                        ],
                    ],
                ]);
            }

            // Amount column index (1-based): 'Amount' is the 17th column
            $amountColIndex = 18;

            $rowIndex = 2;
            foreach ($subscriptions as $sub) {
                $member  = $sub->member;
                $account = $sub->productAccount;
                $status  = ($member->is_active ?? true) ? $sub->status : 'archived';

                $row = [
                    $member->id,
                    $member->cid,
                    $member->full_name,
                    $member->branch?->branch_name,
                    $member->email,
                    $member->contact_number,
                    $member->full_address,
                    $member->age,
                    $member->birth_date,
                    $member->gender_label,
                    $member->marital_status_label,
                    $member->occupation,
                    $sub->insurance?->insurance_name,
                    $status,
                    $member->created_at->format('m/d/Y'),
                    $account?->product_name,
                    $account?->account_number,
                    $sub->amount,
                    $sub->payment_date?->format('m/d/Y'),
                    $sub->activated_at?->format('m/d/Y'),
                    $sub->remark,
                    $sub->expires_at?->format('m/d/Y'),
                    'month/day/Year (12/18/2025)',
                ];

                foreach ($row as $colIndex => $value) {
                    $sheet->getCellByColumnAndRow($colIndex + 1, $rowIndex)->setValue($value);
                }

                // Conditional highlight based on Amount (column 17)
                $amount = $sub->amount;

                if ($amount == 180) {
                    $color = 'FFFF00'; // Yellow
                } elseif ($amount == 360) {
                    $color = '00FF00'; // Green
                } else {
                    $color = 'FF0000'; // Red
                }

                $sheet->getStyleByColumnAndRow($amountColIndex, $rowIndex)
                    ->getFill()
                    ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
                    ->getStartColor()
                    ->setARGB($color);

                $rowIndex++;
            }

            // Auto-size columns
            foreach (range(1, count($headers)) as $col) {
                $sheet->getColumnDimensionByColumn($col)->setAutoSize(true);
            }

            $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
            $writer->save($filepath);

            return response()->download($filepath, $filename, [
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            ])->deleteFileAfterSend(true);

        } catch (\Exception $e) {
            $this->exportFailedNotification('Excel', $e);
        }
    }

    public function exportSummaryPdf()
    {
        try {
            $pdf = Pdf::loadView('reports.subscription-summary-pdf', [
                'reportData'  => $this->generateReportData($this->getTableQuery()),
                'filters'     => $this->currentFilters(),
                'generatedAt' => now()->format('F d, Y g:i A'),
            ]);

            return response()->streamDownload(
                fn () => print($pdf->stream()),
                'subscription-summary-' . now()->format('Y-m-d') . '.pdf'
            );
        } catch (\Exception $e) {
            $this->exportFailedNotification('Summary PDF', $e);
        }
    }

    // -------------------------------------------------------------------------
    // Query
    // -------------------------------------------------------------------------

    protected function getTableQuery(): Builder
    {
        $query = Subscription::query()
            ->with(['member.branch', 'insurance'])
            ->whereHas('member');

        $branchNumber = $this->isSuperAdmin()
            ? ($this->data['branch_id'] ?? null)
            : $this->getUserBranchNumber();

        if ($branchNumber) {
            $query->whereHas('member', fn (Builder $q) => $q->where('branch_number', $branchNumber));
        }

        if ($startDate = $this->data['start_date'] ?? null) {
            $query->where('payment_date', '>=', $startDate);
        }

        if ($endDate = $this->data['end_date'] ?? null) {
            $query->where('payment_date', '<=', $endDate);
        }

        return $query;
    }

    // -------------------------------------------------------------------------
    // Report Data
    // -------------------------------------------------------------------------

    protected function generateReportData(Builder $query): array
    {
        $totals = (clone $query)
            ->selectRaw('SUM(amount) as totalAmount, COUNT(*) as totalSubscriptions')
            ->first();

        $totalMembers = (clone $query)->distinct('member_id')->count('member_id');

        $statusCounts = (clone $query)
            ->join('members', 'subscriptions.member_id', '=', 'members.id')
            ->selectRaw("
                SUM(CASE WHEN members.is_active = 0 THEN 1 ELSE 0 END) as archived,
                SUM(CASE WHEN subscriptions.activated_at <= NOW() AND subscriptions.expires_at > NOW() AND members.is_active = 1 THEN 1 ELSE 0 END) as active,
                SUM(CASE WHEN subscriptions.expires_at <= NOW() AND members.is_active = 1 THEN 1 ELSE 0 END) as expired,
                SUM(CASE WHEN subscriptions.activated_at > NOW() AND members.is_active = 1 THEN 1 ELSE 0 END) as future
            ")
            ->first();

        $branchStats = (clone $query)
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

        $active   = $statusCounts->active   ?? 0;
        $expired  = $statusCounts->expired  ?? 0;
        $future   = $statusCounts->future   ?? 0;
        $archived = $statusCounts->archived ?? 0;

        return [
            'totalAmount'        => $totals->totalAmount        ?? 0,
            'totalSubscriptions' => $totals->totalSubscriptions ?? 0,
            'totalMembers'       => $totalMembers,
            'statusCounts'       => compact('active', 'expired', 'future', 'archived'),
            'activeTotal'        => $active + $future,
            'branchStats'        => $branchStats,
        ];
    }

    public function getReportSummary(): array
    {
        return $this->generateReportData($this->getTableQuery());
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    protected function isSuperAdmin(): bool
    {
        return Auth::user()->hasRole('super_admin');
    }

    protected function getUserBranchNumber(): ?string
    {
        return Auth::user()->branch?->branch_number ?? null;
    }

    protected function getBranchOptions(): array
    {
        if ($this->isSuperAdmin()) {
            return Branch::pluck('branch_name', 'branch_number')->toArray();
        }

        $branchNumber = $this->getUserBranchNumber();

        return $branchNumber
            ? Branch::where('branch_number', $branchNumber)->pluck('branch_name', 'branch_number')->toArray()
            : [];
    }

    protected function getBranchNameForReport(): string
    {
        if ($this->isSuperAdmin()) {
            $branchId = $this->data['branch_id'] ?? null;
            return $branchId
                ? Branch::find($branchId)?->branch_name ?? 'Unknown Branch'
                : 'All Branches';
        }

        $branchNumber = $this->getUserBranchNumber();

        return $branchNumber
            ? Branch::where('branch_number', $branchNumber)->value('branch_name') ?? 'Unknown Branch'
            : 'No Branch Assigned';
    }

    private function currentFilters(): array
    {
        return [
            'start_date' => $this->data['start_date'] ?? null,
            'end_date'   => $this->data['end_date']   ?? null,
            'branch'     => $this->getBranchNameForReport(),
        ];
    }

    private function exportFailedNotification(string $type, \Exception $e): void
    {
        Notification::make()
            ->title("Export Failed")
            ->body("Failed to generate {$type} report: {$e->getMessage()}")
            ->danger()
            ->send();
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
