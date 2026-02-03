<?php

use Illuminate\Http\Request;
use App\Models\Member;
use App\Services\MemberPrintPDFService;
use Illuminate\Support\Facades\Route;
use \Illuminate\Support\Facades\Storage;

Route::get('/', function () {
    return redirect()->route('filament.main.auth.login');
});

Route::get('/member/print/{member}', function (Member $member) {
    $subscription = $member->subscriptions()->latest('activated_at')->first();

    $pdfService = new MemberPrintPDFService($member, $subscription); // ✅ pass model, not array
    $pdfService->generatePDF();
    exit;
})->name('member.print');

Route::get('/download-import-result', function (Request $request) {
    $path = $request->query('path');

    if (!$path || str_contains($path, '..') || !Storage::disk('local')->exists($path)) {
        abort(404);
    }

    return Storage::disk('local')->download($path);
})->name('download.import.result');
