<x-error-layout
    title="Page Not Found"
    heading="Page Not Found"
    badge="✕ 404 Error"
    tagline-heading="Oops! Wrong Turn."
    tagline-body="The page you're looking for doesn't exist, may have been moved, or the link might be broken. Please check the URL and try again."
>

    <x-slot name="icon">
        <svg viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
            <circle cx="11" cy="11" r="8"/>
            <path d="m21 21-4.35-4.35"/>
            <path d="M9 9l4 4M13 9l-4 4" stroke-width="2"/>
        </svg>
    </x-slot>

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
