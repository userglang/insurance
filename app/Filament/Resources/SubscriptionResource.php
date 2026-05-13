<?php

namespace App\Filament\Resources;

use App\Filament\Resources\SubscriptionResource\Pages;
use App\Models\Insurance;
use App\Models\ProductAccount;
use App\Models\Subscription;
use Filament\Forms;
use Filament\Forms\Components\Grid;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Support\Enums\FontWeight;
use Filament\Tables;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;

class SubscriptionResource extends Resource
{
    protected static ?string $model = Subscription::class;

    protected static ?string $navigationIcon  = 'heroicon-o-credit-card';
    protected static ?string $navigationGroup = 'Subscriptions';
    protected static ?int    $navigationSort  = 1;

    // -------------------------------------------------------------------------
    // Navigation
    // -------------------------------------------------------------------------

    public static function getNavigationBadge(): ?string
    {
        $user = Auth::user();

        if ($user && ! $user->hasRole('super_admin')) {
            $branchNumber = $user->branch?->branch_number;

            return Cache::remember(
                "subscriptions_count_branch_{$branchNumber}",
                now()->addMinutes(10),
                fn () => Subscription::join('members', 'subscriptions.member_id', '=', 'members.id')
                    ->where('members.branch_number', $branchNumber)
                    ->active()
                    ->count()
            );
        }

        return Cache::remember(
            'subscriptions_count_all_active',
            now()->addMinutes(10),
            fn () => Subscription::active()->count()
        );
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'success';
    }

    // -------------------------------------------------------------------------
    // Form
    // -------------------------------------------------------------------------

    public static function form(Form $form): Form
    {
        return $form->schema([
            ...ProductAccountResource::getAccountDetails(),
            ...static::getSubscriptionDetails(),
            ...static::getProductAccountID(),
            ...static::getAdditionalInformation(),
        ]);
    }

    public static function getSubscriptionDetails(): array
    {
        return [
            Forms\Components\Section::make('Subscription Details')
                ->description('Configure insurance subscription information and payment details')
                ->schema([
                    Grid::make(2)->schema([
                        Forms\Components\Select::make('insurance_id')
                            ->label('Insurance Name')
                            ->options(fn () => Insurance::where('is_active', true)->pluck('insurance_name', 'id'))
                            ->preload()
                            ->required()
                            ->placeholder('Select an account...')
                            ->default(fn () => Insurance::where('is_active', true)->value('id')),

                        Forms\Components\DatePicker::make('activated_at')
                            ->label('Subscription Date')
                            ->required()
                            ->default(now())
                            // ->minDate(now()->subDays(60))
                            // ->maxDate(now()->addDays(60))
                            ->displayFormat('M j, Y'),

                        Forms\Components\TextInput::make('amount')
                            ->label('Subscription Amount')
                            ->required()
                            ->numeric()
                            ->prefix('₱')
                            ->minValue(1)
                            ->step(0.01)
                            ->default(180.00),

                        Forms\Components\DatePicker::make('payment_date')
                            ->label('Payment Date')
                            ->required()
                            ->default(now())
                            // ->minDate(now()->subDays(60))
                            // ->maxDate(now()->addDays(60))
                            ->displayFormat('M j, Y'),
                    ]),
                ]),
        ];
    }

    public static function getProductAccountID(): array
    {
        return [
            Forms\Components\Section::make('Account Selection')
                ->description('Select an existing account or create a new one for the member')
                ->schema([
                    Forms\Components\Select::make('product_account_id')
                        ->label('Account')
                        ->options(function (callable $get) {
                            $memberId = $get('member_id');

                            if (! $memberId) return [];

                            $accounts = ProductAccount::where('member_id', $memberId)
                                ->get()
                                ->mapWithKeys(function ($account) {
                                    $name = strtoupper($account->product_name);
                                    $label = $name === 'CASH'
                                        ? 'CASH'
                                        : "{$name} ({$account->account_number})";

                                    return [$account->id => $label];
                                })
                                ->toArray();

                            $cashExists = ProductAccount::where('member_id', $memberId)
                                ->whereRaw('UPPER(TRIM(product_name)) = ?', ['CASH'])
                                ->exists();

                            if (! $cashExists) {
                                $accounts = ['0' => 'CASH'] + $accounts;
                            }

                            return $accounts;
                        })
                        ->createOptionForm([
                            ...ProductAccountResource::getProductAccountDetails(),
                        ])
                        ->createOptionUsing(function (array $data, callable $get) {
                            $memberId = $get('member_id');

                            if (! $memberId) {
                                throw new \RuntimeException('Please select a member before creating an account.');
                            }

                            return ProductAccount::create([
                                'member_id'      => $memberId,
                                'product_name'   => $data['product_name'],
                                'account_number' => $data['account_number'],
                            ])->id;
                        })
                        ->disabled(fn (callable $get) => ! $get('member_id'))
                        ->helperText(fn (callable $get) => ! $get('member_id')
                            ? 'Select a member first to choose or create an account.'
                            : null
                        )
                        ->reactive()
                        ->required()
                        ->preload()
                        ->placeholder('Select an account...')
                        ->columnSpan(2),
                ]),
        ];
    }

