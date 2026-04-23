<?php

namespace App\Filament\Resources\MemberResource\Pages;

use App\Exports\MembersExport;
use App\Filament\Resources\MemberResource;
use App\Imports\MemberUpload;
use App\Imports\SubscriptionImport;
use App\Exports\SubscriptionImportIssuesExport;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel;
use Maatwebsite\Excel\Validators\ValidationException;
use App\Models\Insurance;
use Filament\Actions\ActionGroup;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Get;

class ListMembers extends ListRecords
{
    protected static string $resource = MemberResource::class;

    // -------------------------------------------------------------------------
    // Header Actions
    // -------------------------------------------------------------------------

    protected function getHeaderActions(): array
    {
        return [
            $this->uploadVouchersCsvAction(),
            CreateAction::make()->label('Register')->icon('heroicon-o-plus'),
            $this->uploadRenewalSubscriptionAction(),
        ];
    }

    private function uploadVouchersCsvAction(): Action
    {
        return Action::make('uploadCsv')
            ->label('Upload Vouchers CSV')
            ->color('primary')
            ->visible(fn () => Auth::user()?->hasRole('super_admin'))
            ->form([
                FileUpload::make('file')
                    ->label('CSV File')
                    ->required()
                    ->acceptedFileTypes(['text/csv', 'text/plain', 'application/vnd.ms-excel'])
                    ->disk('local')
                    ->directory('uploads')
                    ->maxSize(40960),

                Toggle::make('update_existing')
                    ->label('Update existing records')
                    ->helperText('If enabled, member info and subscription details will be overwritten when a duplicate is found.')
                    ->default(false),
            ])
            ->modalHeading('Upload Voucher CSV')
            ->modalButton('Upload')
            ->action(function (array $data): void {
                try {
                    $path = Storage::disk('local')->path($data['file']);
                    Excel::import(new MemberUpload((bool) ($data['update_existing'] ?? false)), $path);

                    Notification::make()
                        ->title('Upload Successful')
                        ->body('Vouchers have been imported successfully.')
                        ->success()
                        ->send();
                } catch (ValidationException $e) {
                    Log::error('Voucher CSV import validation error', ['errors' => $e->failures()]);

                    Notification::make()
                        ->title('Import Failed')
                        ->body('There were validation errors in your CSV file.')
                        ->danger()
                        ->send();
                } catch (\Exception $e) {
                    Log::error('Voucher CSV import error', ['error' => $e->getMessage()]);

                    Notification::make()
                        ->title('Error')
                        ->body('An unexpected error occurred during import.')
                        ->danger()
                        ->send();
                }
            });
    }

    private function uploadRenewalSubscriptionAction(): Action
    {
        return Action::make('uploadRenewalSubscription')
            ->label('Upload Renewal Subscription')
            ->icon('heroicon-o-cloud-arrow-up')
            ->modalHeading('Upload Member Subscriptions')
            ->modalSubmitActionLabel('Upload File')
            ->modalWidth('lg')
            ->slideOver()
            ->closeModalByClickingAway(false)
            ->form([
                Select::make('insurance_id')
                    ->label('Insurance Plan')
                    ->options(fn () => Insurance::pluck('insurance_name', 'id'))
                    ->searchable()
                    ->required()
                    ->hint('Choose which insurance plan these members belong to'),

                FileUpload::make('subscription_file')
                    ->label('Member Data File')
                    ->required()
                    ->disk('local')
                    ->directory('subscription')
                    ->acceptedFileTypes([
                        'application/vnd.ms-excel',
                        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                        'text/csv',
                    ])
                    ->rules(['file', 'mimes:csv,xls,xlsx'])
                    ->hint('CSV file only (max 10MB)'),
            ])
            ->action(function (array $data): void {
                try {
                    $filePath = Storage::disk('local')->path($data['subscription_file']);

                    if (! file_exists($filePath)) {
                        throw new \RuntimeException("Uploaded file not found at: {$filePath}");
                    }

                    $import = new SubscriptionImport($data['insurance_id']);
                    Excel::import($import, $filePath);

                    $errorPath     = $this->maybeExportIssues($import->getErrorRows(), 'errors', $import->getErrorCount());
                    $duplicatePath = $this->maybeExportIssues($import->getDuplicateRows(), 'duplicates', $import->getDuplicateCount());

                    Notification::make()
                        ->title('Subscription Import Complete')
                        ->body($this->buildImportSummary($import, $errorPath, $duplicatePath))
                        ->success()
                        ->duration(120000)
                        ->send();
                } catch (ValidationException) {
                    Notification::make()
                        ->title('Upload Failed due to a validation error.')
                        ->danger()
                        ->send();
                } catch (\Throwable $e) {
                    Notification::make()
                        ->title('Upload Failed: ' . $e->getMessage())
                        ->danger()
                        ->send();
                }
            });
    }

    // -------------------------------------------------------------------------
    // Table Query
    // -------------------------------------------------------------------------

    public function getTableQuery(): Builder
    {
        $user  = Auth::user();
        $query = parent::getTableQuery()
            ->select([
                'members.id', 'members.cid',
                'members.first_name', 'members.middle_name', 'members.last_name',
                'members.email', 'members.status', 'members.birth_date',
                'members.gender', 'members.marital_status',
                'members.occupation', 'members.employment_status',
                'members.branch_number', 'members.contact_number',
                'members.city', 'members.province', 'members.barangay',
                'members.created_at', 'members.is_active',
            ])
            ->withOnly(['branch:id,branch_number,branch_name']);

        if ($user && ! $user->hasRole('super_admin')) {
            $branchNumber = $user->branch?->branch_number;

            $branchNumber
                ? $query->where('members.branch_number', $branchNumber)
                : $query->whereRaw('1 = 0');
        }

        return $query;
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function maybeExportIssues(array $rows, string $type, int $count): ?string
    {
        if ($count === 0) {
            return null;
        }

        $label  = ucfirst($type);
        $path   = "exports/{$type}_" . now()->format('Ymd_His') . '.xlsx';
        $stored = Excel::store(new SubscriptionImportIssuesExport($rows, $label), $path, 'local');

        if (! $stored) {
            Log::error("Failed to store {$label} export", ['path' => $path]);
        }

        return $path;
    }

    private function buildImportSummary(SubscriptionImport $import, ?string $errorPath, ?string $duplicatePath): string
    {
        $downloadLink = fn (?string $path, string $label, string $color) => $path
            ? "<br><a href='" . route('download.import.result', ['path' => $path]) . "' target='_blank' class='underline text-{$color}-500'>{$label}</a>"
            : '';

        return implode('', [
            "✅ <strong>Inserted:</strong> {$import->getInsertedCount()} subscriptions<br>",
            "💰 <strong>Total Amount:</strong> ₱" . number_format($import->getTotalInsertedAmount(), 2) . "<br>",
            "⚠️ <strong>Duplicates Skipped:</strong> {$import->getDuplicateCount()}",
            $downloadLink($duplicatePath, 'Download Duplicates', 'blue'),
            "<br>❌ <strong>Errors:</strong> {$import->getErrorCount()}",
            $downloadLink($errorPath, 'Download Errors', 'red'),
        ]);
    }
}
