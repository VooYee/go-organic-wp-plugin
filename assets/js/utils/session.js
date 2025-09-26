/**
 * Session Utility Module
 * Handles UUID generation and session management using localStorage.
 *
 * @version 1.0.0
 * @author Purple Box
 */

export function generateUUID() {
    return ([1e7] + -1e3 + -4e3 + -8e3 + -1e11).replace(/[018]/g, (c) =>
        (c ^ (crypto.getRandomValues(new Uint8Array(1))[0] & 15) >> c / 4).toString(16)
    );
}

export function getSessionId() {
    let session = localStorage.getItem('tracking_session');
    if (!session) {
        session = generateUUID();
        localStorage.setItem('tracking_session', session);
    }
    return session;
}