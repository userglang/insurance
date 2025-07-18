<?php

namespace App\Filament\Resources\ProductAccountResource\Pages;

use App\Filament\Resources\ProductAccountResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditProductAccount extends EditRecord
{
    protected static string $resource = ProductAccountResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
