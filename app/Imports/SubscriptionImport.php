<?php

namespace App\Imports;

use App\Models\Subscription;
use App\Models\Member;
use App\Models\ProductAccount;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Concerns\ToCollection;
use Carbon\Carbon;

class SubscriptionImport implements ToCollection
{
    protected string $insuranceId;

    protected int $insertedCount = 0;
    protected float $totalInsertedAmount = 0;
    protected int $duplicateCount = 0;

    protected array $errorRows = [];
    protected array $duplicateRows = [];

    public function __construct(string $insuranceId)
    {
        $this->insuranceId = $insuranceId;
    }

    public function collection(Collection $rows)
    {
        $headerSkipped = false;

        DB::beginTransaction();

        try {
            foreach ($rows as $index => $row) {
                if (! $headerSkipped) {
                    $headerSkipped = true;
                    continue; // Skip header row
                }

                $memberId = trim($row[0]); // Column A
                $accountName = strip_tags(trim($row[14] ?? '')); // Column O
                $accountNumber = preg_replace('/[^\w\d\-]/', '', trim($row[15] ?? '')); // Column P
                $amount = $row[16] ?? null; // Column Q
                $paymentDateRaw = $row[17] ?? null; // Column R
                $subscriptionDateRaw = $row[18] ?? null; // Column S

                // Validate the basic fields
                $validator = Validator::make([
                    'member_id' => $memberId,
                    'account_number' => $accountNumber,
                    'amount' => $amount,
                    'payment_date' => $paymentDateRaw,
                    'subscription_date' => $subscriptionDateRaw,
                ], [
                    'member_id' => 'required|uuid|exists:members,id',
                    'account_number' => 'nullable|string|max:50',
                    'amount' => 'required',
                    'payment_date' => 'required|date_format:m/d/Y',
                    'subscription_date' => 'required|date_format:m/d/Y',
                ]);

                if ($validator->fails()) {
                    $this->errorRows[] = [
                        'row' => $row->toArray(),
                        'member_name' => $member ? $member->full_name : 'Unknown', // Add member's full_name
                        'reason' => 'Validation failed',
                        'errors' => $validator->errors()->toArray(),
                    ];
                    continue;
                }

                $member = Member::find($memberId);
                if (! $member) {
                    $this->errorRows[] = [
                        'row' => $row->toArray(),
                        'member_name' => 'Unknown', // Member not found, so use 'Unknown'
                        'reason' => "Member not found",
                    ];
                    continue;
                }

                $hasActiveSubscription = Subscription::where('member_id', $member->id)
                    ->where('expires_at', '>=', now()) // Only check if subscription is still valid
                    ->exists();

                if ($hasActiveSubscription) {
                    $this->duplicateCount++;
                    $this->duplicateRows[] = [
                        'row' => $row->toArray(),
                        'member_name' => $member->full_name, // Add full_name to duplicate rows
                        'reason' => 'Active subscription exists for this member.',
                    ];
                    continue;
                }

                // Get or create product account
                $productAccount = ProductAccount::where('member_id', $member->id)
                    ->where('account_number', $accountNumber)
                    ->first();

                if (! $productAccount && $accountNumber) {
                    $productAccount = ProductAccount::create([
                        'member_id' => $member->id,
                        'account_number' => $accountNumber,
                        'product_name' => $accountName ?: $member->full_name,
                    ]);
                }

                // Date conversion
                try {
                    $paymentDate = $paymentDateRaw ? Carbon::createFromFormat('m/d/Y', $paymentDateRaw) : null;
                    $subscriptionDate = $subscriptionDateRaw ? Carbon::createFromFormat('m/d/Y', $subscriptionDateRaw) : null;
                } catch (\Exception $e) {
                    $this->errorRows[] = [
                        'row' => $row->toArray(),
                        'member_name' => $member->full_name, // Add full_name to error rows
                        'reason' => 'Date parsing failed',
                        'error' => $e->getMessage(),
                    ];
                    continue;
                }

                // Duplicate check
                $exists = Subscription::where('member_id', $member->id)
                    ->where('insurance_id', $this->insuranceId)
                    ->whereDate('payment_date', $paymentDate)
                    ->exists();

                if ($exists) {
                    $this->duplicateCount++;
                    $this->duplicateRows[] = [
                        'row' => $row->toArray(),
                        'member_name' => $member->full_name, // Add full_name to duplicate rows
                        'reason' => 'Duplicate subscription (same member/payment_date/insurance)',
                    ];
                    continue;
                }

                // Create subscription
                Subscription::create([
                    'member_id' => $member->id,
                    'insurance_id' => $this->insuranceId,
                    'product_account_id' => $productAccount?->id,
                    'amount' => (float) $amount,
                    'payment_date' => $paymentDate,  // Store as Carbon object for date manipulation
                    'activated_at' => $paymentDate,  // Store the Carbon object
                    'expires_at' => $subscriptionDate ? $subscriptionDate->copy()->addYear() : null,  // Add a year to the subscription date
                ]);

                $this->insertedCount++;
                $this->totalInsertedAmount += (float) $amount;
            }

            DB::commit();

            Log::info("Subscription import complete", [
                'inserted_count' => $this->insertedCount,
                'total_amount' => $this->totalInsertedAmount,
                'duplicates_skipped' => $this->duplicateCount,
                'errors' => count($this->errorRows),
            ]);
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('Subscription import failed', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            throw $e;
        }
    }

    // Accessor methods
    public function getInsertedCount(): int
    {
        return $this->insertedCount;
    }

    public function getTotalInsertedAmount(): float
    {
        return $this->totalInsertedAmount;
    }

    public function getDuplicateCount(): int
    {
        return $this->duplicateCount;
    }

    public function getErrorRows(): array
    {
        return $this->errorRows;
    }

    public function getDuplicateRows(): array
    {
        return $this->duplicateRows;
    }

    public function getErrorCount(): int
    {
        return count($this->errorRows);
    }
}
