<?php

namespace App\Filament\Resources\ProductAccountResource\Pages;

use App\Filament\Resources\ProductAccountResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListProductAccounts extends ListRecords
{
    protected static string $resource = ProductAccountResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
