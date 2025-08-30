<?php
// app/Services/SubscriptionPdfService.php

namespace App\Services;

use App\Models\Member;
use Barryvdh\DomPDF\Facade\Pdf;

class SubscriptionPdfService
{
    public function generate(Member $member): \Barryvdh\DomPDF\PDF
    {
        $subscriptions = $member->subscriptions()
            ->with(['insurance', 'productAccount'])
            ->get();

        return Pdf::loadView('pdf.subscriptions', [
            'member' => $member,
            'subscriptions' => $subscriptions,
        ])->setPaper('a4', 'portrait');
    }
}
