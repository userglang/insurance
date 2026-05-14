<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $title }} – Oro Integrated Cooperative</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@700&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">

    <style>
    :root {
        --green-deep:   #064e3b;
        --green-mid:    #059669;
        --green-bright: #10b981;
        --green-soft:   #d1fae5;
        --green-xsoft:  #ecfdf5;
        --text-dark:    #0f1f17;
        --text-mid:     #374151;
        --text-light:   #6b7280;
        --white:        #ffffff;
        --border:       rgba(16,185,129,0.18);
    }

    *, *::before, *::after { margin:0; padding:0; box-sizing:border-box; }

    body {
        font-family: 'DM Sans', sans-serif;
        background: #f0fdf4;
        min-height: 100vh;
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 24px;
        position: relative;
        overflow-x: hidden;
    }

    body::before, body::after {
        content: '';
        position: fixed;
        border-radius: 50%;
        filter: blur(80px);
        opacity: 0.35;
        pointer-events: none;
        z-index: 0;
    }
    body::before {
        width: 500px; height: 500px;
        background: radial-gradient(circle, #6ee7b7, transparent);
        top: -120px; left: -150px;
        animation: blobFloat 8s ease-in-out infinite alternate;
    }
    body::after {
        width: 400px; height: 400px;
        background: radial-gradient(circle, #a7f3d0, transparent);
        bottom: -100px; right: -100px;
        animation: blobFloat 10s ease-in-out infinite alternate-reverse;
    }
    @keyframes blobFloat {
        from { transform: translate(0,0) scale(1); }
        to   { transform: translate(30px,20px) scale(1.08); }
    }

    .card {
        position: relative;
        z-index: 1;
        width: 100%;
        max-width: 640px;
        background: rgba(255,255,255,0.92);
        backdrop-filter: blur(20px);
        -webkit-backdrop-filter: blur(20px);
        border-radius: 28px;
        border: 1px solid rgba(16,185,129,0.22);
        box-shadow: 0 8px 40px rgba(5,150,105,0.12), 0 2px 8px rgba(0,0,0,0.06);
        overflow: hidden;
        animation: cardIn 0.7s cubic-bezier(.22,1,.36,1) both;
    }
    @keyframes cardIn {
        from { opacity:0; transform: translateY(36px) scale(0.97); }
        to   { opacity:1; transform: translateY(0) scale(1); }
    }

    /* ── Header ── */
    .header {
        background: linear-gradient(145deg, var(--green-deep) 0%, var(--green-mid) 100%);
        padding: 44px 32px 36px;
        text-align: center;
        position: relative;
        overflow: hidden;
    }
    .header::after {
        content: '';
        position: absolute;
        inset: 0;
        background: url("data:image/svg+xml,%3Csvg width='60' height='60' viewBox='0 0 60 60' xmlns='http://www.w3.org/2000/svg'%3E%3Cg fill='none' fill-rule='evenodd'%3E%3Cg fill='%23ffffff' fill-opacity='0.04'%3E%3Cpath d='M36 34v-4h-2v4h-4v2h4v4h2v-4h4v-2h-4zm0-30V0h-2v4h-4v2h4v4h2V6h4V4h-4zM6 34v-4H4v4H0v2h4v4h2v-4h4v-2H6zM6 4V0H4v4H0v2h4v4h2V6h4V4H6z'/%3E%3C/g%3E%3C/g%3E%3C/svg%3E");
    }
    .header-icon {
        width: 72px; height: 72px;
        background: rgba(255,255,255,0.15);
        border: 2px solid rgba(255,255,255,0.3);
        border-radius: 20px;
        display: flex; align-items: center; justify-content: center;
        margin: 0 auto 20px;
        position: relative; z-index: 1;
        animation: iconPulse 3s ease-in-out infinite;
    }
    @keyframes iconPulse {
        0%,100% { box-shadow: 0 0 0 0 rgba(255,255,255,0.3); }
        50%      { box-shadow: 0 0 0 12px rgba(255,255,255,0); }
    }
    .header-icon svg { width: 36px; height: 36px; }
    .header h1 {
        font-family: 'Playfair Display', serif;
        font-size: 30px;
        color: white;
        letter-spacing: -0.3px;
        position: relative; z-index: 1;
    }
    .header p {
        font-size: 14px;
        color: rgba(255,255,255,0.75);
        margin-top: 6px;
        font-weight: 300;
        letter-spacing: 0.5px;
        position: relative; z-index: 1;
    }
    .badge {
        display: inline-block;
        margin-top: 16px;
        background: rgba(255,255,255,0.18);
        border: 1px solid rgba(255,255,255,0.28);
        color: #a7f3d0;
        font-size: 12px;
        font-weight: 600;
        letter-spacing: 1.2px;
        text-transform: uppercase;
        padding: 5px 14px;
        border-radius: 50px;
        position: relative; z-index: 1;
    }

    /* ── Body ── */
    .body { padding: 36px 32px; }

    .tagline {
        text-align: center;
        margin-bottom: 28px;
    }
    .tagline h2 {
        font-family: 'Playfair Display', serif;
        font-size: 24px;
        color: var(--text-dark);
        margin-bottom: 10px;
    }
    .tagline p {
        font-size: 15px;
        color: var(--text-mid);
        line-height: 1.7;
        max-width: 460px;
        margin: 0 auto;
    }

    /* ── Progress bar (503) ── */
    .progress-wrap {
        background: #e5e7eb;
        border-radius: 50px;
        height: 6px;
        margin: 24px 0;
        overflow: hidden;
    }
    .progress-bar {
        height: 100%;
        width: 65%;
        background: linear-gradient(90deg, var(--green-mid), var(--green-bright));
        border-radius: 50px;
        animation: progAnim 2.5s ease-in-out infinite alternate;
    }
    @keyframes progAnim {
        from { width: 55%; }
        to   { width: 80%; }
    }
    .progress-label {
        text-align: center;
        font-size: 12px;
        color: var(--text-light);
        margin-top: -8px;
        margin-bottom: 28px;
        font-weight: 500;
    }

    /* ── Tasks grid (503) ── */
    .tasks-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 12px;
        margin-bottom: 32px;
    }
    .task-item {
        display: flex;
        align-items: center;
        gap: 12px;
        background: var(--green-xsoft);
        border: 1px solid var(--border);
        border-radius: 14px;
        padding: 14px 16px;
        animation: itemIn 0.5s ease both;
    }
    .task-item:nth-child(1) { animation-delay: 0.1s; }
    .task-item:nth-child(2) { animation-delay: 0.2s; }
    .task-item:nth-child(3) { animation-delay: 0.3s; }
    .task-item:nth-child(4) { animation-delay: 0.4s; }
    @keyframes itemIn {
        from { opacity:0; transform: translateY(10px); }
        to   { opacity:1; transform: translateY(0); }
    }
    .task-dot {
        width: 8px; height: 8px;
        border-radius: 50%;
        background: var(--green-bright);
        flex-shrink: 0;
        animation: dotBlink 1.8s ease-in-out infinite;
    }
    .task-item:nth-child(2) .task-dot { animation-delay: 0.4s; }
    .task-item:nth-child(3) .task-dot { animation-delay: 0.8s; }
    .task-item:nth-child(4) .task-dot { animation-delay: 1.2s; }
    @keyframes dotBlink {
        0%,100% { opacity:1; }
        50%      { opacity:0.3; }
    }
    .task-text { font-size: 13px; color: var(--text-mid); font-weight: 500; line-height: 1.3; }

    /* ── Error code display ── */
    .error-code {
        text-align: center;
        margin-bottom: 28px;
    }
    .error-code .code-number {
        font-family: 'Playfair Display', serif;
        font-size: 84px;
        line-height: 1;
        background: linear-gradient(135deg, var(--green-deep), var(--green-bright));
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
        background-clip: text;
        letter-spacing: -4px;
        animation: codeIn 0.6s cubic-bezier(.22,1,.36,1) both;
    }
    @keyframes codeIn {
        from { opacity:0; transform: scale(0.75); }
        to   { opacity:1; transform: scale(1); }
    }

    /* ── Action buttons ── */
    .action-buttons {
        display: flex;
        gap: 10px;
        justify-content: center;
        margin-bottom: 32px;
        flex-wrap: wrap;
    }
    .btn {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        padding: 12px 24px;
        border-radius: 50px;
        font-size: 14px;
        font-weight: 600;
        text-decoration: none;
        transition: all 0.25s ease;
        border: none;
        cursor: pointer;
    }
    .btn-primary {
        background: linear-gradient(135deg, var(--green-deep), var(--green-mid));
        color: white;
        box-shadow: 0 4px 14px rgba(5,150,105,0.3);
    }
    .btn-primary:hover {
        transform: translateY(-2px);
        box-shadow: 0 6px 20px rgba(5,150,105,0.4);
        color: white;
    }
    .btn-secondary {
        background: var(--green-xsoft);
        color: var(--green-deep);
        border: 1px solid var(--border);
    }
    .btn-secondary:hover {
        background: var(--green-soft);
        transform: translateY(-2px);
        color: var(--green-deep);
    }

    /* ── Info box ── */
    .info-box {
        background: var(--green-xsoft);
        border: 1px solid var(--border);
        border-left: 4px solid var(--green-bright);
        border-radius: 14px;
        padding: 16px 20px;
        margin-bottom: 28px;
        animation: itemIn 0.5s ease 0.2s both;
    }
    .info-box p { font-size: 13px; color: var(--text-mid); line-height: 1.65; }
    .info-box strong { color: var(--green-deep); font-weight: 600; }

    /* ── Steps list ── */
    .steps-list {
        list-style: none;
        margin-bottom: 32px;
    }
    .steps-list li {
        display: flex;
        align-items: flex-start;
        gap: 14px;
        padding: 13px 0;
        border-bottom: 1px solid #f3f4f6;
        font-size: 14px;
        color: var(--text-mid);
        line-height: 1.5;
        animation: itemIn 0.5s ease both;
    }
    .steps-list li:nth-child(1) { animation-delay: 0.1s; }
    .steps-list li:nth-child(2) { animation-delay: 0.2s; }
    .steps-list li:nth-child(3) { animation-delay: 0.3s; }
    .steps-list li:last-child { border-bottom: none; }
    .step-num {
        width: 26px; height: 26px;
        background: linear-gradient(135deg, var(--green-deep), var(--green-mid));
        color: white;
        border-radius: 50%;
        display: flex; align-items: center; justify-content: center;
        font-size: 12px;
        font-weight: 700;
        flex-shrink: 0;
        margin-top: 1px;
    }

    /* ── Countdown (429) ── */
    .countdown-wrap {
        text-align: center;
        margin-bottom: 28px;
    }
    .countdown-ring {
        display: inline-flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        width: 100px; height: 100px;
        border-radius: 50%;
        background: var(--green-xsoft);
        border: 3px solid var(--green-bright);
        margin-bottom: 12px;
        animation: ringPulse 1s ease-in-out infinite;
    }
    @keyframes ringPulse {
        0%,100% { box-shadow: 0 0 0 0 rgba(16,185,129,0.3); }
        50%      { box-shadow: 0 0 0 10px rgba(16,185,129,0); }
    }
    .countdown-ring .count-num {
        font-family: 'Playfair Display', serif;
        font-size: 32px;
        color: var(--green-deep);
        line-height: 1;
    }
    .countdown-ring .count-unit {
        font-size: 11px;
        color: var(--text-light);
        text-transform: uppercase;
        letter-spacing: 0.5px;
        font-weight: 600;
    }
    .countdown-label {
        font-size: 13px;
        color: var(--text-light);
    }

    /* ── Divider ── */
    .divider {
        display: flex; align-items: center; gap: 12px;
        margin: 8px 0 24px;
    }
    .divider::before, .divider::after {
        content: ''; flex: 1; height: 1px;
        background: linear-gradient(90deg, transparent, #d1d5db, transparent);
    }
    .divider span { font-size: 13px; color: var(--text-light); white-space: nowrap; font-weight: 500; }

    /* ── Contact cards ── */
    .contacts { display: grid; gap: 10px; }
    .contact-card {
        display: flex; align-items: center; gap: 16px;
        padding: 16px 18px;
        background: #fff;
        border: 1px solid #e5e7eb;
        border-radius: 16px;
        text-decoration: none;
        transition: all 0.25s ease;
        animation: itemIn 0.5s ease both;
    }
    .contact-card:nth-child(1) { animation-delay: 0.2s; }
    .contact-card:nth-child(2) { animation-delay: 0.3s; }
    .contact-card:nth-child(3) { animation-delay: 0.4s; }
    .contact-card:hover {
        background: var(--green-xsoft);
        border-color: var(--green-bright);
        transform: translateX(4px);
        box-shadow: 0 4px 16px rgba(16,185,129,0.12);
    }
    .contact-icon {
        width: 44px; height: 44px;
        background: linear-gradient(135deg, var(--green-deep), var(--green-mid));
        border-radius: 12px;
        display: flex; align-items: center; justify-content: center;
        flex-shrink: 0;
        transition: transform 0.25s ease;
    }
    .contact-card:hover .contact-icon { transform: scale(1.08) rotate(-3deg); }
    .contact-icon svg { width: 20px; height: 20px; fill: white; }
    .contact-text strong {
        display: block; font-size: 14px;
        color: var(--text-dark); font-weight: 600; margin-bottom: 2px;
    }
    .contact-text span { font-size: 13px; color: var(--green-mid); font-weight: 400; }
    .contact-arrow { margin-left: auto; color: #d1d5db; font-size: 18px; transition: all 0.25s ease; }
    .contact-card:hover .contact-arrow { color: var(--green-bright); transform: translateX(3px); }

    /* ── Footer ── */
    .footer {
        background: #f9fafb;
        border-top: 1px solid #e5e7eb;
        padding: 22px 32px;
        text-align: center;
    }
    .footer-brand {
        font-family: Impact, Haettenschweiler, 'Arial Black', sans-serif;
        font-size: 15px;
        color: var(--text-dark);
        margin-bottom: 4px;
    }
    .footer-tagline {
        font-size: 12px;
        color: var(--text-light);
        letter-spacing: 0.8px;
        text-transform: uppercase;
        margin-bottom: 14px;
    }
    .footer-hours {
        display: inline-flex;
        gap: 20px;
        background: white;
        border: 1px solid #e5e7eb;
        border-radius: 12px;
        padding: 10px 20px;
    }
    .hour-item { font-size: 12px; color: var(--text-mid); }
    .hour-item strong {
        display: block; font-size: 11px; color: var(--text-light);
        font-weight: 500; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 2px;
    }

    @media (max-width: 520px) {
        .body    { padding: 28px 20px; }
        .header  { padding: 36px 20px 28px; }
        .footer  { padding: 20px; }
        .tasks-grid { grid-template-columns: 1fr; }
        .footer-hours { flex-direction: column; gap: 8px; text-align: left; }
        .error-code .code-number { font-size: 64px; }
        .action-buttons { flex-direction: column; align-items: center; }
        .btn { width: 100%; justify-content: center; }
    }
    </style>
</head>
<body>
<div class="card">

    <div class="header">
        <div class="header-icon">{{ $icon }}</div>
        <h1>{{ $heading }}</h1>
        <p>Oro Integrated Cooperative Registration System</p>
        <div class="badge">{{ $badge }}</div>
    </div>

    <div class="body">

        <div class="tagline">
            <h2>{{ $taglineHeading }}</h2>
            <p>{{ $taglineBody }}</p>
        </div>

        {{ $slot }}

        <div class="divider"><span>Need Assistance?</span></div>

        <div class="contacts">

            <a class="contact-card" href="https://www.facebook.com/taranasaOIC/" target="_blank">
                <div class="contact-icon">
                    <svg viewBox="0 0 24 24"><path d="M22 12a10 10 0 10-11.63 9.87v-6.99h-2.34v-2.88h2.34V9.84c0-2.31 1.37-3.59 3.48-3.59.99 0 2.03.18 2.03.18v2.23h-1.14c-1.13 0-1.48.7-1.48 1.42v1.7h2.52l-.4 2.88h-2.12v6.99A10 10 0 0022 12z"/></svg>
                </div>
                <div class="contact-text">
                    <strong>Facebook</strong>
                    <span>Visit our page for updates</span>
                </div>
                <span class="contact-arrow">›</span>
            </a>

            <a class="contact-card" href="https://www.instagram.com/taranasaoic/" target="_blank">
                <div class="contact-icon">
                    <svg viewBox="0 0 24 24"><path d="M7 2C4.243 2 2 4.243 2 7v10c0 2.757 2.243 5 5 5h10c2.757 0 5-2.243 5-5V7c0-2.757-2.243-5-5-5H7zm5 5a5 5 0 100 10 5 5 0 000-10zm0 2a3 3 0 110 6 3 3 0 010-6zm4.5-2.5a1.5 1.5 0 100 3 1.5 1.5 0 000-3z"/></svg>
                </div>
                <div class="contact-text">
                    <strong>Instagram</strong>
                    <span>Follow us @taranasaoic</span>
                </div>
                <span class="contact-arrow">›</span>
            </a>

            <a class="contact-card" href="https://www.orointegrated.coop/" target="_blank">
                <div class="contact-icon">
                    <svg viewBox="0 0 24 24"><path d="M12 2a10 10 0 100 20A10 10 0 0012 2zm0 2c.93 0 1.96.72 2.8 2H9.2c.84-1.28 1.87-2 2.8-2zm-3.7.72A9.97 9.97 0 004.14 9H7.1a14.3 14.3 0 011.2-4.28zM4 12c0-.68.07-1.35.2-2h3.3a16.1 16.1 0 000 4H4.2A9.1 9.1 0 014 12zm.14 3h3.01a14.3 14.3 0 001.15 4.28A9.97 9.97 0 014.14 15zM12 20c-.93 0-1.96-.72-2.8-2h5.6c-.84 1.28-1.87 2-2.8 2zm3.7-.72A14.3 14.3 0 0016.9 15h2.96a9.97 9.97 0 01-6.16 4.28zM9.2 15h5.6a12.1 12.1 0 01-1.38 3.5A12.1 12.1 0 019.2 15zm-.3-2a13.9 13.9 0 010-2h6.2a13.9 13.9 0 010 2H8.9zM14.8 9H9.2A12.1 12.1 0 0112 5.5c1.02 1 1.82 2.2 2.8 3.5zm1.1 0a14.3 14.3 0 011.2-4.28A9.97 9.97 0 0119.86 9H15.9zm.5 6h3a9.97 9.97 0 01-6.16 4.28A14.3 14.3 0 0016.4 15zm.4-2a16.1 16.1 0 000-4h3.3a9.1 9.1 0 010 4h-3.3z"/></svg>
                </div>
                <div class="contact-text">
                    <strong>Website</strong>
                    <span>www.orointegrated.coop</span>
                </div>
                <span class="contact-arrow">›</span>
            </a>

        </div>
    </div>

    <div class="footer">
        <div class="footer-brand">Oro Integrated Cooperative</div>
        <div class="footer-tagline">Where financial freedom begins!</div>
        <div class="footer-hours">
            <div class="hour-item">
                <strong>Mon – Fri</strong>
                8:00 AM – 3:30 PM
            </div>
            <div class="hour-item">
                <strong>Saturday</strong>
                8:00 AM – 10:30 AM
            </div>
        </div>
    </div>

</div>
</body>
</html>
