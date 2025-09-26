/**
 * Engagement Timer Module
 * Tracks active time on page, excluding tab-out time, with batching every 5 seconds.
 * Uses focus/blur and scroll/mouse/keyboard events to detect engagement.
 *
 * @version 1.0.0
 * @author purple box
 * @see https://developer.mozilla.org/en-US/docs/Web/API/Document/visibilitychange_event
 */

// console.log('Engagement timer loaded'); // Debug: Check if file is loaded

let active = false;
let activeTime = 0;
let lastActive = null;
let totalTime = 0;
let startTime = null;

/**
 * Starts the engagement loop to batch data every 5 seconds.
 * @param {Function} callback - Function to handle engagement data.
 */
function startEngagementLoop(callback) {
    setInterval(() => {
        // console.log('Interval triggered', { active, lastActive }); // Debug: Check interval
        if (active && lastActive) {
            const delta = Date.now() - lastActive;
            activeTime += delta;
            lastActive = Date.now();
            totalTime = Date.now() - startTime; // Update total time
            callback({
                type: 'engagement',
                active_time: Math.floor(activeTime / 1000), // Convert to seconds
                total_time: Math.floor(totalTime / 1000), // Convert to seconds
                ts: Date.now()
            });
            // console.log('Engagement data sent:', { activeTime, totalTime }); // Debug: Check data
        }
    }, 5000);
}

/**
 * Initializes the engagement timer with event listeners.
 * @param {Function} callback - Function to handle engagement data.
 */
export function initEngagementTimer(callback) {
    startTime = Date.now(); // Start total time on init
    const setActive = () => {
        // console.log('Set active triggered'); // Debug: Check active events
        if (!active) lastActive = Date.now();
        active = true;
    };
    const setInactive = () => {
        // console.log('Set inactive triggered'); // Debug: Check inactive events
        active = false;
    };
    document.addEventListener('visibilitychange', () => {
        // console.log('Visibility changed:', document.hidden); // Debug: Check visibility
        if (document.hidden) setInactive();
        else setActive();
    });
    ['scroll', 'mousemove', 'keydown'].forEach((ev) =>
        window.addEventListener(ev, setActive, { passive: true })
    );
    startEngagementLoop(callback);
}