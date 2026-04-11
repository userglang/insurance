<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ProductAccountResource\Pages;
use App\Models\Member;
use App\Models\ProductAccount;
use Filament\Forms;
use Filament\Forms\Components\Grid;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;

class ProductAccountResource extends Resource
{
    protected static ?string $model = ProductAccount::class;

    protected static ?string $navigationIcon    = 'heroicon-o-banknotes';
    protected static ?string $navigationGroup   = 'Member Management';
    protected static ?string $navigationLabel   = 'Member Accounts';
    protected static ?string $pluralModelLabel  = 'Member Accounts';
    protected static ?string $modelLabel        = 'Member Account';
    protected static ?string $slug              = 'member-accounts';
    protected static ?string $recordTitleAttribute = 'product_name';
    protected static ?int    $navigationSort    = 2;

    // -------------------------------------------------------------------------
    // Form
    // -------------------------------------------------------------------------

    public static function form(Form $form): Form
    {
        return $form->schema([
            ...static::getAccountDetails(),
            ...static::getProductAccountDetails(),
        ]);
    }

    public static function getAccountDetails(): array
    {
        return [
            Forms\Components\Section::make('Member Information')
                ->description('Select the member and provide basic account details')
                ->schema([
                    Forms\Components\Select::make('member_id')
                        ->label('Member')
                        ->relationship(
                            name: 'member',
                            titleAttribute: 'last_name',
                            modifyQueryUsing: function ($query) {
                                $user = Auth::user();

                                $query->orderBy('last_name')->orderBy('first_name');

                                if ($user->hasRole('super_admin')) {
                                    return $query;
                                }

                                $branchNumber = $user->branch?->branch_number;

                                return $branchNumber
                                    ? $query->where('branch_number', $branchNumber)
                                    : $query->whereRaw('1 = 0');
                            }
                        )
                        ->getOptionLabelFromRecordUsing(
                            fn ($record) => "{$record->last_name}, {$record->first_name} {$record->middle} {$record->suffix}"
                        )
                        ->searchable()
                        ->preload()
                        ->required()
                        ->live()
                        ->placeholder('Select a member')
                        ->helperText('Choose the member this record belongs to')
                        ->createOptionForm([
                            ...MemberResource::getPersonalInformation(),
                            ...MemberResource::getContactInformation(),
                            ...MemberResource::getGovernmentIDs(),
                        ])
                        ->createOptionUsing(fn (array $data) => Member::create($data)),
                ]),
        ];
    }

    public static function getProductAccountDetails(): array
    {
        return [
            Forms\Components\Section::make('Product Information')
                ->description('Enter the basic details about the product or service')
                ->schema([
                    Grid::make(2)->schema([
                        Forms\Components\TextInput::make('product_name')
                            ->label('Product Name')
                            ->required()
                            ->maxLength(255)
                            ->placeholder('Enter product name')
                            ->helperText('The name of the product or service')
                            ->autocomplete('off')
                            ->live(onBlur: true)
                            ->afterStateUpdated(fn (string $state, callable $set) =>
                                $set('product_name', ucwords(strtolower($state)))
                            ),

                        Forms\Components\TextInput::make('account_number')
                            ->label('Account Number')
                            ->required()
                            ->maxLength(255)
                            ->placeholder('Enter account number')
                            ->helperText('Unique identifier for this account')
                            ->rule('alpha_num')
                            ->unique(ignoreRecord: true),
                    ]),
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
                    ->searchable(['first_name', 'last_name', 'middle_name']),

                Tables\Columns\TextColumn::make('product_name')
                    ->label('Product Name')
                    ->description(fn ($record) => ($record->account_number ?? 'Unknown Account Number'))
                    ->searchable(),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Created')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('updated_at')
                    ->label('Updated')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
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
            'index'  => Pages\ListProductAccounts::route('/'),
            'create' => Pages\CreateProductAccount::route('/create'),
            'edit'   => Pages\EditProductAccount::route('/{record}/edit'),
        ];
    }
}
