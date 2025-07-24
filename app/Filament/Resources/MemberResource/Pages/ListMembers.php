<?php

namespace App\Filament\Resources\MemberResource\Pages;

use App\Filament\Resources\MemberResource;
use App\Models\{Member, Subscription};
use Filament\Actions;
use Filament\Resources\Components\Tab;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;

class ListMembers extends ListRecords
{
    protected static string $resource = MemberResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->label('Register')
                ->icon('heroicon-o-plus'),
        ];
    }

    public function getTabs(): array
    {
        return [
//            'all' => Tab::make('All Members')
//                ->icon('heroicon-o-users')
//                ->badge(Member::count()),

            'active' => Tab::make('Active Members')
                ->icon('heroicon-o-check-circle')
                ->badge(Member::active()->count())
                ->modifyQueryUsing(fn (Builder $query) => $query->active()),

            'pending' => Tab::make('Pending')
                ->icon('heroicon-o-clock')
                ->badge(Member::active()->pending()->count())
                ->modifyQueryUsing(fn (Builder $query) => $query->active()->pending()),

            'with_active_subscriptions' => Tab::make('Active Subscriptions')
                ->icon('heroicon-o-heart')
                ->badge(
                    Member::active()->accepted()
                        ->whereHas('subscriptions', fn ($q) => $q->active())
                        ->count()
                )
                ->modifyQueryUsing(fn (Builder $query) => $query
                    ->active()
                    ->accepted()
                    ->whereHas('subscriptions', fn ($q) => $q->active())
                ),

            'expiring_soon' => Tab::make('Expiring Soon')
                ->icon('heroicon-o-clock')
                ->badge(
                    Member::active()->accepted()
                        ->whereHas('subscriptions', fn ($q) => $q->expiringSoon())
                        ->count()
                )
                ->modifyQueryUsing(fn (Builder $query) => $query
                    ->active()
                    ->accepted()
                    ->whereHas('subscriptions', fn ($q) => $q->expiringSoon())
                ),

            'expired' => Tab::make('Expired')
                ->icon('heroicon-o-x-circle')
                ->badge(
                    Member::active()->accepted()
                        ->whereHas('latestSubscription', fn ($q) => $q->where('expires_at', '<', now()))
                        ->count()
                )
                ->modifyQueryUsing(fn (Builder $query) => $query
                    ->active()
                    ->accepted()
                    ->whereHas('latestSubscription', fn ($q) => $q->where('expires_at', '<', now()))
                ),

            'declined' => Tab::make('Declined')
                ->icon('heroicon-o-x-circle')
                ->badge(Member::active()->declined()->count())
                ->modifyQueryUsing(fn (Builder $query) => $query->active()->declined()),

            'archive' => Tab::make('Archive')
                ->icon('heroicon-o-folder')
                ->badge(Member::archive()->count())
                ->modifyQueryUsing(fn (Builder $query) => $query->archive()),
        ];
    }

    public function getDefaultActiveTab(): string
    {
        return 'active';
    }
}
