@php
    $gradientId = 'fbBrandGradient-' . uniqid();
@endphp

<div class="fi-logo fb-brand" aria-label="FamilyBudget">
    <svg class="fb-brand__icon" viewBox="0 0 48 48" fill="none" aria-hidden="true">
        <defs>
            <linearGradient id="{{ $gradientId }}" x1="6" y1="6" x2="42" y2="42" gradientUnits="userSpaceOnUse">
                <stop offset="0" stop-color="#ff8a00" />
                <stop offset="0.55" stop-color="#ff4d6d" />
                <stop offset="1" stop-color="#7b61ff" />
            </linearGradient>
        </defs>

        <rect x="4" y="4" width="40" height="40" rx="12" fill="url(#{{ $gradientId }})" />
        <path d="M14 23L24 14L34 23V34H14V23Z" fill="white" />
        <rect x="22" y="27" width="4" height="7" rx="1.2" fill="#ff4d6d" />
        <circle cx="34.4" cy="34.4" r="6.2" fill="white" />
        <text x="34.4" y="37.1" text-anchor="middle" font-size="8.6" font-weight="800" fill="#7b61ff" font-family="Manrope, Segoe UI, sans-serif">$</text>
    </svg>

    <span class="fb-brand__text">
        <span class="fb-brand__family">Family</span><span class="fb-brand__budget">Budget</span>
    </span>
</div>

<style>
    .fi-sidebar-header-logo-ctn,
    .fi-sidebar-header-logo-ctn > a {
        background: transparent !important;
        border: 0 !important;
        box-shadow: none !important;
        padding: 0 !important;
        border-radius: 0 !important;
        max-width: none !important;
    }

    .fi-logo.fb-brand {
        display: inline-flex !important;
        align-items: center !important;
        gap: clamp(.36rem, 1.8vw, .62rem) !important;
        white-space: nowrap !important;
        line-height: 1;
        max-width: max-content !important;
        background: transparent !important;
        border: 0 !important;
        box-shadow: none !important;
        padding: 0 !important;
        border-radius: 0 !important;
        font-family: Manrope, "Segoe UI", sans-serif;
    }

    .fi-logo.fb-brand .fb-brand__icon {
        display: block !important;
        flex: 0 0 auto !important;
        width: clamp(32px, 8vw, 40px) !important;
        height: clamp(32px, 8vw, 40px) !important;
        min-width: clamp(32px, 8vw, 40px) !important;
        max-width: none !important;
    }

    .fi-logo.fb-brand .fb-brand__text {
        display: inline-flex !important;
        visibility: visible !important;
        opacity: 1 !important;
        font-size: clamp(.9rem, 3.8vw, 1.2rem) !important;
        font-weight: 800 !important;
        letter-spacing: 0 !important;
    }

    .fi-logo.fb-brand .fb-brand__family {
        color: rgb(15 23 42);
    }

    .dark .fi-logo.fb-brand .fb-brand__family {
        color: white !important;
    }

    .fi-logo.fb-brand .fb-brand__budget {
        color: #ff4d6d !important;
    }

    @media (max-width: 400px) {
        .fi-logo.fb-brand .fb-brand__icon {
            width: 32px !important;
            height: 32px !important;
            min-width: 32px !important;
        }

        .fi-logo.fb-brand .fb-brand__text {
            font-size: .9rem !important;
        }
    }
</style>
