<x-error-layout
    title="Server Error"
    heading="Server Error"
    badge="⚠ 500 Error"
    tagline-heading="Something Went Wrong."
    tagline-body="An unexpected error occurred on our server. Our team has been notified and is working to resolve the issue as quickly as possible."
>

    <x-slot name="icon">
        <svg viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
            <path d="M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/>
            <line x1="12" y1="9" x2="12" y2="13"/>
            <line x1="12" y1="17" x2="12.01" y2="17" stroke-width="2.5"/>
        </svg>
    </x-slot>

    <div class="info-box">
        <p><strong>What happened?</strong> Our server encountered an internal error and was unable to complete your request. This is not something you did — it's on our end.</p>
    </div>

    <ul class="steps-list">
        <li>
            <div class="step-num">1</div>
            <span>Try refreshing the page — the issue may be temporary.</span>
        </li>
        <li>
            <div class="step-num">2</div>
            <span>Clear your browser cache and cookies, then try again.</span>
        </li>
        <li>
            <div class="step-num">3</div>
            <span>If the problem persists, contact us through the channels below.</span>
        </li>
    </ul>

    <div class="action-buttons">
        <a href="javascript:location.reload()" class="btn btn-primary">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                <path d="M3 12a9 9 0 1 0 9-9 9.75 9.75 0 0 0-6.74 2.74L3 8"/><path d="M3 3v5h5"/>
            </svg>
            Refresh Page
        </a>
        <a href="{{ url('/') }}" class="btn btn-secondary">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                <path d="m3 9 9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/>
            </svg>
            Home
        </a>
    </div>

</x-error-layout>
