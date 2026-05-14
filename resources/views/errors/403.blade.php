<x-error-layout
    title="Access Forbidden"
    heading="Access Forbidden"
    badge="🔒 403 Error"
    tagline-heading="You're Not Allowed Here."
    tagline-body="You don't have permission to access this page or resource. If you believe this is a mistake, please contact our support team."
>

    <x-slot name="icon">
        <svg viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
            <rect x="3" y="11" width="18" height="11" rx="2" ry="2"/>
            <path d="M7 11V7a5 5 0 0 1 10 0v4"/>
            <circle cx="12" cy="16" r="1" fill="white"/>
        </svg>
    </x-slot>

    <div class="info-box">
        <p><strong>Access denied.</strong> This area is restricted to authorized users only. You may not have the required role or permission level to view this content.</p>
    </div>

    <ul class="steps-list">
        <li>
            <div class="step-num">1</div>
            <span>Make sure you are logged in with the correct account.</span>
        </li>
        <li>
            <div class="step-num">2</div>
            <span>If you need access, contact your administrator or our support team.</span>
        </li>
        <li>
            <div class="step-num">3</div>
            <span>Return to the home page and navigate from there.</span>
        </li>
    </ul>

    <div class="action-buttons">
        <a href="{{ url('/') }}" class="btn btn-primary">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                <path d="m3 9 9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/>
            </svg>
            Back to Home
        </a>
        <a href="javascript:history.back()" class="btn btn-secondary">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                <path d="m12 19-7-7 7-7"/><path d="M19 12H5"/>
            </svg>
            Go Back
        </a>
    </div>

</x-error-layout>
