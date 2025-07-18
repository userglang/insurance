<?php

namespace App\Filament\Resources\MemberResource\Pages;

use App\Filament\Resources\MemberResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;
use Filament\Forms\Components\Wizard\Step;
use Filament\Resources\Pages\CreateRecord\Concerns\HasWizard;

class CreateMember extends CreateRecord
{
    use HasWizard;

    protected static string $resource = MemberResource::class;

    public static function getSteps(): array
    {
        return
        [
            // First Step: Member Details
            Step::make('Member Information')
                ->schema(
                    array_merge(
                        MemberResource::getPersonalInformation(),
                    )
                ),

            // Second Step: Loan Details
            Step::make('Contact Information')
                ->schema(MemberResource::getContactInformation()),

            // Second Step: Loan Details
            Step::make('Employment Information')
                ->schema(MemberResource::getEmploymentInformation()),

            // Third Step: ROD Details
            Step::make('Others')
                ->schema(
                    array_merge(
                        MemberResource::getGovernmentIDs(),
                        MemberResource::getAdditionalInformation(),
                    )
                ),
        ];
    }
}
