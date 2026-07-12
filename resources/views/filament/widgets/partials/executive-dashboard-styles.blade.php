<style>
    .fr-exec-dashboard {
        --fr-bg: #ffffff;
        --fr-soft: #f8fafc;
        --fr-border: #e2e8f0;
        --fr-text: #0f172a;
        --fr-muted: #64748b;
        --fr-primary: #0f766e;
        --fr-primary-soft: rgba(15, 118, 110, 0.10);
        color: var(--fr-text);
    }

    .dark .fr-exec-dashboard {
        --fr-bg: #0f172a;
        --fr-soft: #111827;
        --fr-border: #263247;
        --fr-text: #f8fafc;
        --fr-muted: #94a3b8;
        --fr-primary: #5eead4;
        --fr-primary-soft: rgba(45, 212, 191, 0.12);
    }

    .fr-exec-dashboard *,
    .fr-exec-dashboard *::before,
    .fr-exec-dashboard *::after {
        box-sizing: border-box;
    }

    .fr-dashboard-empty {
        padding: 1.25rem;
        border: 1px dashed var(--fr-border);
        border-radius: 0.9rem;
        color: var(--fr-muted);
        text-align: center;
        font-size: 0.85rem;
    }

    /* Operational alerts */
    .fr-operational-alerts {
        display: grid;
        gap: 1rem;
    }

    .fr-operational-alerts__list {
        display: grid;
        gap: 0.75rem;
    }

    .fr-operational-alert {
        display: grid;
        grid-template-columns: 2.6rem minmax(0, 1fr) auto;
        gap: 0.85rem;
        align-items: center;
        padding: 0.9rem 1rem;
        border: 1px solid var(--fr-border);
        border-radius: 1rem;
        background: var(--fr-soft);
        color: var(--fr-text);
        text-decoration: none;
        transition: transform 150ms ease, border-color 150ms ease, box-shadow 150ms ease;
    }

    .fr-operational-alert:hover {
        transform: translateY(-1px);
        border-color: rgba(15, 118, 110, 0.35);
        box-shadow: 0 10px 24px rgba(15, 23, 42, 0.08);
    }

    .fr-operational-alert__icon {
        display: grid;
        width: 2.6rem;
        height: 2.6rem;
        place-items: center;
        border-radius: 0.85rem;
        background: var(--fr-primary-soft);
        color: var(--fr-primary);
    }

    .fr-operational-alert__icon svg {
        width: 1.25rem;
        height: 1.25rem;
    }

    .fr-operational-alert__content {
        display: grid;
        gap: 0.2rem;
        min-width: 0;
    }

    .fr-operational-alert__content strong {
        color: var(--fr-text);
        font-size: 0.9rem;
        font-weight: 800;
    }

    .fr-operational-alert__content small {
        color: var(--fr-muted);
        font-size: 0.78rem;
        line-height: 1.55;
    }

    .fr-operational-alert__value {
        color: var(--fr-text);
        white-space: nowrap;
        font-size: 0.8rem;
        font-weight: 900;
    }

    .fr-operational-alert--danger {
        border-color: rgba(220, 38, 38, 0.26);
    }

    .fr-operational-alert--danger .fr-operational-alert__icon,
    .fr-operational-alert--danger .fr-operational-alert__value {
        color: #dc2626;
    }

    .fr-operational-alert--danger .fr-operational-alert__icon {
        background: rgba(220, 38, 38, 0.12);
    }

    .fr-operational-alert--warning {
        border-color: rgba(245, 158, 11, 0.30);
    }

    .fr-operational-alert--warning .fr-operational-alert__icon,
    .fr-operational-alert--warning .fr-operational-alert__value {
        color: #d97706;
    }

    .fr-operational-alert--warning .fr-operational-alert__icon {
        background: rgba(245, 158, 11, 0.13);
    }

    .fr-operational-alerts__healthy,
    .fr-dashboard-follow-up-ok {
        display: flex;
        gap: 0.85rem;
        align-items: center;
        padding: 1rem;
        border: 1px solid rgba(22, 163, 74, 0.26);
        border-radius: 1rem;
        background: rgba(22, 163, 74, 0.08);
    }

    .fr-operational-alerts__healthy-icon,
    .fr-dashboard-follow-up-ok > svg {
        width: 2rem;
        height: 2rem;
        flex: 0 0 auto;
        color: #16a34a;
    }

    .fr-operational-alerts__healthy div,
    .fr-dashboard-follow-up-ok div {
        display: grid;
        gap: 0.2rem;
    }

    .fr-operational-alerts__healthy strong,
    .fr-dashboard-follow-up-ok strong {
        color: var(--fr-text);
    }

    .fr-operational-alerts__healthy span,
    .fr-dashboard-follow-up-ok span {
        color: var(--fr-muted);
        font-size: 0.8rem;
    }

    .fr-dashboard-quick-links {
        display: grid;
        gap: 0.65rem;
        padding-top: 0.9rem;
        border-top: 1px solid var(--fr-border);
    }

    .fr-dashboard-quick-links__title {
        color: var(--fr-muted);
        font-size: 0.78rem;
        font-weight: 900;
    }

    .fr-dashboard-quick-links__items {
        display: flex;
        flex-wrap: wrap;
        gap: 0.55rem;
    }

    .fr-dashboard-quick-link {
        display: inline-flex;
        gap: 0.4rem;
        align-items: center;
        padding: 0.55rem 0.75rem;
        border: 1px solid rgba(15, 118, 110, 0.22);
        border-radius: 999px;
        background: var(--fr-primary-soft);
        color: var(--fr-primary);
        text-decoration: none;
        font-size: 0.78rem;
        font-weight: 800;
    }

    .fr-dashboard-quick-link svg {
        width: 1rem;
        height: 1rem;
    }

    /* Rankings */
    .fr-dashboard-rankings {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 1rem;
    }

    .fr-dashboard-ranking-panel {
        overflow: hidden;
        border: 1px solid var(--fr-border);
        border-radius: 1rem;
        background: var(--fr-soft);
    }

    .fr-dashboard-ranking-panel__header {
        display: flex;
        justify-content: space-between;
        gap: 1rem;
        align-items: center;
        padding: 0.9rem 1rem;
        border-bottom: 1px solid var(--fr-border);
        background: var(--fr-bg);
    }

    .fr-dashboard-ranking-panel__header div {
        display: grid;
        gap: 0.15rem;
    }

    .fr-dashboard-ranking-panel__header strong {
        color: var(--fr-text);
        font-size: 0.92rem;
    }

    .fr-dashboard-ranking-panel__header span {
        color: var(--fr-muted);
        font-size: 0.75rem;
    }

    .fr-dashboard-ranking-panel__header a {
        color: var(--fr-primary);
        text-decoration: none;
        white-space: nowrap;
        font-size: 0.76rem;
        font-weight: 900;
    }

    .fr-dashboard-ranking-list {
        display: grid;
    }

    .fr-dashboard-ranking-row {
        display: grid;
        grid-template-columns: 2.2rem minmax(0, 1fr) auto;
        gap: 0.75rem;
        align-items: center;
        padding: 0.85rem 1rem;
        border-bottom: 1px solid var(--fr-border);
        color: var(--fr-text);
        text-decoration: none;
        transition: background-color 150ms ease;
    }

    .fr-dashboard-ranking-row:last-child {
        border-bottom: 0;
    }

    .fr-dashboard-ranking-row:hover {
        background: var(--fr-primary-soft);
    }

    .fr-dashboard-rank {
        display: grid;
        width: 2rem;
        height: 2rem;
        place-items: center;
        border-radius: 0.7rem;
        background: var(--fr-primary-soft);
        color: var(--fr-primary);
        font-size: 0.82rem;
        font-weight: 900;
    }

    .fr-dashboard-ranking-main,
    .fr-dashboard-ranking-metrics {
        display: grid;
        gap: 0.18rem;
        min-width: 0;
    }

    .fr-dashboard-ranking-main strong,
    .fr-dashboard-ranking-metrics strong {
        color: var(--fr-text);
        font-size: 0.84rem;
    }

    .fr-dashboard-ranking-main small,
    .fr-dashboard-ranking-metrics small {
        color: var(--fr-muted);
        font-size: 0.72rem;
        line-height: 1.5;
    }

    .fr-dashboard-ranking-metrics {
        text-align: left;
        white-space: nowrap;
    }

    /* Recent activity */
    .fr-dashboard-activity-list,
    .fr-dashboard-follow-up-list {
        display: grid;
    }

    .fr-dashboard-activity,
    .fr-dashboard-follow-up {
        display: grid;
        grid-template-columns: 2.5rem minmax(0, 1fr) auto;
        gap: 0.8rem;
        align-items: center;
        padding: 0.82rem 0;
        border-bottom: 1px solid var(--fr-border);
        color: var(--fr-text);
        text-decoration: none;
    }

    .fr-dashboard-activity:last-child,
    .fr-dashboard-follow-up:last-child {
        border-bottom: 0;
    }

    .fr-dashboard-activity:hover,
    .fr-dashboard-follow-up:hover {
        background: var(--fr-primary-soft);
    }

    .fr-dashboard-activity__icon,
    .fr-dashboard-follow-up__icon {
        display: grid;
        width: 2.4rem;
        height: 2.4rem;
        place-items: center;
        border-radius: 0.8rem;
        background: var(--fr-primary-soft);
        color: var(--fr-primary);
    }

    .fr-dashboard-activity__icon svg,
    .fr-dashboard-follow-up__icon svg {
        width: 1.15rem;
        height: 1.15rem;
    }

    .fr-dashboard-activity__icon--success {
        background: rgba(22, 163, 74, 0.12);
        color: #16a34a;
    }

    .fr-dashboard-activity__icon--danger {
        background: rgba(220, 38, 38, 0.12);
        color: #dc2626;
    }

    .fr-dashboard-activity__icon--warning {
        background: rgba(245, 158, 11, 0.13);
        color: #d97706;
    }

    .fr-dashboard-activity__icon--info {
        background: rgba(2, 132, 199, 0.12);
        color: #0284c7;
    }

    .fr-dashboard-activity__content,
    .fr-dashboard-activity__meta,
    .fr-dashboard-follow-up__content {
        display: grid;
        gap: 0.18rem;
        min-width: 0;
    }

    .fr-dashboard-activity__content strong,
    .fr-dashboard-activity__meta strong,
    .fr-dashboard-follow-up__content strong {
        color: var(--fr-text);
        font-size: 0.84rem;
    }

    .fr-dashboard-activity__content small,
    .fr-dashboard-activity__meta small,
    .fr-dashboard-follow-up__content small {
        color: var(--fr-muted);
        font-size: 0.72rem;
        line-height: 1.5;
    }

    .fr-dashboard-activity__meta {
        text-align: left;
        white-space: nowrap;
    }

    .fr-dashboard-follow-up__value {
        color: var(--fr-text);
        white-space: nowrap;
        font-size: 0.78rem;
        font-weight: 900;
    }

    .fr-dashboard-follow-up--danger .fr-dashboard-follow-up__icon,
    .fr-dashboard-follow-up--danger .fr-dashboard-follow-up__value {
        color: #dc2626;
    }

    .fr-dashboard-follow-up--danger .fr-dashboard-follow-up__icon {
        background: rgba(220, 38, 38, 0.12);
    }

    .fr-dashboard-follow-up--warning .fr-dashboard-follow-up__icon,
    .fr-dashboard-follow-up--warning .fr-dashboard-follow-up__value {
        color: #d97706;
    }

    .fr-dashboard-follow-up--warning .fr-dashboard-follow-up__icon {
        background: rgba(245, 158, 11, 0.13);
    }

    @media (max-width: 900px) {
        .fr-dashboard-rankings {
            grid-template-columns: 1fr;
        }
    }

    @media (max-width: 640px) {
        .fr-operational-alert,
        .fr-dashboard-ranking-row,
        .fr-dashboard-activity,
        .fr-dashboard-follow-up {
            grid-template-columns: 2.5rem minmax(0, 1fr);
        }

        .fr-operational-alert__value,
        .fr-dashboard-ranking-metrics,
        .fr-dashboard-activity__meta,
        .fr-dashboard-follow-up__value {
            grid-column: 2;
            text-align: right;
        }
    }
</style>
