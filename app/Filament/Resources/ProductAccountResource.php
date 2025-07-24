<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ProductAccountResource\Pages;
use App\Filament\Resources\ProductAccountResource\RelationManagers;
use App\Models\Member;
use App\Models\ProductAccount;
use Filament\Forms;
use Filament\Forms\Components\Grid;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Str;

class ProductAccountResource extends Resource
{
    protected static ?string $model = ProductAccount::class;

    protected static ?string $navigationIcon = 'heroicon-o-banknotes';

    protected static ?string $navigationGroup = 'Member Management';
    protected static ?string $navigationLabel = 'Member Accounts';
    protected static ?string $pluralModelLabel = 'Member Accounts';
    protected static ?string $modelLabel = 'Member Account';
    protected static ?string $slug = 'member-accounts';

    protected static ?int $navigationSort = 2;

    protected static ?string $recordTitleAttribute = 'product_name';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                ...self::getAccountDetails(),
                ...self::getProductAccountDetails(),
            ]);
    }

    public static function getAccountDetails(): array
    {
        return
        [
            Forms\Components\Section::make('Member Information')
                ->description('Select the member and provide basic account details')
                ->schema([
                    Forms\Components\Select::make('member_id')
                        ->label('Member')
                        ->relationship('member', 'first_name')
                        ->getOptionLabelFromRecordUsing(fn ($record) => $record->first_name . ', ' . $record->middle_name. ' ' . $record->last_name. ' ' . $record->suffix)
                        ->searchable()
                        ->preload()
                        ->required()
                        ->placeholder('Select a member')
                        ->helperText('Choose the member this record belongs to')
                        ->createOptionForm([
                            ...MemberResource::getPersonalInformation(),
                            ...MemberResource::getContactInformation(),
                            ...MemberResource::getGovernmentIDs(),
                        ])
                        ->createOptionUsing(function (array $data) {
                            return Member::create($data);
                        }),


                ])
        ];
    }
    public static function getProductAccountDetails(): array
    {
        return
        [
            Grid::make(2)
                ->schema([
                    Forms\Components\TextInput::make('product_name')
                        ->label('Product Name')
                        ->required()
                        ->maxLength(255)
                        ->placeholder('Enter product name')
                        ->helperText('The name of the product or service')
                        ->autocomplete('off')
                        ->live(onBlur: true)
                        ->afterStateUpdated(function (string $state, callable $set) {
                            // Auto-capitalize first letter of each word
                            $set('product_name', ucwords(strtolower($state)));
                        }),

                    Forms\Components\TextInput::make('account_number')
                        ->label('Account Number')
                        ->required()
                        ->maxLength(255)
                        ->placeholder('Enter account number')
                        ->helperText('Unique identifier for this account')
                        // ->mask('9999-9999-9999') // Adjust mask pattern as needed
                        ->rule('alpha_num')
                        ->unique(ignoreRecord: true),
                ])
        ];
    }


    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('member.full_name')
                    ->searchable(),
                Tables\Columns\TextColumn::make('product_name')
                    ->searchable(),
                Tables\Columns\TextColumn::make('account_number')
                    ->searchable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
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

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListProductAccounts::route('/'),
            'create' => Pages\CreateProductAccount::route('/create'),
            'edit' => Pages\EditProductAccount::route('/{record}/edit'),
        ];
    }
}
