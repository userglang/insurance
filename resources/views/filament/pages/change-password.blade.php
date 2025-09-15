<x-filament-panels::page>
    <div class="w-full space-y-6">

        {{-- Important Notice --}}
        <div class="bg-gradient-to-r from-amber-50 to-orange-50 border border-amber-200 rounded-xl p-6 shadow-sm">
            <div class="flex items-start space-x-4">
                <div class="flex-shrink-0">
                    <div class="w-10 h-10 bg-amber-100 rounded-full flex items-center justify-center">
                        <x-heroicon-o-exclamation-triangle class="h-6 w-6 text-amber-600" />
                    </div>
                </div>
                <div class="flex-1">
                    <h3 class="text-lg font-semibold text-amber-800 mb-2">
                        Password Change Required
                    </h3>
                    <p class="text-amber-700 mb-4">
                        For security reasons, you must change your default password before accessing the staff panel.
                        Please choose a strong, unique password that you haven't used elsewhere.
                    </p>
                    <div class="bg-amber-100 rounded-lg p-3">
                        <h4 class="font-medium text-amber-800 mb-2">Password Requirements:</h4>
                        <ul class="text-sm text-amber-700 space-y-1">
                            <li class="flex items-center">
                                <x-heroicon-o-check-circle class="h-4 w-4 text-amber-600 mr-2" />
                                At least 8 characters long
                            </li>
                            <li class="flex items-center">
                                <x-heroicon-o-check-circle class="h-4 w-4 text-amber-600 mr-2" />
                                Contains uppercase and lowercase letters
                            </li>
                            <li class="flex items-center">
                                <x-heroicon-o-check-circle class="h-4 w-4 text-amber-600 mr-2" />
                                Includes numbers and symbols
                            </li>
                            <li class="flex items-center">
                                <x-heroicon-o-check-circle class="h-4 w-4 text-amber-600 mr-2" />
                                Not a commonly used password
                            </li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>


        {{-- Password Change Form --}}
        <x-filament-panels::form wire:submit="changePassword">
                {{ $this->form }}
                {{-- Render the form actions properly --}}
                <div class="mt-1 flex justify-end gap-3">
                    <x-filament::actions :actions="$this->getFormActions()" />
                </div>
        </x-filament-panels::form>




        {{-- Help Section --}}
        <div class="bg-blue-50 border border-blue-200 rounded-xl p-6 shadow-sm">
            <div class="flex items-start space-x-4">
                <div class="flex-shrink-0">
                </div>
                <div class="flex-1">
                    <h3 class="text-lg font-semibold text-gray-800 mb-2">
                        Need Help?
                    </h3>
                    <p class="text-blue-500 mb-3">
                        If you're having trouble changing your password or have forgotten your current password,
                        please contact your system administrator for assistance.
                    </p>
                </div>
            </div>
        </div>
    </div>
</x-filament-panels::page>
