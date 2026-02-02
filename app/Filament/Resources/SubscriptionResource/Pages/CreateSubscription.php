<?php

namespace App\Filament\Resources\SubscriptionResource\Pages;

use App\Filament\Resources\SubscriptionResource;
use App\Models\Subscription;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Log;
use Filament\Notifications\Notification;
use Illuminate\Validation\ValidationException;

/**
 * CreateSubscription Page
 *
 * Handles the creation of new subscriptions with validation to prevent
 * duplicate active subscriptions for the same member.
 *
 * @package App\Filament\Resources\SubscriptionResource\Pages
 */
class CreateSubscription extends CreateRecord
{
    protected static string $resource = SubscriptionResource::class;

    /**
     * Handle the creation of a new subscription record.
     *
     * This method validates that the member doesn't have an active subscription
     * before creating a new one. An active subscription is defined as one with
     * an expires_at date in the future.
     *
     * @param array $data The form data containing subscription details
     * @return Subscription The newly created subscription instance
     * @throws ValidationException If member already has an active subscription
     */
    protected function handleRecordCreation(array $data): Subscription
    {
        $memberId = $data['member_id'];

        // Validate no active subscription exists
        $this->validateNoActiveSubscription($memberId);

        // Create the subscription
        $subscription = $this->createSubscription($data);

        // Log successful creation
        $this->logSubscriptionCreation($subscription);

        // Send success notification
        $this->sendSuccessNotification($subscription);

        return $subscription;
    }

    /**
     * Validate that the member doesn't have an active subscription.
     *
     * @param int|string $memberId The ID of the member
     * @throws ValidationException If an active subscription exists
     */
    protected function validateNoActiveSubscription(int|string $memberId): void
    {
        $hasActiveSubscription = Subscription::query()
            ->where('member_id', $memberId)
            ->where('expires_at', '>=', now())
            ->exists();

        if ($hasActiveSubscription) {
            Log::warning('Subscription creation blocked: Member already has active subscription', [
                'member_id' => $memberId,
                'attempted_at' => now(),
            ]);

            Notification::make()
                ->title('Subscription Failed')
                ->body('This member already has an active subscription.')
                ->danger()
                ->send();

            // Throw validation exception to halt the creation process
            throw ValidationException::withMessages([
                'member_id' => 'This member already has an active subscription.',
            ]);
        }
    }

    /**
     * Create the subscription record.
     *
     * @param array $data The subscription data
     * @return Subscription The created subscription
     */
    protected function createSubscription(array $data): Subscription
    {
        return Subscription::create($data);
    }

    /**
     * Log the successful creation of a subscription.
     *
     * @param Subscription $subscription The created subscription
     */
    protected function logSubscriptionCreation(Subscription $subscription): void
    {
        Log::info('Subscription created successfully', [
            'subscription_id' => $subscription->id,
            'member_id' => $subscription->member_id,
            'created_at' => $subscription->created_at,
            'expires_at' => $subscription->expires_at,
        ]);
    }

    /**
     * Send a success notification after subscription creation.
     *
     * @param Subscription $subscription The created subscription
     */
    protected function sendSuccessNotification(Subscription $subscription): void
    {
        $memberName = $subscription->member->full_name ?? 'Unknown Member';

        Notification::make()
            ->title('Subscription Created')
            ->body("Subscription for {$memberName} has been created successfully.")
            ->success()
            ->send();
    }

    /**
     * Customize the redirect after creating the subscription.
     *
     * Uncomment and modify if you want custom redirect behavior.
     */
    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    /**
     * Mutate form data before creating the subscription.
     *
     * Use this hook to modify data before it's saved to the database.
     * Example: Add created_by user, calculate expiration dates, etc.
     */
    // protected function mutateFormDataBeforeCreate(array $data): array
    // {
    //     $data['created_by'] = auth()->id();
    //
    //     return $data;
    // }
}
