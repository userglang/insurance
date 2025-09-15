<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Filament\Notifications\Notification;

class StatusService
{
    public function toggle(Model $record, User $user): void
    {
        try {
            $currentStatus = $record->is_active;

            if ($user->hasRole('super_admin')) {
                // Super admin: can toggle both ways
                $record->update(['is_active' => !$currentStatus]);

                Notification::make()
                    ->success()
                    ->title('Status Updated')
                    ->send();
            } else {
                // Non-super-admin: can only deactivate (not activate)
                if ($currentStatus) {
                    $record->update(['is_active' => false]);

                    Notification::make()
                        ->success()
                        ->title('Deactivated Successfully')
                        ->send();
                } else {
                    Notification::make()
                        ->danger()
                        ->title('Permission Denied')
                        ->body('You are not allowed to activate this record.')
                        ->send();
                }
            }
        } catch (\Exception $e) {
            Notification::make()
                ->danger()
                ->title('Error updating status')
                ->body('Please try again.')
                ->send();
        }
    }
}
