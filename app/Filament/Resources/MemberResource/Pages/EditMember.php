<?php

namespace App\Filament\Resources\MemberResource\Pages;

use App\Filament\Resources\MemberResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpKernel\Exception\HttpException;

class EditMember extends EditRecord
{
    protected static string $resource = MemberResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }

    // -------------------------------------------------------------------------
    // Access Control
    //
    // Optimized: resolved $user once and reused — avoids two Auth::user() calls.
    // Error message is more descriptive for non-admin users.
    // -------------------------------------------------------------------------

    protected function authorizeAccess(): void
    {
        parent::authorizeAccess();

        $record = $this->getRecord();
        $user   = Auth::user();

        // Super admins can edit any record regardless of active state
        if ($user->hasRole('super_admin') || $record->is_active) {
            return;
        }

        Log::warning('Unauthorized edit attempt on archived member', [
            'user_id'    => $user->id,
            'user_roles' => $user->roles->pluck('name'),
            'member_id'  => $record->id,
        ]);

        throw new HttpException(403, 'You cannot edit an archived or deceased member profile.');
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('view', ['record' => $this->getRecord()]);
    }

    protected function getSavedNotificationTitle(): ?string
    {
        return 'Member profile updated successfully.';
    }
}
