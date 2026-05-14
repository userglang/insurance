<x-error-layout
    title="Page Expired"
    heading="Page Expired"
    badge="⏱ 419 Error"
    tagline-heading="Your Session Has Expired."
    tagline-body="For your security, this page has expired due to inactivity or an invalid session token. Please go back and try your action again."
>

    <x-slot name="icon">
        <svg viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
            <circle cx="12" cy="12" r="10"/>
            <polyline points="12 6 12 12 16 14"/>
            <path d="M4.93 4.93l14.14 14.14" stroke-width="1.4" stroke-dasharray="2 3"/>
        </svg>
    </x-slot>

    <div class="info-box">
        <p><strong>Why did this happen?</strong> Your form or page session token has expired. This is a security feature that protects your account from unauthorized submissions.</p>
    </div>

    <ul class="steps-list">
        <li>
            <div class="step-num">1</div>
            <span>Go back to the previous page using your browser's back button.</span>
        </li>
        <li>
            <div class="step-num">2</div>
            <span>Refresh the page to get a new session token.</span>
        </li>
        <li>
            <div class="step-num">3</div>
            <span>Fill out and submit the form again within the session window.</span>
        </li>
    </ul>

    <div class="action-buttons">
        <a href="javascript:history.back()" class="btn btn-primary">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                <path d="m12 19-7-7 7-7"/><path d="M19 12H5"/>
            </svg>
            Go Back
        </a>
        <a href="{{ url('/') }}" class="btn btn-secondary">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                <path d="m3 9 9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/>
            </svg>
            Home
        </a>
    </div>

</x-error-layout>
