<?php

namespace App\Filament\Widgets;

use App\Models\Subscription;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

class SubscriptionTable extends BaseWidget
{
    protected static ?int $sort = 2;
    protected int|string|array $columnSpan = 'full'; // use 'full', '2', '3' etc., based on your layout

    public function table(Table $table): Table
    {
        return $table
            ->query($this->getFilteredQuery())
            ->columns([
                Tables\Columns\TextColumn::make('member.full_name')
                    ->label('Member')
                    ->sortable()
                    ->searchable(),

                Tables\Columns\TextColumn::make('member.branch_number')
                    ->label('Branch')
                    ->sortable()
                    ->searchable()
                    ->visible(fn () => $this->isSuperAdmin()), // Only show branch column for super admin

                Tables\Columns\TextColumn::make('insurance.insurance_name')
                    ->label('Insurance')
                    ->sortable(),

                Tables\Columns\TextColumn::make('amount')
                    ->money('PHP')
                    ->label('Amount'),

                Tables\Columns\TextColumn::make('activated_at')
                    ->label('Activated')
                    ->date(),

                Tables\Columns\TextColumn::make('expires_at')
                    ->label('Expires')
                    ->date()
                    ->color(fn (Subscription $record) => match (true) {
                        $record->isExpired() => 'danger',
                        $record->daysRemaining() <= 30 => 'warning',
                        default => 'success',
                    }),

                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->label('Status')
                    ->formatStateUsing(fn ($state) => ucfirst($state))
                    ->color(fn ($state) => match ($state) {
                        'active' => 'success',
                        'expired' => 'danger',
                        'future' => 'warning',
                        default => 'gray',
                    }),
            ]);
    }

    /**
     * Get filtered query based on user role
     */
    protected function getFilteredQuery(): Builder
    {
        $query = Subscription::query()
            ->whereIn('id', function ($subQuery) {
                $subQuery->select(DB::raw('MAX(id)'))
                    ->from('subscriptions')
                    ->groupBy('member_id', 'insurance_id');
            })
            ->whereHas('member', function (Builder $query) {
                $query->where('is_active', true);

                // Apply branch filter if not super admin
                if (!$this->isSuperAdmin()) {
                    $userBranchNumber = $this->getUserBranchNumber();
                    if ($userBranchNumber) {
                        $query->where('branch_number', $userBranchNumber);
                    }
                }
            })
            ->with(['member', 'member.branch', 'insurance'])
            ->limit(10); // Show only latest 10 entries

        return $query;
    }

    /**
     * Check if current user is super admin
     */
    protected function isSuperAdmin(): bool
    {
        $user = Auth::user();

        if (!$user) {
            return false;
        }

        // Method 1: Check if user has a 'roles' attribute/relationship
        if (method_exists($user, 'hasRole')) {
            return $user->hasRole('super_admin');
        }

        // Method 2: Check if user has a direct 'role' attribute
        if (isset($user->role)) {
            return $user->role === 'super_admin';
        }

        // Method 3: Check if user has a 'roles' relationship (many-to-many)
        if ($user->relationLoaded('roles') || method_exists($user, 'roles')) {
            return $user->roles()->where('name', 'super_admin')->exists();
        }

        // Default to false if no role system is found
        return false;
    }

    /**
     * Get current user's branch number
     */
    protected function getUserBranchNumber(): ?string
    {
        $user = Auth::user();

        if (!$user) {
            return null;
        }

        $branchNumber = null;

        // Method 1: Direct branch_number attribute
        if (isset($user->branch_number)) {
            $branchNumber = $user->branch_number;
        }
        // Method 2: Through branch relationship
        elseif ($user->relationLoaded('branch') || method_exists($user, 'branch')) {
            $branchNumber = $user->branch?->branch_number;
        }
        // Method 3: Through member relationship (if user is also a member)
        elseif ($user->relationLoaded('member') || method_exists($user, 'member')) {
            $branchNumber = $user->member?->branch_number;
        }
        // Method 4: Through member's branch relationship
        elseif (($user->relationLoaded('member') || method_exists($user, 'member')) &&
                $user->member &&
                ($user->member->relationLoaded('branch') || method_exists($user->member, 'branch'))) {
            $branchNumber = $user->member?->branch?->branch_number;
        }

        // Return string or null
        return $branchNumber ? (string) $branchNumber : null;
    }

    /**
     * Check if widget should be visible based on user authentication
     */
    public static function canView(): bool
    {
        return Auth::check();
    }
}