    public static function getAdditionalInformation(): array
    {
        return [
            Forms\Components\Section::make('Additional Information')
                ->collapsible()
                ->schema([
                    Forms\Components\Textarea::make('remark')
                        ->label('Notes/Remarks')
                        ->placeholder('Add any additional notes about this subscription...')
                        ->rows(3)
                        ->columnSpanFull(),
                ]),
        ];
    }

    // -------------------------------------------------------------------------
    // Table
    // -------------------------------------------------------------------------

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('member.full_name')
                    ->label('Member Name')
                    ->searchable(['first_name', 'last_name', 'middle_name'])

                    ->description(fn ($record) => '🏢 ' . ($record->member->branch?->branch_name ?? 'No branch'))
                    ->weight(FontWeight::Medium),

                Tables\Columns\TextColumn::make('member.birth_date')
                    ->label('Birth Date')
                    ->date('M d, Y')
                    ->description(fn ($record) => $record->member->age
                        ? "{$record->member->age} year" . ($record->member->age > 1 ? 's' : '') . ' old'
                        : null
                    ),

                Tables\Columns\TextColumn::make('insurance.insurance_name')
                    ->label('Insurance')
                    ->searchable()
                    ->sortable()
                    ->wrap()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('amount')
                    ->label('Amount')
                    ->money('PHP')
                    ->sortable()
                    ->weight(FontWeight::Medium)
                    ->description(fn ($record) => $record->productAccount
                        ? strtoupper($record->productAccount->product_name) . ' · ' . ($record->productAccount->account_number ?? 'No Account Number')
                        : 'No Account'
                    ),

                Tables\Columns\TextColumn::make('payment_date')
                    ->label('Payment Date')
                    ->dateTime('M j, Y')
                    ->weight(FontWeight::Medium),

                Tables\Columns\TextColumn::make('activated_at')
                    ->label('Activated')
                    ->dateTime('M j, Y')
                    ->sortable()
                    ->color('success')
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('expires_at')
                    ->label('Expires')
                    ->dateTime('M j, Y')
                    ->sortable()
                    ->color(fn (Subscription $record) => match (true) {
                        $record->isExpired()           => 'danger',
                        $record->daysRemaining() <= 7  => 'danger',
                        $record->daysRemaining() <= 30 => 'warning',
                        default                        => 'success',
                    })
                    ->description(fn (Subscription $record) => $record->isExpired()
                        ? 'Expired'
                        : $record->daysRemaining() . ' days left'
                    ),

