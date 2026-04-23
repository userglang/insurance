<?php

namespace App\Imports;

use App\Models\Member;
use App\Models\ProductAccount;
use App\Models\Subscription;
use Carbon\Carbon;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithChunkReading;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Events\AfterImport;
use Maatwebsite\Excel\Events\BeforeImport;

class MemberUpload implements ToModel, WithHeadingRow, WithChunkReading, ShouldQueue, WithEvents
{
    protected static int   $totalRows       = 0;
    protected static int   $insertedMembers = 0;
    protected static int   $updatedMembers  = 0;
    protected static int   $skippedMembers  = 0;
    protected static int   $errors          = 0;
    protected static array $errorDetails    = [];

    private const DEFAULT_INSURANCE_ID = '117cf002-ee7d-4284-a1d9-19d052fb237e';
    private const FALLBACK_BIRTH_DATE  = '1900-01-01';
    private const FALLBACK_CREATED_AT  = '2000-01-01 00:00:00';

    /**
     * Formats tried in order for applicationdate (parseCreatedAt).
     * Handles: "10/02/2023 10:39", "10/02/2023 10:39:24",
     *          "2023-02-10 10:39:24", "2023-02-10 10:39"
     */
    private const DATETIME_FORMATS = [
        'd/m/Y H:i',
        'd/m/Y H:i:s',
        'Y-m-d H:i:s',
        'Y-m-d H:i',
    ];

    /**
     * Formats tried in order for generic date columns (parseDateColumn).
     * Handles: "21/02/2024", "2024-02-21", "2024-02-21 10:39:24", "2024-02-21 10:39"
     */
    private const DATE_FORMATS = [
        'd/m/Y',
        'Y-m-d',
        'Y-m-d H:i:s',
        'Y-m-d H:i',
    ];

    // -------------------------------------------------------------------------
    // Constructor
    // -------------------------------------------------------------------------

    /**
     * @param bool $updateExisting When true, existing member info and
     *                             subscription details will be overwritten
     *                             on duplicate rows instead of being skipped.
     */
    public function __construct(private bool $updateExisting = false) {}

    // -------------------------------------------------------------------------
    // Import
    // -------------------------------------------------------------------------

    public function model(array $row): ?Member
    {
        self::$totalRows++;

        $row = array_change_key_case($row, CASE_LOWER);

        $birthDate = $this->parseBirthDate($row);
        $createdAt = $this->parseCreatedAt($row);
        $email     = $this->parseEmail($row);

        $member  = $this->firstOrCreateMember($row, $birthDate, $createdAt, $email);
        $account = $this->firstOrCreateProductAccount($member, $row);

        $subscription = $this->createOrUpdateSubscription($member, $account, $row);

        return $subscription ? $member : null;
    }

    // -------------------------------------------------------------------------
    // Parsing
    // -------------------------------------------------------------------------

    private function parseBirthDate(array $row): string
    {
        $raw = trim($row['dateofbirth'] ?? '');

        if (empty($raw)) {
            return self::FALLBACK_BIRTH_DATE;
        }

        foreach (self::DATE_FORMATS as $format) {
            try {
                return Carbon::createFromFormat($format, $raw)->format('Y-m-d');
            } catch (\Exception) {
                continue;
            }
        }

        $this->logError("Invalid dateofbirth format: {$raw}");
        return self::FALLBACK_BIRTH_DATE;
    }

    private function parseCreatedAt(array $row): Carbon
    {
        $raw = trim($row['applicationdate'] ?? '');

        if (! empty($raw)) {
            foreach (self::DATETIME_FORMATS as $format) {
                try {
                    $date = Carbon::createFromFormat($format, $raw);

                    if ($date->year > 2038) {
                        $this->logError("applicationdate exceeds 2038: {$date->toDateTimeString()} | Raw: {$raw}");
                        break;
                    }

                    return $date;
                } catch (\Exception) {
                    continue;
                }
            }
        }

        $this->logError("Using fallback applicationDate — no matching format | Raw: {$raw}");
        return Carbon::parse(self::FALLBACK_CREATED_AT);
    }

