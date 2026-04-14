<?php

namespace App\Imports;

use App\Models\Member;
use App\Models\ProductAccount;
use App\Models\Subscription;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

class SubscriptionImport implements ToCollection, WithHeadingRow
{
    protected int   $insertedCount       = 0;
    protected float $totalInsertedAmount = 0;
    protected int   $duplicateCount      = 0;
    protected array $errorRows           = [];
    protected array $duplicateRows       = [];

    public function __construct(protected string $insuranceId) {}

    // -------------------------------------------------------------------------
    // Import
    // -------------------------------------------------------------------------

    public function collection(Collection $rows): void
    {
        DB::beginTransaction();

        try {
            foreach ($rows as $row) {
                $this->processRow($row);
            }

            DB::commit();

            Log::info('Subscription import complete', [
                'inserted'   => $this->insertedCount,
                'amount'     => $this->totalInsertedAmount,
                'duplicates' => $this->duplicateCount,
                'errors'     => count($this->errorRows),
            ]);
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('Subscription import failed', ['message' => $e->getMessage()]);
            throw $e;
        }
    }

    // -------------------------------------------------------------------------
    // Row Processing
    // -------------------------------------------------------------------------

    private function processRow(mixed $row): void
    {
        $fields = $this->extractFields($row);

        if (! $this->validate($row, $fields)) return;

        $member = Member::find($fields['member_id']);
        if (! $member) {
            $this->addError($row, 'Member not found.');
            return;
        }

        // Member must be active (is_active = true) and accepted (status = 'accepted')
        if (! $this->isMemberActive($member)) {
            $status = ucfirst($member->status ?? 'unknown');
            $active = $member->is_active ? 'active' : 'archived';
            $this->addError($row, "Member '{$member->full_name}' cannot be imported: is {$active} with status '{$status}'. Only active, accepted members are allowed.");
            return;
        }

        if ($this->hasActiveSubscription($member->id)) {
            $this->addDuplicate($row, $member->full_name, 'Active subscription exists for this member.');
            return;
        }

        $dates = $this->parseDates($row, $member, $fields);
        if (! $dates) return;

        ['paymentDate' => $paymentDate, 'subscriptionDate' => $subscriptionDate] = $dates;

        if ($this->isDuplicateSubscription($member->id, $paymentDate)) {
            $this->addDuplicate($row, $member->full_name, 'Duplicate subscription (same member/payment_date/insurance).');
            return;
        }

        $account = $this->resolveProductAccount($member, $fields);

        Subscription::create([
            'member_id'          => $member->id,
            'insurance_id'       => $this->insuranceId,
            'product_account_id' => $account?->id,
            'amount'             => (float) $fields['amount'],
            'payment_date'       => $paymentDate,
            'activated_at'       => $paymentDate,
            'expires_at'         => $subscriptionDate?->copy()->addYear(),
        ]);

        $this->insertedCount++;
        $this->totalInsertedAmount += (float) $fields['amount'];
    }

    // -------------------------------------------------------------------------
    // Field Extraction
    //
    // WithHeadingRow converts headers to snake_case keys automatically, e.g.:
    //   'Account Name'      → 'account_name'
    //   'Account Number'    → 'account_number'
    //   'Payment Date'      → 'payment_date'
    //   'Subscription Date' → 'subscription_date'
    // -------------------------------------------------------------------------

    private function extractFields(mixed $row): array
    {
        return [
            'member_id'             => trim($row['id'] ?? ''),
            'account_name'          => strip_tags(trim($row['account_name'] ?? '')),
            'account_number'        => preg_replace('/[^\w\d\-]/', '', trim($row['account_number'] ?? '')),
            'amount'                => $row['amount'] ?? null,
            'payment_date_raw'      => $this->normalizeDate($row['payment_date'] ?? null),
            'subscription_date_raw' => $this->normalizeDate($row['subscription_date'] ?? null),
        ];
    }

    // -------------------------------------------------------------------------
    // Date Normalization
    //
    // Handles three possible formats coming from Excel/CSV:
    //   1. Excel serial number (e.g. 45644)     → converted via PhpSpreadsheet
    //   2. Already m/d/Y string (e.g. 12/18/2025) → passed through as-is
    //   3. Other string formats (e.g. Y-m-d)    → parsed and reformatted by Carbon
    // -------------------------------------------------------------------------

    private function normalizeDate(mixed $value): ?string
    {
        if (empty($value)) return null;

        // Excel serial number (e.g. 45644)
        if (is_numeric($value) && $value > 1000) {
            try {
                return \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject((float) $value)
                    ->format('m/d/Y');
            } catch (\Throwable) {
                return null;
            }
        }

        $value = trim((string) $value);

        // Already m/d/Y → return as-is
        if (preg_match('/^\d{2}\/\d{2}\/\d{4}$/', $value)) {
            return $value;
        }

        // Fallback: try Carbon parsing for other formats (Y-m-d, d-m-Y, etc.)
        try {
            return Carbon::parse($value)->format('m/d/Y');
        } catch (\Throwable) {
            return null;
        }
    }