                Tables\Columns\TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->color(fn (string $state) => match ($state) {
                        'active'  => 'success',
                        'expired' => 'danger',
                        'future'  => 'warning',
                        default   => 'gray',
                    })
                    ->formatStateUsing(fn (string $state) => ucfirst($state)),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Created')
                    ->dateTime('M j, Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])

            // -----------------------------------------------------------------
            // Filters
            // -----------------------------------------------------------------
            ->filters([
                Filter::make('payment_date_range')
                    ->label('Payment Date Range')
                    ->form([
                        Forms\Components\DatePicker::make('payment_from')
                            ->label('Payments From')
                            ->placeholder('Select start date')
                            ->default(now()->subMonth()->startOfMonth()),

                        Forms\Components\DatePicker::make('payment_until')
                            ->label('Payments Until')
                            ->placeholder('Select end date')
                            ->default(now()->subMonth()->endOfMonth()),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['payment_from'],
                                fn (Builder $q, $date) => $q->whereDate('payment_date', '>=', $date)
                            )
                            ->when(
                                $data['payment_until'],
                                fn (Builder $q, $date) => $q->whereDate('payment_date', '<=', $date)
                            );
                    })
                    ->indicateUsing(function (array $data): array {
                        $indicators = [];

                        if ($data['payment_from'] ?? null) {
                            $indicators[] = 'Payments from: ' . \Carbon\Carbon::parse($data['payment_from'])->format('M j, Y');
                        }

                        if ($data['payment_until'] ?? null) {
                            $indicators[] = 'Payments until: ' . \Carbon\Carbon::parse($data['payment_until'])->format('M j, Y');
                        }

                        return $indicators;
                    }),
                SelectFilter::make('status')
                    ->options([
                        'active'  => 'Active',
                        'expired' => 'Expired',
                        'future'  => 'Future',
                    ])
                    ->query(fn (Builder $query, array $data) => match ($data['value'] ?? null) {
                        'active'  => $query->active(),
                        'expired' => $query->expired(),
                        'future'  => $query->future(),
                        default   => $query,
                    }),

                Filter::make('expires_soon')
                    ->label('Expires Soon (30 days)')
                    ->query(fn (Builder $query) => $query
                        ->where('expires_at', '>', now())
                        ->where('expires_at', '<=', now()->addDays(30))
                    ),

                SelectFilter::make('insurance')
                    ->label('Insurance Name')
                    ->relationship('insurance', 'insurance_name')
                    ->searchable()
                    ->preload(),

                Filter::make('duplicates')
                    ->label('Members with Duplicate Active Subscriptions')
                    ->toggle()
                    ->query(fn (Builder $query) => $query->whereIn('id', function ($sub) {
                        $sub->selectRaw('id')
                            ->fromRaw('(
                                SELECT
                                    id,
                                    member_id,
                                    ROW_NUMBER() OVER (PARTITION BY member_id ORDER BY expires_at DESC) as rn,
                                    COUNT(*) OVER (PARTITION BY member_id) as subscription_count
                                FROM subscriptions
                                WHERE expires_at > ?
                            ) as ranked_subscriptions', [now()])
                            ->whereRaw('rn = 1 AND subscription_count > 1');
                    })),
            ])

