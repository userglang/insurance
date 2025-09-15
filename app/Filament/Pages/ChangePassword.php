<?php

namespace App\Filament\Pages;

use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Section;
use Filament\Forms\Form;
use Filament\Pages\Page;
use Filament\Actions\Action;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

class ChangePassword extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-key';
    protected static string $view = 'filament.pages.change-password';
    protected static ?string $title = '';
    protected static ?string $navigationLabel = 'Change Password';
    protected static bool $shouldRegisterNavigation = false;
    protected static ?string $slug = 'change-password';

    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill();
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('Change Your Password')
                    ->description('Please enter your current password and choose a new secure password.')
                    ->icon('heroicon-o-lock-closed')
                    ->schema([
                        TextInput::make('current_password')
                            ->label('Current Password')
                            ->password()
                            ->required()
                            ->revealable()
                            ->rules(['required', 'string'])
                            ->validationAttribute('current password')
                            ->helperText('Enter your existing password to confirm your identity.')
                            ->placeholder('Enter your current password'),

                        TextInput::make('password')
                            ->label('New Password')
                            ->password()
                            ->required()
                            ->rule(Password::min(8)
                                ->mixedCase()
                                ->numbers()
                                ->symbols()
                                ->uncompromised()
                            )
                            ->revealable()
                            ->same('password_confirmation')
                            ->validationAttribute('new password')
                            ->helperText('Must be at least 8 characters with uppercase, lowercase, numbers, and symbols.')
                            ->placeholder('Enter your new password')
                            ->live(onBlur: true),

                        TextInput::make('password_confirmation')
                            ->label('Confirm New Password')
                            ->password()
                            ->revealable()
                            ->required()
                            ->dehydrated(false)
                            ->validationAttribute('password confirmation')
                            ->helperText('Re-enter your new password to confirm.')
                            ->placeholder('Confirm your new password')
                            ->live(onBlur: true),
                    ])
                    ->columns(1)
                    ->collapsible(false),
            ])
            ->statePath('data');
    }

    public function changePassword(): void
    {
        try {
            $data = $this->form->getState();
            $user = Auth::user();

            // Verify current password
            if (!Hash::check($data['current_password'], $user->password)) {
                throw ValidationException::withMessages([
                    'data.current_password' => 'The current password is incorrect.',
                ]);
            }

            // Check if new password is different from current
            if (Hash::check($data['password'], $user->password)) {
                throw ValidationException::withMessages([
                    'data.password' => 'Your new password must be different from your current password.',
                ]);
            }

            // Update the password
            $user->update([
                'password' => Hash::make($data['password']),
                'password_changed_at' => now(), // Optional: track when password was changed
            ]);

            // Clear the form
            $this->form->fill([]);

            Notification::make()
                ->success()
                ->title('Password Changed Successfully!')
                ->body('Your password has been updated securely. You can now use your new password to log in.')
                ->duration(5000)
                ->send();

            // Redirect to main dashboard after a brief delay
            $this->redirect('/main');

        } catch (ValidationException $e) {
            // Re-throw validation exceptions to show field-specific errors
            throw $e;
        } catch (\Exception $e) {
            Notification::make()
                ->danger()
                ->title('Error Changing Password')
                ->body('An unexpected error occurred. Please try again or contact support if the problem persists.')
                ->duration(8000)
                ->send();
        }
    }

    protected function getFormActions(): array
    {
        return [
            Action::make('changePassword')
                ->label('Change Password')
                ->icon('heroicon-o-check')
                ->color('primary')
                ->submit('changePassword'),


            Action::make('logout')
                ->label('Logout')
                ->color('gray')
                ->url(route('filament.main.auth.logout'))
                ->openUrlInNewTab(false),
        ];
    }
}
