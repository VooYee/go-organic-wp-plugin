/**
 * Analytics Bundle Module
 * Orchestrates tracking of scroll, click, engagement, interaction, and visibility events.
 * Integrates with WordPress-localized TrackerMeta and batches events for API submission.
 *
 * @version 1.0.0
 * @author Purple Box
 */

import { initScrollTracker } from './scrollTracker.js';
import { initClickTracker } from './clickTracker.js';
import { initEngagementTimer } from './engagementTimer.js';
import { initInteractionTracker } from './interactionTracker.js';
import { initVisibilityTracker } from './visibility-tracker.js';
import { generateUUID, getSessionId } from './utils/session.js';
import { batchEvents } from './utils/batching.js';

/**
 * Logs batched events to the WordPress REST endpoint.
 * @param {Object} batchedData - Contains the 'data' array of events.
 */
async function logBatchedEvents(batchedData) {
    console.log('Batched Events:', batchedData.data);

    try {
        const response = await fetch('/wp-json/tracking/v1/batch', {
            method: 'POST',
           headers: {
                'Content-Type': 'application/json',
                'x-wp-key': TrackerMeta.api_password, // Password Kredensial
            },
            body: JSON.stringify(batchedData),
        });
        const result = await response.json();
        console.log('Endpoint Response:', result);
    } catch (error) {
        console.error('Error sending to endpoint:', error);
    }
}

/**
 * Placeholder for Supabase direct submission (not currently used).
 * @param {Object} batchedData - Contains the 'data' array of events.
 */
function sendToSupabase(batchedData) {
    // Placeholder, to be implemented if needed
}

document.addEventListener('DOMContentLoaded', () => {
    if (typeof TrackerMeta !== 'undefined') {
        const sessionId = getSessionId();
        TrackerMeta.session_id = sessionId;

        const eventBatcher = batchEvents(logBatchedEvents);
        eventBatcher.start();

        initScrollTracker((data) => {
            eventBatcher.push({
                session_id: sessionId,
                page_url: TrackerMeta.page_url,
                event_type: 'scroll',
                meta: {
                    page_id: data.page_id || null,
                    velocity: data.velocity || null,
                    scroll_percent: data.scroll_percent || null,
                },
                ts: Date.now(),
            });
        }, TrackerMeta);

        initClickTracker((data) => {
            eventBatcher.push({
                session_id: sessionId,
                page_url: TrackerMeta.page_url,
                event_type: 'click',
                meta: {
                    link_text: data.link_text || null,
                    target_url: data.target_url || null,
                    position_in_view: data.position_in_view || null,
                    internal: data.internal || false,
                    page_id: data.page_id || null,
                },
                ts: Date.now(),
            });
        }, TrackerMeta);

        initEngagementTimer((data) => {
            eventBatcher.push({
                session_id: sessionId,
                page_url: TrackerMeta.page_url,
                event_type: 'engagement',
                active_time: data.active_time,
                total_time: data.total_time,
                ts: data.ts,
            });
        }, TrackerMeta);

        initInteractionTracker((data) => {
            eventBatcher.push({
                session_id: sessionId,
                page_url: TrackerMeta.page_url,
                ...data,
                ts: data.ts,
            });
        }, TrackerMeta);

        initVisibilityTracker('.track-section', { threshold: 0.5 }, (events) => {
            if (Array.isArray(events)) {
                events.forEach((event) => {
                    eventBatcher.push({
                        session_id: sessionId,
                        page_url: TrackerMeta.page_url,
                        ...event,
                        ts: event.ts,
                    });
                });
            } else if (events && typeof events === 'object') {
                eventBatcher.push({
                    session_id: sessionId,
                    page_url: TrackerMeta.page_url,
                    ...events,
                    ts: Date.now(),
                });
            } else {
                console.warn('Invalid visibility event data:', events);
            }
        });
    } else {
        console.warn('TrackerMeta not available');
    }
});