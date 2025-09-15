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

    protected function authorizeAccess(): void
    {
        parent::authorizeAccess();

        /** @var Model $record */
        $record = $this->getRecord();

        // ✅ Check if the user has the 'super_admin' role and the member is active
        if (! $record->is_active  || ! Auth::user()->hasRole('super_admin')) {

            Log::warning('Unauthorized edit attempt:', [
                'user_id' => $user->id,
                'user_role' => $user->roles->pluck('name')->toArray(),
                'member_id' => $record->id,
                'is_active' => $record->is_active,
                'reason' => $record->is_active ? 'User does not have super_admin role.' : 'Member is archived.',
            ]);

            // Option 1: Throw a 403 Forbidden error
            throw new HttpException(403, 'You cannot edit an archived profile.');

            // Option 2: Redirect instead of throwing an error (uncomment if needed)
            // redirect()->route('filament.admin.resources.members.index')->send();
        }
    }
}