    private function parseEmail(array $row): ?string
    {
        $email = strtolower(trim($row['email'] ?? ''));

        if (in_array($email, ['none', 'n/a', 'na', 'null', ''], true)) {
            return null;
        }

        return Member::where('email', $email)->exists() ? null : $email;
    }

    /**
     * Parse a date/datetime column using multiple format fallbacks.
     *
     * @param  array  $row
     * @param  string $column   Row key to read
     * @param  array  $formats  Ordered list of Carbon format strings to try.
     *                          Defaults to DATE_FORMATS (handles d/m/Y and Y-m-d variants).
     */
    private function parseDateColumn(array $row, string $column, array $formats = self::DATE_FORMATS): ?Carbon
    {
        $raw = trim($row[$column] ?? '');

        if (empty($raw)) {
            $this->logError("Missing {$column} in row: " . json_encode($row));
            return null;
        }

        foreach ($formats as $format) {
            try {
                return Carbon::createFromFormat($format, $raw);
            } catch (\Exception) {
                continue;
            }
        }

        $this->logError("Invalid {$column} format: {$raw}");
        return null;
    }

    // -------------------------------------------------------------------------
    // Model Resolution
    // -------------------------------------------------------------------------

    private function firstOrCreateMember(array $row, string $birthDate, Carbon $createdAt, ?string $email): Member
    {
        $existing = Member::where('first_name', $row['firstname'] ?? null)
            ->where('last_name', $row['surname'] ?? null)
            ->where('middle_name', $row['middlename'] ?? null)
            ->whereDate('birth_date', $birthDate)
            ->first();

        if ($existing) {
            if ($this->updateExisting) {
                $existing->update([
                    'cid'                   => $row['cid'] ?? $existing->cid,
                    'branch_number'         => $row['branch_id'] ?? $existing->branch_number,
                    'email'                 => $email ?? $existing->email,
                    'gender'                => $this->parseGender($row),
                    'sss_gsis'              => $row['sss/gsis'] ?? $existing->sss_gsis,
                    'tin'                   => $row['tin'] ?? $existing->tin,
                    'occupation'            => $row['occupation'] ?? $existing->occupation,
                    'office_contact_number' => $row['office_number'] ?? $existing->office_contact_number,
                    'name_of_employer'      => $row['nameofemployer'] ?? $existing->name_of_employer,
                    'employment_status'     => $row['employment_status'] ?? $existing->employment_status,
                    'office_address'        => $row['office_address'] ?? $existing->office_address,
                    'house_number'          => $row['house_number'] ?? $existing->house_number,
                    'street'                => $row['street'] ?? $existing->street,
                    'barangay'              => $row['barangay'] ?? $existing->barangay,
                    'city'                  => $row['city'] ?? $existing->city,
                    'province'              => $row['province'] ?? $existing->province,
                    'zipcode'               => $row['zipcode'] ?? $existing->zipcode,
                    'contact_number'        => $row['contact_number'] ?? $existing->contact_number,
                    'remark'                => $row['remark'] ?? $existing->remark,
                    'is_active'             => ($row['active'] ?? '') === 'Active',
                ]);

                self::$updatedMembers++;
            } else {
                self::$skippedMembers++;
            }

            return $existing;
        }

        self::$insertedMembers++;

        return Member::create([
            'cid'                   => $row['cid'] ?? null,
            'branch_number'         => $row['branch_id'] ?? null,
            'first_name'            => $row['firstname'] ?? null,
            'last_name'             => $row['surname'] ?? null,
            'middle_name'           => $row['middlename'] ?? null,
            'birth_date'            => $birthDate,
            'birth_place'           => $row['placeofbirth'] ?? null,
            'email'                 => $email,
            'gender'                => $this->parseGender($row),
            'sss_gsis'              => $row['sss/gsis'] ?? null,
            'tin'                   => $row['tin'] ?? null,
            'occupation'            => $row['occupation'] ?? null,
            'office_contact_number' => $row['office_number'] ?? null,
            'name_of_employer'      => $row['nameofemployer'] ?? null,
            'employment_status'     => $row['employment_status'] ?? null,
            'office_address'        => $row['office_address'] ?? null,
            'house_number'          => $row['house_number'] ?? null,
            'street'                => $row['street'] ?? null,
            'barangay'              => $row['barangay'] ?? null,
            'city'                  => $row['city'] ?? null,
            'province'              => $row['province'] ?? null,
            'zipcode'               => $row['zipcode'] ?? null,
            'remark'                => $row['remark'] ?? null,
            'contact_number'        => $row['contact_number'] ?? null,
            'is_active'             => ($row['active'] ?? '') === 'Active',
            'status'                => 'accepted',
            'created_at'            => $createdAt,
        ]);
    }

