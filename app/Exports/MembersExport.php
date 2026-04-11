<?php

namespace App\Exports;

use App\Models\Member;
use Illuminate\Database\Eloquent\Builder;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Alignment;

class MembersExport implements
    FromQuery,
    WithHeadings,
    WithMapping,
    WithStyles,
    WithColumnWidths,
    WithTitle,
    ShouldAutoSize
{
    public function __construct(protected array $filters = []) {}

    # ---------------------------------------------------------
    #  MAIN QUERY
    # ---------------------------------------------------------
    public function query(): Builder
    {
        return Member::query()
            ->select([
                // Primary key & identifier
                'members.id',
                'members.cid',

                // Required by getFullNameAttribute()
                'members.first_name',
                'members.last_name',
                'members.middle_name',
                'members.suffix',

                // Required by branch() → belongsTo(Branch, 'branch_number', 'branch_number')
                'members.branch_number',

                // Required by getFullAddressAttribute()
                'members.house_number',
                'members.street',
                'members.barangay',
                'members.city',
                'members.province',
                'members.zipcode',

                // Required by getAgeAttribute() — age is computed from birth_date, not a column
                'members.birth_date',

                // Direct columns used in map()
                'members.email',
                'members.contact_number',
                'members.gender',
                'members.marital_status',
                'members.occupation',
                'members.employment_status',
                'members.is_active',
                'members.status',
                'members.created_at',
            ])
            ->with([
                // branch() uses branch_number as FK — select only what map() needs
                'branch:id,branch_number,branch_name',

                // Load only the subscription columns used in map().
                // Must prefix with table name — latestOfMany() uses an inner join
                // which makes bare column names like 'member_id' ambiguous.
                'latestSubscription' => fn ($q) => $q->select([
                    'subscriptions.id',
                    'subscriptions.member_id',
                    'subscriptions.product_account_id',
                    'subscriptions.amount',
                    'subscriptions.expires_at',
                ]),

                // Load only the product account columns used in map()
                'latestSubscription.productAccount:id,product_name,account_number',
            ])
            ->orderBy('last_name')
            ->tap(fn ($q) => $this->applyFilters($q));
    }

    # ---------------------------------------------------------
    #  FILTERS
    # ---------------------------------------------------------
    private function applyFilters(Builder $query): void
    {
        if (!empty($this->filters['date_from']) && !empty($this->filters['date_to'])) {
            $query->whereHas('latestSubscription', fn ($q) =>
                $q->whereBetween('expires_at', [
                    (string) $this->filters['date_from'],
                    (string) $this->filters['date_to'],
                ])
            );
        }

        // Branch filter — the real FK column is branch_number, not branch_id
        if (!empty($this->filters['branch_id'])) {
            $query->where('branch_number', $this->filters['branch_id']);
        }

        if (!empty($this->filters['status'])) {
            $query->where('status', $this->filters['status']);
        }
    }

    # ---------------------------------------------------------
    #  HEADINGS
    # ---------------------------------------------------------
    public function headings(): array
    {
        return [
            'ID',
            'CID',
            'Name',
            'Branch',
            'Email',
            'Phone',
            'Address',
            'Age',
            'Gender',
            'Marital Status',
            'Occupation',
            'Employment Status',
            'Status',
            'Joined Date',
            'Account Name',
            'Account Number',
            'Amount',
            'Payment Date',
            'Subscription Date',
            'Remarks',
            'Note: Date Format',
        ];
    }

    # ---------------------------------------------------------
    #  DATA MAPPING
    # ---------------------------------------------------------
    public function map($member): array
    {
        // Cache relation lookups — avoids repeated magic-property resolution per row
        $sub     = $member->latestSubscription;
        $branch  = $member->branch;
        $account = $sub?->productAccount;

        return [
            $member->id,
            $member->cid,
            $member->full_name,                     // accessor: "Last, First Middle Suffix"
            $branch?->branch_name,
            $member->email,
            $member->contact_number,
            $member->full_address,                  // accessor: "house, street, barangay, city, province, zip"
            $member->age,                           // accessor: computed from birth_date
            $member->gender,
            $member->marital_status,
            $member->occupation,
            $member->employment_status,
            $member->is_active ? 'Active' : 'Archived',
            $member->created_at?->format('m/d/Y'),
            $account?->product_name,
            $account?->account_number,
            $sub?->amount,
            '',                                     // Payment Date (intentionally blank)
            $sub?->expires_at?->format('m/d/Y'),
            'RENEWAL',
            'All dates are in month/day/Year format. (12/18/2025)',
        ];
    }

    # ---------------------------------------------------------
    #  STYLES
    # ---------------------------------------------------------
    public function styles(Worksheet $sheet): array
    {
        return [
            1 => [
                'font' => [
                    'bold'  => true,
                    'color' => ['rgb' => 'FFFFFF'],
                    'size'  => 12,
                ],
                'fill' => [
                    'fillType'   => Fill::FILL_SOLID,
                    'startColor' => ['rgb' => '4F46E5'],
                ],
                'alignment' => [
                    'horizontal' => Alignment::HORIZONTAL_CENTER,
                    'vertical'   => Alignment::VERTICAL_CENTER,
                ],
            ],
        ];
    }

    # ---------------------------------------------------------
    #  COLUMN WIDTHS
    # ---------------------------------------------------------
    public function columnWidths(): array
    {
        return [
            'A' => 38,
            'B' => 18,
            'C' => 18,
            'D' => 30,
            'E' => 38,
            'F' => 18,
            'G' => 35,
            'H' => 20,
            'I' => 20,
            'J' => 20,
            'K' => 38,
            'L' => 35,
            'M' => 25,
            'N' => 12,
            'O' => 10,
            'P' => 15,
            'Q' => 12,
        ];
    }

    # ---------------------------------------------------------
    #  SHEET TITLE
    # ---------------------------------------------------------
    public function title(): string
    {
        return 'Member Report';
    }

    # ---------------------------------------------------------
    #  POST-SHEET EVENTS
    # ---------------------------------------------------------
    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                // Freeze header row only — ShouldAutoSize already handles column sizing
                $event->sheet->getDelegate()->freezePane('A2');
            },
        ];
    }

    # ---------------------------------------------------------
    #  CHUNK SIZE
    # ---------------------------------------------------------
    public function chunkSize(): int
    {
        return 2000;
    }
}