    // -------------------------------------------------------------------------
    // Validation
    // -------------------------------------------------------------------------

    private function validate(mixed $row, array $fields): bool
    {
        $validator = Validator::make([
            'member_id'         => $fields['member_id'],
            'account_number'    => $fields['account_number'],
            'amount'            => $fields['amount'],
            'payment_date'      => $fields['payment_date_raw'],
            'subscription_date' => $fields['subscription_date_raw'],
        ], [
            'member_id'         => 'required|uuid|exists:members,id',
            'account_number'    => 'nullable|string|max:50',
            'amount'            => 'required|numeric|gt:0',
            'payment_date'      => 'required|date_format:m/d/Y',
            'subscription_date' => 'required|date_format:m/d/Y',
        ], [
            'amount.gt'      => 'The amount must be greater than zero.',
            'amount.numeric' => 'The amount must be a valid number.',
        ]);

        if ($validator->fails()) {
            $this->errorRows[] = [
                'row'    => $row->toArray(),
                'reason' => 'Validation failed',
                'errors' => $validator->errors()->toArray(),
            ];
            return false;
        }

        return true;
    }

    // -------------------------------------------------------------------------
    // Date Parsing
    // -------------------------------------------------------------------------

    private function parseDates(mixed $row, Member $member, array $fields): ?array
    {
        try {
            $paymentDate      = $fields['payment_date_raw']
                ? Carbon::createFromFormat('m/d/Y', $fields['payment_date_raw'])
                : null;
            $subscriptionDate = $fields['subscription_date_raw']
                ? Carbon::createFromFormat('m/d/Y', $fields['subscription_date_raw'])
                : null;

            if ($paymentDate && $subscriptionDate) {
                $diffInMonths = abs($paymentDate->diffInMonths($subscriptionDate));

                if ($diffInMonths > 6) {
                    $this->errorRows[] = [
                        'row'         => $row->toArray(),
                        'member_name' => $member->full_name,
                        'reason'      => 'Payment date must not be more than 6 months apart from the subscription date.',
                    ];
                    return null;
                }
            }

            return [
                'paymentDate'      => $paymentDate,
                'subscriptionDate' => $subscriptionDate,
            ];
        } catch (\Exception $e) {
            $this->errorRows[] = [
                'row'         => $row->toArray(),
                'member_name' => $member->full_name,
                'reason'      => 'Date parsing failed',
                'error'       => $e->getMessage(),
            ];
            return null;
        }
    }

    // -------------------------------------------------------------------------
    // Model Resolution
    // -------------------------------------------------------------------------

    private function resolveProductAccount(Member $member, array $fields): ?ProductAccount
    {
        $accountNumber = $fields['account_number'];

        if (! $accountNumber) return null;

        return ProductAccount::firstOrCreate(
            ['member_id' => $member->id, 'account_number' => $accountNumber],
            ['product_name' => $fields['account_name'] ?: $member->full_name]
        );
    }

    // -------------------------------------------------------------------------
    // Member Status Check
    // -------------------------------------------------------------------------

    /**
     * Returns true if the member is eligible for subscription import.
     * - is_active must be true (not archived/deactivated)
     * - status must be 'accepted' (not 'pending' or 'declined')
     */
    private function isMemberActive(Member $member): bool
    {
        return $member->is_active === true
            && strtolower($member->status) === 'accepted';
    }

    // -------------------------------------------------------------------------
    // Duplicate Checks
    // -------------------------------------------------------------------------

    private function hasActiveSubscription(string $memberId): bool
    {
        return Subscription::where('member_id', $memberId)
            ->where('expires_at', '>', now()->addMonths(2))
            ->exists();
    }

    private function isDuplicateSubscription(string $memberId, ?Carbon $paymentDate): bool
    {
        return Subscription::where('member_id', $memberId)
            ->where('insurance_id', $this->insuranceId)
            ->whereDate('payment_date', $paymentDate)
            ->exists();
    }

    // -------------------------------------------------------------------------
    // Issue Tracking
    // -------------------------------------------------------------------------

    private function addError(mixed $row, string $reason, array $extras = []): void
    {
        $this->errorRows[] = array_merge(
            ['row' => $row->toArray(), 'reason' => $reason],
            $extras
        );
    }

    private function addDuplicate(mixed $row, string $memberName, string $reason): void
    {
        $this->duplicateCount++;
        $this->duplicateRows[] = [
            'row'         => $row->toArray(),
            'member_name' => $memberName,
            'reason'      => $reason,
        ];
    }

    // -------------------------------------------------------------------------
    // Accessors
    // -------------------------------------------------------------------------

    public function getInsertedCount(): int        { return $this->insertedCount; }
    public function getTotalInsertedAmount(): float { return $this->totalInsertedAmount; }
    public function getDuplicateCount(): int        { return $this->duplicateCount; }
    public function getErrorCount(): int            { return count($this->errorRows); }
    public function getErrorRows(): array           { return $this->errorRows; }
    public function getDuplicateRows(): array       { return $this->duplicateRows; }
}
