<div class="fi-logo fb-brand" aria-label="FamilyBudget">
    <svg class="fb-brand__icon" width="40" height="40" viewBox="0 0 48 48" fill="none" aria-hidden="true">
        <defs>
            <linearGradient id="fbBrandGradient" x1="6" y1="6" x2="42" y2="42" gradientUnits="userSpaceOnUse">
                <stop offset="0" stop-color="#ff8a00" />
                <stop offset="0.55" stop-color="#ff4d6d" />
                <stop offset="1" stop-color="#7b61ff" />
            </linearGradient>
        </defs>

        <rect x="4" y="4" width="40" height="40" rx="12" fill="url(#fbBrandGradient)" />
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
    .fb-brand {
        display: inline-flex;
        align-items: center;
        gap: 0.62rem;
        min-height: 2.5rem;
        white-space: nowrap;
        font-family: Manrope, "Segoe UI", sans-serif;
        line-height: 1;
        user-select: none;
    }

    .fb-brand__icon {
        display: block;
        flex: 0 0 auto;
        min-width: 2.5rem;
        filter: drop-shadow(0 8px 16px rgb(0 0 0 / 18%));
    }

    .fb-brand__text {
        display: inline-flex;
        font-size: 1.2rem;
        font-weight: 800;
        letter-spacing: 0.14px;
    }

    .fb-brand__family {
        color: rgb(15 23 42);
    }

    .fb-brand__budget {
        color: #ff4d6d;
    }

    html.dark .fb-brand__family {
        color: rgb(241 245 249);
    }

    @media (max-width: 640px) {
        .fb-brand {
            gap: 0.5rem;
        }

        .fb-brand__text {
            font-size: 1rem;
        }
    }
</style>
