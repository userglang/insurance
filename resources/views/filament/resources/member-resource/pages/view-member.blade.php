{{-- resources/views/filament/resources/member/view.blade.php --}}

<x-filament::page>
    {{-- ✅ Authorization should be enforced in controller or route --}}
    {{-- @can('view', $record) --}}

    <div class="col-span-12 lg:col-span-9">
        <div class="space-y-6">
            <div class="bg-white dark:bg-gray-800 shadow dark:shadow-gray-700/50 rounded-xl p-6 border border-gray-200 dark:border-gray-700">

                <h2 class="text-xl font-semibold text-green-600 dark:text-green-400 mb-4">Personal Details</h2>
                <table class="table-auto w-full border border-gray-300 dark:border-gray-50 mb-8">
                    <tbody class="divide-y divide-gray-200 dark:divide-gray-700">

                        <tr class="border-b border-gray-200 dark:border-gray-700">
                            <td class="font-bold py-3 px-4">Name:</td>
                            <td class="text-red-600 py-3 px-4" colspan="3">{{ $record->full_name }}</td>
                            <td class="font-bold py-3 px-4">Branch:</td>
                            <td class="text-red-600 py-3 px-4">{{ $record->branch->branch_name }}</td>
                        </tr>

                        <tr class="border-b border-gray-200 dark:border-gray-700">
                            <td class="font-bold py-3 px-4">Address:</td>
                            <td class="text-red-600 py-3 px-4" colspan="5">
                                {{ $record->house_number }} {{ $record->street }} {{ $record->barangay }}, {{ $record->city }}, {{ $record->province }}, {{ $record->zipcode }}
                            </td>
                        </tr>

                        <tr class="border-b border-gray-200 dark:border-gray-700">
                            <td class="font-bold py-3 px-4">Place of Birth:</td>
                            <td class="text-red-600 py-3 px-4" colspan="5">{{ $record->birth_place }}</td>
                        </tr>

                        <tr class="border-b border-gray-200 dark:border-gray-700">
                            <td class="font-bold py-3 px-4">Date of Birth:</td>
                            <td class="text-red-600 py-3 px-4">
                                {{ \Carbon\Carbon::parse($record->birth_date)->format('F j, Y') }}
                            </td>
                            <td class="font-bold py-3 px-4">Gender:</td>
                            <td class="text-red-600 py-3 px-4">{{ $record->gender }}</td>
                        </tr>

                        <tr class="border-b border-gray-200 dark:border-gray-700">
                            <td class="font-bold py-3 px-4">Civil Status:</td>
                            <td class="text-red-600 py-3 px-4">{{ $record->marital_status }}</td>
                            <td class="font-bold py-3 px-4">Contact Number:</td>
                            <td class="text-red-600 py-3 px-4" colspan="3">{{ $record->contact_number }}</td>
                        </tr>

                        <tr class="border-b border-gray-200 dark:border-gray-700">
                            <td class="font-bold py-3 px-4">TIN:</td>
                            <td class="text-red-600 py-3 px-4">
                                {{ substr($record->tin, 0, 3) . '-•••-•••' }}
                            </td>
                            <td class="font-bold py-3 px-4">SSS / GSIS:</td>
                            <td class="text-red-600 py-3 px-4" colspan="3">
                                {{ substr($record->{'sss_gsis'}, 0, 4) . '-••••' }}
                            </td>
                        </tr>

                        @if($record->profStatus !== 'Active')
                            <tr class="border-b border-gray-200 dark:border-gray-700">
                                <td class="font-bold py-3 px-4">Remarks:</td>
                                <td class="text-red-600 py-3 px-4" colspan="5">
                                    {{ strip_tags($record->remark) }}
                                </td>
                            </tr>
                        @endif
                    </tbody>
                </table>

                <h2 class="text-xl font-semibold text-green-600 dark:text-green-400 mb-4 mt-4"><br>Employment Details</h2>
                <table class="table-auto w-full border border-gray-300 dark:border-gray-100">
                    <tbody class="divide-y divide-gray-200 dark:divide-gray-700">

                        <tr class="border-b border-gray-200 dark:border-gray-700">
                            <td class="font-bold py-3 px-4">Occupation:</td>
                            <td class="text-red-600 py-3 px-4" colspan="2">{{ $record->occupation }}</td>
                            <td class="font-bold py-3 px-4">Office Number:</td>
                            <td class="text-red-600 py-3 px-4" colspan="2">{{ $record->office_contact_number }}</td>
                        </tr>

                        <tr class="border-b border-gray-200 dark:border-gray-700">
                            <td class="font-bold py-3 px-4">Name of Employer:</td>
                            <td class="text-red-600 py-3 px-4" colspan="2">{{ $record->name_of_employer }}</td>
                            <td class="font-bold py-3 px-4">Employment Status:</td>
                            <td class="text-red-600 py-3 px-4" colspan="2">{{ $record->employment_status }}</td>
                        </tr>

                        <tr>
                            <td class="font-bold py-3 px-4">Office Address:</td>
                            <td class="text-red-600 py-3 px-4" colspan="2">{{ $record->office_address }}</td>
                            <td class="font-bold py-3 px-4">Email Address:</td>
                            <td class="text-red-600 py-3 px-4" colspan="2">{{ $record->email }}</td>
                        </tr>

                    </tbody>
                </table>
            </div>
        </div>
    </div>

    {{-- ✅ Subscriptions Relation Manager --}}
    @if($record->is_active)
        <x-filament::section>
            <x-slot name="header">Subscriptions</x-slot>

            @livewire(
                \App\Filament\Resources\MemberResource\RelationManagers\SubscriptionsRelationManager::class,
                [
                    'ownerRecord' => $record,
                    'pageClass' => \App\Filament\Resources\MemberResource\Pages\ViewMember::class,
                ],
                key('subscriptions-' . $record->getKey())
            )
        </x-filament::section>
    @endif

    {{-- @endcan --}}
</x-filament::page>
