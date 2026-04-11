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

        $record = $this->getRecord();
        $user   = Auth::user();

        if ($record->is_active || $user->hasRole('super_admin')) {
            return;
        }

        Log::warning('Unauthorized edit attempt on archived member', [
            'user_id'   => $user->id,
            'user_roles' => $user->roles->pluck('name'),
            'member_id' => $record->id,
        ]);

        throw new HttpException(403, 'You cannot edit an archived profile.');
    }
}
