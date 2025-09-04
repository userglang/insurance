<?php

namespace App\Imports;

use App\Models\Member;
use App\Models\ProductAccount;
use App\Models\Subscription;
use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithChunkReading;
use Maatwebsite\Excel\Concerns\WithEvents;
use Illuminate\Contracts\Queue\ShouldQueue;
use Maatwebsite\Excel\Events\AfterImport;
use Maatwebsite\Excel\Events\BeforeImport;
use Carbon\Carbon;

class MemberUpload implements ToModel, WithHeadingRow, WithChunkReading, ShouldQueue, WithEvents
{
    protected static int $totalRows = 0;
    protected static int $insertedMembers = 0;
    protected static int $skippedMembers = 0;
    protected static int $errors = 0;
    protected static array $errorDetails = [];

    public function model(array $row)
    {
        self::$totalRows++;

        $row = array_change_key_case($row, CASE_LOWER);

        // Normalize and validate gender
        $gender = strtolower(trim($row['sex'] ?? ''));
        if (!in_array($gender, ['male', 'female'])) {
            $gender = 'Other';
        } else {
            $gender = ucfirst($gender);
        }

        // Normalize and validate dateofbirth
        if (empty($row['dateofbirth'])) {
            $birthDate = '1900-01-01';
        } else {
            try {
                $birthDate = Carbon::createFromFormat('d/m/Y', $row['dateofbirth'])->format('Y-m-d');
            } catch (\Exception $e) {
                $birthDate = '1900-01-01';
            }
        }

        // Parse applicationDate (created_at)
        try {
            if (empty($row['applicationdate'])) {
                throw new \Exception('Empty applicationdate');
            }

            $createdAt = Carbon::createFromFormat('d/m/Y H:i', $row['applicationdate']);

            // Check for year beyond 2038
            if ($createdAt->year > 2038) {
                throw new \Exception('applicationdate exceeds 2038 limit: ' . $createdAt->toDateTimeString());
            }

        } catch (\Exception $e) {
            $createdAt = Carbon::parse('2000-01-01 00:00:00');

            $msg = 'Using default applicationDate (2000-01-01 00:00:00) due to error: ' . $e->getMessage() . ' | Raw: ' . ($row['applicationdate'] ?? 'N/A');
            Log::warning($msg);
            self::$errorDetails[] = $msg;
            self::$errors++;
        }

        // Check for invalid or duplicate email
        $email = strtolower(trim($row['email'] ?? ''));

        // Treat "none", "n/a", and empty strings as null
        if (in_array($email, ['none', 'n/a', 'na', 'null', ''], true)) {
            $email = null;
        }

        // Check for duplicates if email is valid
        if ($email && Member::where('email', $email)->exists()) {
            $email = null;
        }

        $existingMember = Member::where('first_name', $row['firstname'] ?? null)
            ->where('last_name', $row['surname'] ?? null)
            ->where('middle_name', $row['middlename'] ?? null)
            ->whereDate('birth_date', $birthDate)
            ->first();

        if (!$existingMember) {
            self::$insertedMembers++;
            $member = Member::create([
                'cid' => $row['cid'] ?? null,
                'branch_number' => $row['branch_id'] ?? null,
                'first_name' => $row['firstname'] ?? null,
                'last_name' => $row['surname'] ?? null,
                'middle_name' => $row['middlename'] ?? null,
                'birth_date' => $birthDate,
                'birth_place' => $row['placeofbirth'] ?? null,
                'email' => $email,
                'gender' => $gender,
                'sss_gsis' => $row['sss/gsis'] ?? null,
                'tin' => $row['tin'] ?? null,
                'occupation' => $row['occupation'] ?? null,
                'office_contact_number' => $row['office_number'] ?? null,
                'name_of_employer' => $row['nameofemployer'] ?? null,
                'employment_status' => $row['employment_status'] ?? null,
                'office_address' => $row['office_address'] ?? null,
                'house_number' => $row['house_number'] ?? null,
                'street' => $row['street'] ?? null,
                'barangay' => $row['barangay'] ?? null,
                'city' => $row['city'] ?? null,
                'province' => $row['province'] ?? null,
                'zipcode' => $row['zipcode'] ?? null,
                'remark' => $row['remark'] ?? null,
                'contact_number' => $row['contact_number'] ?? null,
                'is_active' => ($row['active'] === 'Active') ? true : false,
                'status' => 'accepted',
                'created_at' => $createdAt,
            ]);
        } else {
            self::$skippedMembers++;

            $member = $existingMember;
        }

        $existingProductAccount = ProductAccount::where('member_id', $member->id)
            ->where('product_name', $row['account_name'] ?? null)
            ->where('account_number', $row['account_number'] ?? null)
            ->first();

        if (!$existingProductAccount) {
            $productAccount = ProductAccount::create([
                'member_id' => $member->id,
                'account_number' => $row['account_number'] ?? null,
                'product_name' => $row['account_name'] ?? null,
            ]);
        } else {

            $productAccount = $existingProductAccount;
        }

        if (empty($row['subscription_date'])) {
            self::$errors++;
            $msg = 'Missing subscription_date in the row: ' . json_encode($row);
            self::$errorDetails[] = $msg;
            Log::error($msg);
            return null;
        }

        try {
            $subscriptionDate = Carbon::createFromFormat('d/m/Y', $row['subscription_date']);
        } catch (\Exception $e) {
            self::$errors++;
            $msg = 'Invalid subscription_date format: ' . $row['subscription_date'];
            self::$errorDetails[] = $msg;
            Log::error($msg);
            return null;
        }

        try {
            $dateOnly = explode(' ', $row['date_time'])[0]; // "31/05/2023"
            $payment_date = Carbon::createFromFormat('d/m/Y', $dateOnly);
        } catch (\Exception $e) {
            self::$errors++;
            $msg = 'Invalid date_time format: ' . $row['date_time'];
            self::$errorDetails[] = $msg;
            Log::error($msg);
            return null;
        }

        try {
            Subscription::create([
                'member_id' => $member->id,
                'product_account_id' => $productAccount->id,
                'insurance_id' => '117cf002-ee7d-4284-a1d9-19d052fb237e', // Adjust as needed
                'amount' => $row['amount'] ?? 0,
                'payment_date' => $payment_date,
                'activated_at' => $subscriptionDate,
                'expires_at' => $subscriptionDate->copy()->addYear(),
                'remark' => $row['remarks'] ?? null,
            ]);
        } catch (\Exception $e) {
            self::$errors++;
            $msg = 'Subscription creation failed: ' . $e->getMessage() . ' | Row: ' . json_encode($row);
            self::$errorDetails[] = $msg;
            Log::error($msg);
            return null;
        }

        return $member;
    }

    public function chunkSize(): int
    {
        return 500;
    }

    public function batchSize(): int
    {
        return 500;
    }

    public function registerEvents(): array
    {
        return [
            BeforeImport::class => function () {
                self::$totalRows = 0;
                self::$insertedMembers = 0;
                self::$skippedMembers = 0;
                self::$errors = 0;
                self::$errorDetails = [];
            },

            AfterImport::class => function () {
                Log::info('Member Upload Import Summary:', [
                    'Total rows processed' => self::$totalRows,
                    'Members inserted' => self::$insertedMembers,
                    'Members skipped (existing)' => self::$skippedMembers,
                    'Errors count' => self::$errors,
                ]);

                if (!empty(self::$errorDetails)) {
                    Log::error('Detailed errors during import:', self::$errorDetails);
                }
            },
        ];
    }
}
