<?php

namespace App\Filament\Resources\MemberResource\Pages;

use App\Filament\Resources\MemberResource;
use App\Models\Insurance;
use App\Models\Member;
use App\Models\Subscription;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Fieldset;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Wizard\Step;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Filament\Resources\Pages\CreateRecord\Concerns\HasWizard;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class CreateMember extends CreateRecord
{
    use HasWizard;

    protected static string $resource = MemberResource::class;

    // -------------------------------------------------------------------------
    // Wizard Steps
    // -------------------------------------------------------------------------

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
                    static::getPaymentInformationFieldset(),
                    ...MemberResource::getAdditionalInformation(),
                ]),
        ];
    }

    private static function getPaymentInformationFieldset(): Fieldset
    {
        // Cache active insurances for the duration of the request
        $insurances = Insurance::where('is_active', true)->get(['id', 'insurance_name', 'amount']);
        $firstId    = $insurances->first()?->id;

        return Fieldset::make('Payment Information')
            ->schema([
                Grid::make(3)->schema([
                    Select::make('insurance_id')
                        ->label('Insurance Plan')
                        ->options($insurances->pluck('insurance_name', 'id'))
                        ->preload()
                        ->required()
                        ->placeholder('Select insurance plan...')
                        ->default($firstId)
                        ->reactive()
                        ->afterStateUpdated(function ($state, callable $set) use ($insurances): void {
                            if ($state) {
                                // Resolve from already-loaded collection — no extra query
                                $amount = $insurances->firstWhere('id', $state)?->amount ?? 160.00;
                                $set('amount', $amount);
                            }
                        })
                        ->columnSpan(1),

                    Select::make('account')
                        ->label('Account Type')
                        ->required()
                        ->options([
                            'Regular Savings'            => 'Regular Savings',
                            'Compulsory Savings Deposit' => 'Compulsory Savings Deposit',
                            'ATM'                        => 'ATM',
                            'Loan'                       => 'Loan',
                            'Cash'                       => 'Cash',
                            'Others'                       => 'Others',
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
                        ->default(180.00)
                        ->columnSpan(1),

                    DatePicker::make('payment_date')
                        ->label('Payment Date')
                        ->native(true)
                        ->required()
                        ->default(now())
                        ->minDate(now()->subDays(120))
                        ->maxDate(now()->addDays(120))
                        ->displayFormat('M j, Y')
                        ->columnSpan(1),

                    DatePicker::make('activated_at')
                        ->label('Activation Date')
                        ->native(true)
                        ->required()
                        ->default(now())
                        ->minDate(now()->subDays(120))
                        ->maxDate(now()->addDays(120))
                        ->displayFormat('M j, Y')
                        ->columnSpan(1),
                ]),
            ]);
    }

    // -------------------------------------------------------------------------
    // Record Creation
    // -------------------------------------------------------------------------

    protected function handleRecordCreation(array $data): Member
    {
        try {
            return DB::transaction(function () use ($data): Member {

                Member::findSimilarMembers(
                    firstName: $data['first_name'],
                    lastName: $data['last_name'],
                    middleName: $data['middle_name'] ?? null,
                    birthDate: $data['birth_date'] ?? null,
                );

                $member = Member::create($this->cleanMemberData($data));

                if (! empty($data['insurance_id'])) {
                    $this->createSubscription($member, $data);
                }

                Log::info('Member created', [
                    'member_id'  => $member->id,
                    'created_by' => auth()->id(),
                ]);

                Notification::make()
                    ->title('Member Created Successfully')
                    ->success()
                    ->send();

                return $member;
            });

        } catch (\Illuminate\Database\UniqueConstraintViolationException $e) {
            // Handles MySQL SQLSTATE[23000] duplicate entry errors
            Notification::make()
                ->title('Duplicate Member Detected')
                ->body('A member with the same name and birth date already exists. Please contact system administrator')
                ->danger()
                ->send();

            throw ValidationException::withMessages([
                'first_name' => 'A member with the same name and birth date already exists.',
            ]);

        } catch (\Exception $e) {
            Log::error('Member creation failed', [
                'error' => $e->getMessage()
            ]);

            Notification::make()
                ->title('Creation Failed')
                ->body($e->getMessage())
                ->danger()
                ->send();

            throw ValidationException::withMessages([
                'first_name' => $e->getMessage(),
            ]);
        }
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function cleanMemberData(array $data): array
    {
        unset($data['insurance_id'], $data['account'], $data['account_number'], $data['amount'], $data['payment_date'], $data['activated_at']);

        return array_map(
            fn ($value) => is_string($value) ? trim(strip_tags($value)) : $value,
            $data
        ) + ['created_by' => auth()->id()];
    }

    private function createSubscription(Member $member, array $data): void
    {
        // Check if this member already has any subscription for this insurance
        $isRenewal = Subscription::where('member_id', $member->id)
            ->where('insurance_id', $data['insurance_id'])
            ->exists();

        Subscription::create([
            'member_id'      => $member->id,
            'insurance_id'   => $data['insurance_id'],
            'account_type'   => $data['account'],
            'account_number' => trim(strip_tags($data['account_number'])),
            'amount'         => $data['amount'],
            'payment_date'   => $data['payment_date'],
            'activated_at'   => $data['activated_at'],
            'remark'         => $isRenewal ? 'Renewal' : 'First Payment',
        ]);
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