    private function firstOrCreateProductAccount(Member $member, array $row): ProductAccount
    {
        return ProductAccount::firstOrCreate(
            [
                'member_id'      => $member->id,
                'product_name'   => $row['account_name'] ?? null,
                'account_number' => $row['account_number'] ?? null,
            ]
        );
    }

    private function createOrUpdateSubscription(Member $member, ProductAccount $account, array $row): ?bool
    {
        // subscription_date: handles "21/02/2024" and "2024-02-21"
        $subscriptionDate = $this->parseDateColumn($row, 'subscription_date');
        if (! $subscriptionDate) return null;

        // date_time: handles datetime ("2023-02-10 10:39:24") and date-only formats
        $paymentDate = $this->parseDateColumn($row, 'date_time', self::DATETIME_FORMATS);
        if (! $paymentDate) return null;

        try {
            $existing = Subscription::where('member_id', $member->id)
                ->where('product_account_id', $account->id)
                ->whereDate('activated_at', $subscriptionDate->toDateString())
                ->first();

            if ($existing) {
                if ($this->updateExisting) {
                    $existing->update([
                        'amount'       => $row['amount'] ?? $existing->amount,
                        'payment_date' => $paymentDate,
                        'expires_at'   => $subscriptionDate->copy()->addYear(),
                        'remark'       => $row['remarks'] ?? $existing->remark,
                    ]);
                }
                // Whether updating or not, skip creating a new duplicate
                return true;
            }

            Subscription::create([
                'member_id'          => $member->id,
                'product_account_id' => $account->id,
                'insurance_id'       => self::DEFAULT_INSURANCE_ID,
                'amount'             => $row['amount'] ?? 0,
                'payment_date'       => $paymentDate,
                'activated_at'       => $subscriptionDate,
                'expires_at'         => $subscriptionDate->copy()->addYear(),
                'remark'             => $row['remarks'] ?? null,
            ]);

            return true;
        } catch (\Exception $e) {
            $this->logError("Subscription creation failed: {$e->getMessage()} | Row: " . json_encode($row));
            return null;
        }
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function parseGender(array $row): string
    {
        $gender = strtolower(trim($row['sex'] ?? ''));
        return in_array($gender, ['male', 'female']) ? ucfirst($gender) : 'Other';
    }

    private function logError(string $message): void
    {
        self::$errors++;
        self::$errorDetails[] = $message;
        Log::warning($message);
    }

    // -------------------------------------------------------------------------
    // Chunk / Batch
    // -------------------------------------------------------------------------

    public function chunkSize(): int
    {
        return 500;
    }

    public function batchSize(): int
    {
        return 500;
    }

    // -------------------------------------------------------------------------
    // Events
    // -------------------------------------------------------------------------

    public function registerEvents(): array
    {
        return [
            BeforeImport::class => function (): void {
                self::$totalRows       = 0;
                self::$insertedMembers = 0;
                self::$updatedMembers  = 0;
                self::$skippedMembers  = 0;
                self::$errors          = 0;
                self::$errorDetails    = [];
            },

            AfterImport::class => function (): void {
                Log::info('Member Upload Import Summary', [
                    'total_rows' => self::$totalRows,
                    'inserted'   => self::$insertedMembers,
                    'updated'    => self::$updatedMembers,
                    'skipped'    => self::$skippedMembers,
                    'errors'     => self::$errors,
                ]);

                if (! empty(self::$errorDetails)) {
                    Log::error('Member Upload Import Errors', self::$errorDetails);
                }
            },
        ];
    }
}
