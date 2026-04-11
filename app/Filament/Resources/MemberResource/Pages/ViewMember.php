<?php

namespace App\Filament\Resources\MemberResource\Pages;

use App\Filament\Resources\MemberResource;
use App\Models\Member;
use Filament\Pages\Actions;
use Filament\Resources\Pages\Page;
use Illuminate\Support\Facades\Gate;

class ViewMember extends Page
{
    protected static string $resource = MemberResource::class;
    protected static string $view = 'filament.resources.member-resource.pages.view-member';

    public Member $record;

    public function mount(Member $record): void
    {
        Gate::authorize('view', $record);

        $this->record = $record;
    }

    public function getTitle(): string
    {
        return 'View Profile';
    }

    public static function getRoute(?string $panel = null): string
    {
        return '/{record}/view';
    }

    public static function getRouteName(?string $panel = null): string
    {
        return static::generateRouteName($panel, 'view');
    }

    protected function getHeaderActions(): array
    {
        $isInactive = ! $this->record->is_active;

        return [
            Actions\Action::make('edit')
                ->label('Edit Profile')
                ->icon('heroicon-o-pencil-square')
                ->color('primary')
                ->url(fn () => route('filament.main.resources.members.edit', $this->record))
                ->disabled($isInactive),

            Actions\Action::make('download_pdf')
                ->label('Print Profile')
                ->icon('heroicon-o-printer')
                ->color('success')
                ->url(fn () => route('member.print', $this->record))
                ->openUrlInNewTab()
                ->requiresConfirmation()
                ->disabled($isInactive),
        ];
    }
}
