<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Illuminate\Contracts\Support\Responsable;
use Maatwebsite\Excel\Concerns\Exportable;

class SubscriptionImportIssuesExport implements FromCollection, WithHeadings, Responsable
{
    use Exportable;

    protected array $issues;
    protected string $title;

    public string $fileName;

    public function __construct(array $issues, string $title = 'Issues')
    {
        $this->issues = $issues;
        $this->title = $title;
        $this->fileName = 'subscription-import-' . Str::slug($this->title) . '-' . now()->format('Ymd_His') . '.xlsx';
    }

    public function collection(): Collection
    {
        return collect($this->issues)->map(function ($item) {
            return [
                'Member ID' => (string) ($item['row'][0] ?? ''),
                'Reason' => (string) ($item['reason'] ?? ''),
                'Details' => isset($item['errors'])
                    ? json_encode($item['errors'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
                    : (string) ($item['error'] ?? ''),
            ];
        });
    }

    public function headings(): array
    {
        return ['Member ID', 'Reason', 'Details'];
    }
}
