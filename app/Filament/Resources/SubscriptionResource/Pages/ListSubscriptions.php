<?php

namespace App\Filament\Resources\SubscriptionResource\Pages;

use App\Filament\Resources\SubscriptionResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

class ListSubscriptions extends ListRecords
{
    protected static string $resource = SubscriptionResource::class;

    /**
     * Customize the query for the table with branch filtering.
     */
    protected function getTableQuery(): Builder
    {
        $query = parent::getTableQuery();

        $user = Auth::user();

        // Super admins see all subscriptions
        if (!$user || $user->hasRole('super_admin')) {
            return $query;
        }

        // Get user's branch number
        $branchNumber = $user->branch?->branch_number;

        // If no branch assigned, return empty result set
        if (!$branchNumber) {
            return $query->whereRaw('1 = 0');
        }

        // Use whereIn with subquery for better performance
        // This avoids N+1 queries that whereHas can cause
        return $query->whereIn('member_id', function ($subquery) use ($branchNumber) {
            $subquery->select('id')
                ->from('members')
                ->where('branch_number', $branchNumber);
        });
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
