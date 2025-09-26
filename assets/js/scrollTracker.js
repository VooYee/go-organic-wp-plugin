/**
 * Scroll Tracker Module
 * Tracks scroll depth and velocity at predefined thresholds using requestAnimationFrame.
 * Emits events for 25%, 50%, 75%, and 100% scroll positions.
 *
 * @version 1.0.0
 * @author Purple Box
 */

const SCROLL_THRESHOLDS = [0.25, 0.5, 0.75, 1.0];
const fired = new Set();

/**
 * Calculates scroll event data including velocity.
 * @param {number} scrollTop - Current scroll position.
 * @param {number} docHeight - Document height minus viewport.
 * @param {number} currentTimestamp - Current performance timestamp.
 * @param {Object} meta - Metadata with page_id and session_id.
 * @returns {Object} Scroll event data.
 */
const getScrollEventData = (scrollTop, docHeight, currentTimestamp, meta) => {
    const timeDelta = (currentTimestamp - lastTimestamp) / 1000;
    const scrollDelta = Math.abs(scrollTop - lastScrollTop);
    const scrollVelocity = timeDelta > 0 ? Math.round(scrollDelta / timeDelta) : 0;

    return {
        event_type: 'scroll',
        page_id: meta?.page_id || null,
        session_id: meta?.session_id || null,
        ts: Date.now(),
        scroll_percent: null,
        velocity: scrollVelocity,
    };
};

let lastScrollTop = 0;
let lastTimestamp = performance.now();
let animationFrameId;

/**
 * Initializes the scroll tracker with a callback for event handling.
 * @param {Function} callback - Function to process scroll events.
 * @param {Object} meta - Metadata object with page_id and session_id.
 */
export function initScrollTracker(callback, meta) {
    /**
     * Tracks scroll position and triggers events at thresholds.
     */
    function trackScroll() {
        const scrollTop = window.scrollY;
        const docHeight = document.documentElement.scrollHeight - window.innerHeight;
        const scrolledRatio = scrollTop / docHeight;
        const currentTimestamp = performance.now();

        SCROLL_THRESHOLDS.forEach((threshold) => {
            if (scrolledRatio >= threshold && !fired.has(threshold)) {
                fired.add(threshold);
                const eventData = getScrollEventData(scrollTop, docHeight, currentTimestamp, meta);
                eventData.scroll_percent = threshold * 100;
                callback(eventData);
            }
        });

        lastScrollTop = scrollTop;
        lastTimestamp = currentTimestamp;
        animationFrameId = requestAnimationFrame(trackScroll);
    }

    animationFrameId = requestAnimationFrame(trackScroll);

    // Cleanup on page unload
    window.addEventListener('beforeunload', () => {
        cancelAnimationFrame(animationFrameId);
    });
}