<x-error-layout
    title="System Maintenance"
    heading="System Maintenance"
    badge="⚙ In Progress"
    tagline-heading="We'll Be Back Soon!"
    tagline-body="Our system is currently undergoing scheduled maintenance to improve performance, security, and reliability. We appreciate your patience."
>

    <x-slot name="icon">
        <svg viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
            <path d="M12 2a10 10 0 1 0 10 10"/>
            <path d="M12 6v6l4 2"/>
            <circle cx="19" cy="5" r="3" fill="rgba(255,255,255,0.3)" stroke="white"/>
            <path d="M19 3v2l1 1"/>
        </svg>
    </x-slot>

    <div class="progress-wrap">
        <div class="progress-bar"></div>
    </div>
    <p class="progress-label">Maintenance in progress — estimated completion soon</p>

    <div class="tasks-grid">
        <div class="task-item">
            <div class="task-dot"></div>
            <span class="task-text">System upgrades &amp; performance</span>
        </div>
        <div class="task-item">
            <div class="task-dot"></div>
            <span class="task-text">Security enhancements</span>
        </div>
        <div class="task-item">
            <div class="task-dot"></div>
            <span class="task-text">Database optimization</span>
        </div>
        <div class="task-item">
            <div class="task-dot"></div>
            <span class="task-text">Testing new features</span>
        </div>
    </div>

</x-error-layout>
