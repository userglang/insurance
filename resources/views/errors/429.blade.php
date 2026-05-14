<x-error-layout
    title="Too Many Requests"
    heading="Slow Down!"
    badge="🚦 429 Error"
    tagline-heading="Too Many Requests."
    tagline-body="You've sent too many requests in a short period of time. Please wait a moment before trying again. This helps us keep the system stable for everyone."
>

    <x-slot name="icon">
        <svg viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
            <rect x="2" y="7" width="20" height="14" rx="2" ry="2"/>
            <path d="M16 7V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v2"/>
            <line x1="12" y1="12" x2="12" y2="16"/>
            <line x1="12" y1="16" x2="12.01" y2="16" stroke-width="2.5"/>
        </svg>
    </x-slot>

    <div class="countdown-wrap">
        <div class="countdown-ring" id="countdown-ring">
            <span class="count-num" id="countdown-num">60</span>
            <span class="count-unit">seconds</span>
        </div>
        <p class="countdown-label">Please wait before trying again</p>
    </div>

    <div class="info-box">
        <p><strong>Rate limit reached.</strong> Our system limits the number of requests to prevent abuse and ensure fair access for all members. The countdown above will let you know when you can try again.</p>
    </div>

    <div class="action-buttons">
        <a href="{{ url('/') }}" class="btn btn-primary" id="retry-btn" style="pointer-events:none; opacity:0.5;">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                <path d="M3 12a9 9 0 1 0 9-9 9.75 9.75 0 0 0-6.74 2.74L3 8"/><path d="M3 3v5h5"/>
            </svg>
            <span id="retry-label">Wait 60s to retry…</span>
        </a>
    </div>

    <script>
    (function () {
        let seconds = 60;
        const numEl   = document.getElementById('countdown-num');
        const labelEl = document.getElementById('retry-label');
        const btnEl   = document.getElementById('retry-btn');
        const ringEl  = document.getElementById('countdown-ring');

        const timer = setInterval(function () {
            seconds--;
            numEl.textContent = seconds;
            labelEl.textContent = seconds > 0 ? 'Wait ' + seconds + 's to retry…' : 'Try Again';

            if (seconds <= 0) {
                clearInterval(timer);
                btnEl.style.pointerEvents = 'auto';
                btnEl.style.opacity = '1';
                ringEl.style.borderColor = 'var(--green-bright)';
                ringEl.style.background = 'var(--green-soft)';
                numEl.textContent = '✓';
            }
        }, 1000);
    })();
    </script>

</x-error-layout>