            // -----------------------------------------------------------------
            // Actions
            // -----------------------------------------------------------------
            ->actions([
                Tables\Actions\ActionGroup::make([
                    Tables\Actions\ViewAction::make()
                        ->label('View Subscription')
                        ->icon('heroicon-m-eye')
                        ->color('info'),

                    Tables\Actions\EditAction::make()
                        ->label('Edit Subscription')
                        ->icon('heroicon-m-pencil-square')
                        ->color('warning'),
                ])
                    ->label('Actions')
                    ->icon('heroicon-m-ellipsis-vertical')
                    ->size('sm')
                    ->tooltip('More actions')
                    ->color('gray'),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                    Tables\Actions\BulkAction::make('export')
                        ->label('Export Selected')
                        ->icon('heroicon-o-document-arrow-down')
                        ->color('info')
                        ->action(function (\Illuminate\Support\Collection $records) {

                            $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
                            $sheet       = $spreadsheet->getActiveSheet();

                            $headers = [
                                'ID', 'CID', 'Name', 'Branch', 'Email', 'Phone', 'Address',
                                'Age', 'Birth Date', 'Gender', 'Marital Status', 'Occupation', 'Employment Status',
                                'Status', 'Joined Date', 'Account Name', 'Account Number', 'Amount',
                                'Payment Date', 'Subscription Date', 'Remarks', 'Note: Date Format',
                            ];

                            $sheet->fromArray($headers, null, 'A1');

                            // ✅ Highlight ONLY selected headers (light green)
                            $highlightColumns = ['P', 'Q', 'R', 'S', 'T', 'U'];

                            foreach ($highlightColumns as $col) {
                                $sheet->getStyle("{$col}1")
                                    ->getFill()
                                    ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
                                    ->getStartColor()
                                    ->setARGB('FFCCFFCC');

                                $sheet->getStyle("{$col}1")
                                    ->getFont()
                                    ->setBold(true);
                            }

                            $totalColumns = count($headers);
                            $totalRows    = $records->count() + 1;

                            // Set all columns to text except Amount (col R = index 18)
                            foreach (range(1, $totalColumns) as $colIndex) {
                                $colLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colIndex);

                                if ($colIndex === 18) continue;

                                $sheet->getStyle("{$colLetter}1:{$colLetter}{$totalRows}")
                                    ->getNumberFormat()
                                    ->setFormatCode(\PhpOffice\PhpSpreadsheet\Style\NumberFormat::FORMAT_TEXT);
                            }

                            $lastColumn = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($totalColumns);

                            $row = 2;
                            foreach ($records->sortBy(fn ($r) => $r->member?->last_name) as $record) {
                                $member  = $record->member;
                                $account = $record->productAccount;

                                $rowData = [
                                    (string) $member->id,
                                    (string) ($member?->cid ?? ''),
                                    (string) ($member?->full_name ?? ''),
                                    (string) ($member?->branch?->branch_name ?? 'N/A'),
                                    (string) ($member?->email ?? ''),
                                    (string) ($member?->contact_number ?? ''),
                                    (string) ($member?->full_address ?? ''),
                                    (string) ($member?->age ?? ''),
                                    (string) ($member?->birth_date?->format('m/d/Y') ?? ''),
                                    (string) ($member?->gender ?? ''),
                                    (string) ($member?->marital_status ?? ''),
                                    (string) ($member?->occupation ?? ''),
                                    (string) ($member?->employment_status ?? ''),
                                    (string) (ucfirst($record->status ?? '')),
                                    (string) ($member?->created_at?->format('m/d/Y') ?? ''),
                                    (string) (strtoupper($account?->product_name ?? '')),
                                    (string) ($account?->account_number ?? ''),
                                    $record->amount ?? 0,
                                    '',
                                    (string) ($record->activated_at?->format('m/d/Y') ?? ''),
                                    '',
                                    'month/day/Year (12/18/2025)',
                                ];

                                foreach ($rowData as $colIndex => $value) {
                                    $colLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colIndex + 1);

                                    if ($colIndex === 17) {
                                        $sheet->setCellValue("{$colLetter}{$row}", $value);
                                        $sheet->getStyle("{$colLetter}{$row}")
                                            ->getNumberFormat()
                                            ->setFormatCode('#,##0.00');
                                    } else {
                                        $sheet->setCellValueExplicit(
                                            "{$colLetter}{$row}",
                                            $value,
                                            \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING
                                        );
                                    }
                                }

                                // Row highlight by age
                                $age = (int) ($member?->age ?? 0);

                                if ($age >= 70) {
                                    $sheet->getStyle("A{$row}:{$lastColumn}{$row}")
                                        ->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
                                        ->getStartColor()->setARGB('FFFF0000');
                                    $sheet->getStyle("A{$row}:{$lastColumn}{$row}")
                                        ->getFont()->getColor()->setARGB('FFFFFFFF');

                                } elseif ($age >= 65) {
                                    $sheet->getStyle("A{$row}:{$lastColumn}{$row}")
                                        ->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
                                        ->getStartColor()->setARGB('FFFF6600');
                                    $sheet->getStyle("A{$row}:{$lastColumn}{$row}")
                                        ->getFont()->getColor()->setARGB('FFFFFFFF');
                                }

                                // Highlight Amount if invalid
                                $amount       = (float) ($record->amount ?? 0);
                                $validAmounts = [180, 360];

                                if (! in_array($amount, $validAmounts)) {
                                    $sheet->getStyle("R{$row}")
                                        ->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
                                        ->getStartColor()->setARGB('FFFFFF00');
                                    $sheet->getStyle("R{$row}")
                                        ->getFont()->getColor()->setARGB('FF000000');
                                }

                                $row++;
                            }

                            // SUM row
                            $sumRow = $row;

                            $sheet->setCellValueExplicit(
                                "A{$sumRow}",
                                'TOTAL',
                                \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING
                            );

                            $sheet->setCellValue("R{$sumRow}", "=SUM(R2:R" . ($sumRow - 1) . ")");

                            $sheet->getStyle("R{$sumRow}")
                                ->getNumberFormat()
                                ->setFormatCode('#,##0.00');

                            $sheet->getStyle("A{$sumRow}:{$lastColumn}{$sumRow}")
                                ->getFont()
                                ->setBold(true);

                            $filename = 'subscriptions_export-' . now()->format('Y-m-d-H-i-s') . '.xlsx';
                            $writer   = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);

                            return response()->streamDownload(function () use ($writer) {
                                $writer->save('php://output');
                            }, $filename, [
                                'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                            ]);
                        }),
                ]),

            ])

            // -----------------------------------------------------------------
            // Table Options
            // -----------------------------------------------------------------
            ->defaultSort('created_at', 'desc')
            ->striped()
            ->paginated([10, 25, 50])
            ->defaultPaginationPageOption(10)
            ->deferLoading()
            ->searchOnBlur()
            ->searchDebounce('750ms')
            ->poll(null)
            ->persistFiltersInSession()
            ->persistSortInSession()
            ->persistSearchInSession()
            ->emptyStateHeading('No subscriptions found')
            ->emptyStateDescription('Get started by adding your first subscription.')
            ->emptyStateIcon('heroicon-o-credit-card');
    }

    // -------------------------------------------------------------------------
    // Relations & Pages
    // -------------------------------------------------------------------------

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListSubscriptions::route('/'),
            'create' => Pages\CreateSubscription::route('/create'),
            'edit'   => Pages\EditSubscription::route('/{record}/edit'),
        ];
    }
}
