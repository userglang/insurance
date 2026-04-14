<?php

namespace App\Exports;

use App\Models\Member;
use Illuminate\Contracts\Support\Responsable;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class SubscriptionImportIssuesExport implements FromCollection, WithHeadings, WithStyles, WithColumnFormatting, Responsable
{
    use Exportable;

    public string $fileName;

    public function __construct(
        protected array  $issues,
        protected string $title = 'Issues',
    ) {
        $this->fileName = 'subscription-import-' . Str::slug($title) . '-' . now()->format('Ymd_His') . '.xlsx';
    }

    public function collection(): Collection
    {
        // Safely extract member ID from either associative or numeric row array
        $memberIds = collect($this->issues)
            ->map(fn($item) => $this->extractMemberId($item))
            ->filter()
            ->unique();

        $members = Member::whereIn('id', $memberIds)->get()->pluck('full_name', 'id');

        return collect($this->issues)->map(function ($item) use ($members) {
            $memberId = $this->extractMemberId($item);

            return [
                'Member ID'   => (string) ($memberId ?? ''),
                'Member Name' => $memberId ? ($members[$memberId] ?? 'Unknown Member') : '',
                'Reason'      => (string) ($item['reason'] ?? ''),
                'Details'     => isset($item['errors'])
                    ? json_encode($item['errors'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
                    : (string) ($item['error'] ?? ''),
            ];
        });
    }

    public function headings(): array
    {
        return ['Member ID', 'Member Name', 'Reason', 'Details'];
    }

    // Set all columns as text format to prevent Excel auto-conversion
    public function columnFormats(): array
    {
        return [
            'A' => NumberFormat::FORMAT_TEXT,
            'B' => NumberFormat::FORMAT_TEXT,
            'C' => NumberFormat::FORMAT_TEXT,
            'D' => NumberFormat::FORMAT_TEXT,
        ];
    }

    // Style the header row
    public function styles(Worksheet $sheet): array
    {
        return [
            1 => [
                'font' => ['bold' => true],
            ],
        ];
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    /**
     * Extract member ID safely from either:
     *   - associative row: $item['row']['id']
     *   - numeric row:     $item['row'][0]
     */
    private function extractMemberId(array $item): ?string
    {
        $row = $item['row'] ?? [];

        $id = $row['id'] ?? $row[0] ?? null;

        return $id ? (string) $id : null;
    }
}
