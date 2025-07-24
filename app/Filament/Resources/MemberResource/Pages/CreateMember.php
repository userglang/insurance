<?php

namespace App\Filament\Resources\MemberResource\Pages;

use App\Filament\Resources\MemberResource;
use App\Models\Insurance;
use App\Models\Member;
use App\Models\Subscription;
use App\Exceptions\MemberCreationException;
use Filament\Resources\Pages\CreateRecord;
use Filament\Forms\Components\Wizard\Step;
use Filament\Resources\Pages\CreateRecord\Concerns\HasWizard;
use Filament\Forms\Components\Fieldset;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class CreateMember extends CreateRecord
{
    use HasWizard;

    protected static string $resource = MemberResource::class;

    public static function getSteps(): array
    {
        return [
            Step::make('Member Information')
                ->schema(MemberResource::getPersonalInformation()),

            Step::make('Contact Information')
                ->schema(MemberResource::getContactInformation()),

            Step::make('Employment Information')
                ->schema(MemberResource::getEmploymentInformation()),

            Step::make('Other Information')
                ->schema([
                    ...MemberResource::getGovernmentIDs(),

                    Fieldset::make('Payment Information')
                        ->schema([
                            Grid::make(3)
                                ->schema([
                                    Select::make('insurance_id')
                                        ->label('Insurance Plan')
                                        ->options(
                                            Insurance::where('is_active', true)
                                                ->pluck('insurance_name', 'id')
                                                ->toArray()
                                        )
                                        ->preload()
                                        ->required()
                                        ->placeholder('Select insurance plan...')
                                        ->default(fn () => Insurance::where('is_active', true)->first()?->id)
                                        ->columnSpan(1)
                                        ->reactive()
                                        ->afterStateUpdated(function ($state, callable $set) {
                                            if ($state) {
                                                $insurance = Insurance::find($state);
                                                if ($insurance) {
                                                    $set('amount', $insurance->default_amount ?? 160.00);
                                                }
                                            }
                                        }),

                                    Select::make('account')
                                        ->label('Account Type')
                                        ->required()
                                        ->options([
                                            'Regular Savings' => 'Regular Savings',
                                            'Compulsory Savings Deposit' => 'Compulsory Savings Deposit',
                                            'ATM' => 'ATM',
                                            'Loan' => 'Loan',
                                            'Cash' => 'Cash',
                                        ])
                                        ->placeholder('Select account type...')
                                        ->columnSpan(1),

                                    TextInput::make('account_number')
                                        ->label('Account Number')
                                        ->required()
                                        ->default('0')
                                        ->autocomplete('off')
                                        ->maxLength(50)
                                        ->columnSpan(1),

                                    TextInput::make('amount')
                                        ->label('Subscription Amount')
                                        ->required()
                                        ->numeric()
                                        ->prefix('₱')
                                        ->minValue(1)
                                        ->maxValue(999999.99)
                                        ->step(0.01)
                                        ->default(160.00)
                                        ->columnSpan(1),

                                    DatePicker::make('payment_date')
                                        ->label('Payment Date')
                                        ->required()
                                        ->default(now())
                                        ->maxDate(now())
                                        ->displayFormat('M j, Y')
                                        ->columnSpan(1),

                                    DatePicker::make('activated_at')
                                        ->label('Activation Date')
                                        ->required()
                                        ->default(now())
                                        ->maxDate(now()->addDays(30)) // Allow future activation up to 30 days
                                        ->displayFormat('M j, Y')
                                        ->columnSpan(1),
                                ]),
                        ]),

                    ...MemberResource::getAdditionalInformation(),
                ]),
        ];
    }

    protected function handleRecordCreation(array $data): Member
    {
        try {
            return DB::transaction(function () use ($data) {
                // Clean input data
                $cleanData = $this->cleanData($data);

                // Create member record
                $member = Member::create($cleanData);

                // Create subscription if insurance is selected
                if (!empty($data['insurance_id'])) {
                    $this->createSubscription($member, $data);
                }

                // Log the creation
                Log::info('Member created', [
                    'member_id' => $member->id,
                    'created_by' => auth()->id()
                ]);

                // Show success message
                Notification::make()
                    ->title('Member Created Successfully')
                    ->success()
                    ->send();

                return $member;
            });
        } catch (\Exception $e) {
            Log::error('Member creation failed: ' . $e->getMessage());

            Notification::make()
                ->title('Creation Failed')
                ->body('Please try again.')
                ->danger()
                ->send();

            throw $e;
        }
    }

    private function cleanData(array $data): array
    {
        // Remove subscription fields from member data
        unset($data['insurance_id'], $data['account'], $data['account_number'], $data['amount']);

        // Clean text fields
        foreach ($data as $key => $value) {
            if (is_string($value)) {
                $data[$key] = trim(strip_tags($value));
            }
        }

        // Add creator info
        $data['created_by'] = auth()->id();

        return $data;
    }

    private function createSubscription(Member $member, array $data): void
    {
        // Check if this is a renewal
        $existingCount = Subscription::where('member_id', $member->id)
            ->where('insurance_id', $data['insurance_id'])
            ->count();

        $remark = $existingCount > 0 ? 'Renewal' : 'First Payment';

        Subscription::create([
            'member_id' => $member->id,
            'insurance_id' => $data['insurance_id'],
            'account_type' => $data['account'],
            'account_number' => trim(strip_tags($data['account_number'])),
            'amount' => $data['amount'],
            'payment_date' => $data['payment_date'],
            'activated_at' => $data['activated_at'],
            'remark' => $remark,
        ]);
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
