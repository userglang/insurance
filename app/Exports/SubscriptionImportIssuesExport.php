<?php

namespace App\Exports;

use App\Models\Member;
use Illuminate\Contracts\Support\Responsable;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;

class SubscriptionImportIssuesExport implements FromCollection, WithHeadings, Responsable
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
        $memberIds = collect($this->issues)->pluck('row.0')->filter()->unique();
        $members = Member::whereIn('id', $memberIds)->get()->pluck('full_name', 'id');

        return collect($this->issues)->map(function ($item) use ($members) {
            $memberId = $item['row'][0] ?? null;

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
}
