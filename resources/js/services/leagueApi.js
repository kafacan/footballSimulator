const jsonHeaders = () => ({
    Accept: 'application/json',
    'Content-Type': 'application/json',
    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? '',
});

async function request(path, options = {}) {
    const response = await fetch(path, {
        headers: jsonHeaders(),
        ...options,
    });

    const data = await response.json();

    if (!response.ok) {
        const message = data.message ?? 'The league request failed.';
        throw new Error(message);
    }

    return data;
}

export function getTeams() {
    return request('/teams');
}

export function getGameSessions() {
    return request('/game-sessions');
}

export function createGameSession(payload) {
    return request('/game-sessions', {
        method: 'POST',
        body: JSON.stringify(payload),
    });
}

export function getGameSessionState(sessionId) {
    return request(`/game-sessions/${sessionId}`);
}

export function deleteGameSession(sessionId) {
    return request(`/game-sessions/${sessionId}`, { method: 'DELETE' });
}

export function clearGameSessions() {
    return request('/game-sessions', { method: 'DELETE' });
}

export function playNextSessionWeek(sessionId) {
    return request(`/game-sessions/${sessionId}/play-next`, { method: 'POST' });
}

export function playAllSession(sessionId) {
    return request(`/game-sessions/${sessionId}/play-all`, { method: 'POST' });
}

export function resetSession(sessionId) {
    return request(`/game-sessions/${sessionId}/reset`, { method: 'POST' });
}

export function playSessionMatch(sessionId, matchId) {
    return request(`/game-sessions/${sessionId}/matches/${matchId}/play`, { method: 'POST' });
}

export function updateSessionMatchResult(sessionId, matchId, scores) {
    return request(`/game-sessions/${sessionId}/matches/${matchId}`, {
        method: 'PATCH',
        body: JSON.stringify(scores),
    });
}
