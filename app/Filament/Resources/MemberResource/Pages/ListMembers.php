<?php

namespace App\Filament\Resources\MemberResource\Pages;

use App\Exports\SubscriptionImportIssuesExport;
use App\Filament\Resources\MemberResource;
use App\Imports\SubscriptionImport;
use App\Imports\MemberUpload;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use App\Models\{Insurance, Member, Subscription};
use Filament\Actions;
use Filament\Resources\Components\Tab;
use Filament\Resources\Pages\ListRecords;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Database\Eloquent\Builder;
use Filament\Actions\Action;
use Maatwebsite\Excel\Validators\ValidationException;
use Illuminate\Support\Facades\Cache;

class ListMembers extends ListRecords
{
    protected static string $resource = MemberResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('uploadCsv')
                ->label('Upload Vouchers CSV')
                ->form([
                    FileUpload::make('file')
                        ->label('CSV File')
                        ->required()
                        ->acceptedFileTypes(['text/csv', 'text/plain', 'application/vnd.ms-excel'])
                        ->disk('local') // Store in storage/app
                        ->directory('uploads') // path: storage/app/uploads
                        ->maxSize(40960), // Set the maximum file size in kilobytes (40MB = 40960KB)
                ])
                ->action(function (array $data): void {
                    try {
                        // Get full path to the uploaded file
                        $path = Storage::disk('local')->path($data['file']);

                        // Import CSV using your VoucherUpload import class
                        Excel::import(new MemberUpload, $path);

                        Notification::make()
                            ->title('Upload Successful')
                            ->success()
                            ->body('Vouchers have been imported successfully.')
                            ->send();
                    } catch (ValidationException $e) {
                        Notification::make()
                            ->title('Import Failed')
                            ->danger()
                            ->body('There were validation errors in your CSV file.')
                            ->send();

                        Log::error('Voucher CSV Import Validation Error: ', [
                            'errors' => $e->failures(),
                        ]);
                    } catch (\Exception $e) {
                        Notification::make()
                            ->title('Error')
                            ->danger()
                            ->body('An unexpected error occurred during import.')
                            ->send();

                        Log::error('Voucher CSV Import Error: ' . $e->getMessage());
                    }
                })
                ->modalHeading('Upload Voucher CSV')
                ->modalButton('Upload')
                ->color('primary'),
            Actions\CreateAction::make()
                ->label('Register')
                ->icon('heroicon-o-plus'),
            Actions\Action::make('create')
                ->label('Upload Member Subscription')
                ->icon('heroicon-o-cloud-arrow-up')
                ->form([
                    Select::make('insurance_id')
                        ->label('Insurance Plan')
                        ->options(Insurance::pluck('insurance_name', 'id'))
                        ->searchable()
                        ->required()
                        ->hint('Choose which insurance plan these members belong to'),

                    FileUpload::make('subscription_file')
                        ->label('Member Data File')
                        ->required()
                        ->disk('local')
                        ->directory('subscription')
                        ->acceptedFileTypes(['text/csv', 'application/csv'])
                        ->maxSize(10) // MB
                        ->hint('CSV file only (max 10MB)')
                        ->rules(['file', 'mimes:csv', 'max:10240'])
                ])
                ->modalHeading('Upload Member Subscriptions')
                ->modalSubmitActionLabel('Upload File')
                ->modalWidth('lg')
                ->slideOver()
                ->closeModalByClickingAway(false)
                ->action(function (array $data, \Filament\Actions\Action $action) {
                    try {
                        $insuranceId = $data['insurance_id'];
                        $filePath = Storage::disk('local')->path($data['subscription_file']);

                        if (!file_exists($filePath)) {
                            throw new \Exception("Uploaded file not found at path: {$filePath}");
                        }

                        $import = new SubscriptionImport($insuranceId);
                        Excel::import($import, $filePath);

                        // ✅ Export errors and duplicates
                        $errorPath = null;
                        $duplicatePath = null;

                        if ($import->getErrorCount() > 0) {
                            $errorExport = new \App\Exports\SubscriptionImportIssuesExport(
                                $import->getErrorRows(),
                                'Errors'
                            );

                            $errorPath = 'exports/errors_' . now()->format('Ymd_His') . '.xlsx';
                            $stored = Excel::store($errorExport, $errorPath, 'local');
                            if (! $stored) {
                                Log::error("Error export not stored.", ['path' => $errorPath]);
                            }
                        }

                        if ($import->getDuplicateCount() > 0) {
                            $duplicateExport = new \App\Exports\SubscriptionImportIssuesExport(
                                $import->getDuplicateRows(),
                                'Duplicates'
                            );

                            $duplicatePath = 'exports/duplicates_' . now()->format('Ymd_His') . '.xlsx';
                            $stored = Excel::store($duplicateExport, $duplicatePath, 'local');
                            if (! $stored) {
                                Log::error("Duplicate export not stored.", ['path' => $duplicatePath]);
                            }
                        }

                        // ✅ Final Notification
                        \Filament\Notifications\Notification::make()
                            ->title('Subscription Import Complete')
                            ->body("
                            ✅ <strong>Inserted:</strong> {$import->getInsertedCount()} subscriptions<br>
                            💰 <strong>Total Amount:</strong> ₱" . number_format($import->getTotalInsertedAmount(), 2) . "<br>
                            ⚠️ <strong>Duplicates Skipped:</strong> {$import->getDuplicateCount()}" .
                                ($duplicatePath
                                    ? "<br><a href='" . route('download.import.result', ['path' => $duplicatePath]) . "' target='_blank' class='underline text-blue-500'>Download Duplicates</a>"
                                    : '') .
                                "<br>❌ <strong>Errors:</strong> {$import->getErrorCount()}" .
                                ($errorPath
                                    ? "<br><a href='" . route('download.import.result', ['path' => $errorPath]) . "' target='_blank' class='underline text-red-500'>Download Errors</a>"
                                    : '')
                            )
                            ->success()
                            ->duration(120000)
                            ->send();

                    } catch (\Maatwebsite\Excel\Validators\ValidationException $e) {
                        \Filament\Notifications\Notification::make()
                            ->title('Upload Failed due to validation error.')
                            ->danger()
                            ->send();

                    } catch (\Throwable $e) {
                        \Filament\Notifications\Notification::make()
                            ->title('Upload Failed: ' . $e->getMessage())
                            ->danger()
                            ->send();
                    }
                }),
        ];
    }

    // public function getTabs(): array
    // {
    //     // Get all counts in one query to avoid N+1 problem
    //     $counts = $this->getBadgeCounts();

    //     return [
    //         'active' => Tab::make('Active Members')
    //             ->icon('heroicon-o-check-circle')
    //             ->badge($counts['active'])
    //             ->modifyQueryUsing(fn (Builder $query) => $query->active()),

    //         'pending' => Tab::make('Pending')
    //             ->icon('heroicon-o-clock')
    //             ->badge($counts['pending'])
    //             ->modifyQueryUsing(fn (Builder $query) => $query->active()->pending()),

    //         'with_active_subscriptions' => Tab::make('Active Subscriptions')
    //             ->icon('heroicon-o-heart')
    //             ->badge($counts['with_active_subscriptions'])
    //             ->modifyQueryUsing(fn (Builder $query) => $query
    //                 ->active()
    //                 ->accepted()
    //                 ->whereHas('subscriptions', fn ($q) => $q->active())
    //             ),

    //         'expiring_soon' => Tab::make('Expiring Soon')
    //             ->icon('heroicon-o-clock')
    //             ->badge($counts['expiring_soon'])
    //             ->modifyQueryUsing(fn (Builder $query) => $query
    //                 ->active()
    //                 ->accepted()
    //                 ->whereHas('subscriptions', fn ($q) => $q->expiringSoon())
    //             ),

    //         'expired' => Tab::make('Expired')
    //             ->icon('heroicon-o-x-circle')
    //             ->badge($counts['expired'])
    //             ->modifyQueryUsing(fn (Builder $query) => $query
    //                 ->active()
    //                 ->accepted()
    //                 ->whereHas('latestSubscription', fn ($q) => $q->where('expires_at', '<', now()))
    //             ),

    //         'declined' => Tab::make('Declined')
    //             ->icon('heroicon-o-x-circle')
    //             ->badge($counts['declined'])
    //             ->modifyQueryUsing(fn (Builder $query) => $query->active()->declined()),

    //         'archive' => Tab::make('Archive')
    //             ->icon('heroicon-o-folder')
    //             ->badge($counts['archive'])
    //             ->modifyQueryUsing(fn (Builder $query) => $query->archive()),
    //     ];
    // }

    // private function getBadgeCounts(): array
    // {
    //     return Cache::remember('member_tab_counts', 300, function () {
    //         return [
    //             'active' => Member::active()->count(),
    //             'pending' => Member::active()->pending()->count(),
    //             'with_active_subscriptions' => Member::active()->accepted()
    //                 ->whereHas('subscriptions', fn ($q) => $q->active())->count(),
    //             'expiring_soon' => Member::active()->accepted()
    //                 ->whereHas('subscriptions', fn ($q) => $q->expiringSoon())->count(),
    //             'expired' => Member::active()->accepted()
    //                 ->whereHas('latestSubscription', fn ($q) => $q->where('expires_at', '<', now()))->count(),
    //             'declined' => Member::active()->declined()->count(),
    //             'archive' => Member::archive()->count(),
    //         ];
    //     });
    // }

    // public function getDefaultActiveTab(): string
    // {
    //     return 'expired';
    // }
}
