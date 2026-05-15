const STAGE_LABELS = {
    GROUP_STAGE: 'Group Stage',
    ROUND_OF_16: 'Round of 16',
    QUARTER_FINAL: 'Quarter Final',
    SEMI_FINAL: 'Semi Final',
    FINAL: 'Final',
};

export function modeLabel(mode) {
    return mode === 'national_league' ? 'National League' : 'Champions League';
}

export function stageLabel(stage) {
    return STAGE_LABELS[stage] ?? 'Group Stage';
}

export function weekLabel(session) {
    if (!session || session.status === 'finished') {
        return '';
    }

    if (session.mode === 'national_league') {
        const total = totalWeeks(session);

        return session.current_week ? `Week ${session.current_week} / ${total}` : `Week 0 / ${total}`;
    }

    if (session.current_stage === 'FINAL') {
        return 'Final';
    }

    if (['ROUND_OF_16', 'QUARTER_FINAL', 'SEMI_FINAL'].includes(session.current_stage)) {
        return `Leg ${session.current_week % 2 === 0 ? 2 : 1}`;
    }

    return session.current_week ? `Week ${session.current_week}` : 'Not started';
}

function totalWeeks(session) {
    if (!session) {
        return 0;
    }

    if (session.mode === 'national_league') {
        return Math.max(((session.teams_count ?? 0) - 1) * 2, 1);
    }

    if (session.current_stage === 'GROUP_STAGE') {
        return 6;
    }

    if (['ROUND_OF_16', 'QUARTER_FINAL', 'SEMI_FINAL'].includes(session.current_stage)) {
        return 2;
    }

    if (session.current_stage === 'FINAL') {
        return 1;
    }

    return 1;
}

export function statusLabel(session) {
    if (!session) {
        return 'No save';
    }

    if (session.status === 'finished') {
        return 'Finished';
    }

    if (session.status === 'in_progress') {
        return 'In progress';
    }

    return 'Setup';
}

export function lastPlayedLabel(session) {
    const value = session?.updated_at ?? session?.created_at;

    if (!value) {
        return '';
    }

    const date = new Date(value);

    if (Number.isNaN(date.getTime())) {
        return '';
    }

    const minutes = Math.max(1, Math.round((Date.now() - date.getTime()) / 60000));

    if (minutes < 60) {
        return `${minutes}m ago`;
    }

    const hours = Math.round(minutes / 60);

    if (hours < 24) {
        return `${hours}h ago`;
    }

    const days = Math.round(hours / 24);

    if (days < 7) {
        return `${days}d ago`;
    }

    return new Intl.DateTimeFormat(undefined, {
        dateStyle: 'medium',
        timeStyle: 'short',
    }).format(date);
}
