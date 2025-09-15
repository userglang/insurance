<?php

namespace App\Services;

use Illuminate\Database\Eloquent\Model;
use Filament\Notifications\Notification;

class MemberStatusService
{
    public function accept(Model $record): void
    {
        try {
            $record->update([
                'status' => 'accepted',
            ]);

            Notification::make()
                ->success()
                ->title('Member Accepted')
                ->send();
        } catch (\Exception $e) {
            Notification::make()
                ->danger()
                ->title('Error accepting member')
                ->send();
        }
    }
}
