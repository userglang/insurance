<?php

namespace App\Filament\Resources\MemberResource\Pages;

use App\Models\Member;
use Filament\Forms\Components\FileUpload;
use Filament\Resources\Pages\Page;
use Filament\Pages\Actions\EditAction; // <-- Make sure this is imported
use Filament\Pages\Actions; // Needed for other actions

class ViewMember extends Page
{
    protected static string $resource = \App\Filament\Resources\MemberResource::class;

    protected static string $view = 'filament.resources.member-resource.pages.view-member';

    public Member $record;

    public function mount(Member $record): void
    {
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
        return [
            Actions\Action::make('edit')
                ->label('Edit Profile')
                ->url(fn () => route('filament.main.resources.members.edit', $this->record))
                ->icon('heroicon-o-pencil-square')
                ->color('primary'),

            Actions\Action::make('download_pdf')
                ->label('Print Profile')
                ->color('success')
                ->icon('heroicon-o-printer')
                ->url(fn () => route('member.print', $this->record))
                ->openUrlInNewTab()
                ->requiresConfirmation(),

        ];
    }
}
