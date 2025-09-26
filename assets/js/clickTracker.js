/**
 * Click Tracker Module
 * Tracks click events on links, buttons, and data-track elements with debouncing.
 * Emits event data including link text, target URL, and viewport position.
 *
 * @version 1.0.0
 * @author Purple Box
 */

const DEBOUNCE_DELAY = 250;

/**
 * Creates a debounced function to limit event frequency.
 * @param {Function} fn - The function to debounce.
 * @param {number} delay - Delay in milliseconds.
 * @returns {Function} Debounced function.
 */
const debounce = (fn, delay) => {
    let timeoutId;
    return (...args) => {
        clearTimeout(timeoutId);
        timeoutId = setTimeout(() => fn(...args), delay);
    };
};

/**
 * Extracts click event data from a target element.
 * @param {Element} el - The clicked element.
 * @param {Object} meta - Metadata including page_id and session_id.
 * @returns {Object} Click event data.
 */
const getClickEventData = (el, meta) => {
    const href = el.getAttribute('href') || null;
    const rect = el.getBoundingClientRect();
    const positionInView = rect.top >= 0 && rect.bottom <= window.innerHeight
        ? (rect.top / window.innerHeight).toFixed(2)
        : (rect.top < 0 ? 0 : 1).toFixed(2);

    return {
        event_type: 'click',
        page_id: meta?.page_id || null,
        session_id: meta?.session_id || null,
        ts: Date.now(),
        link_text: el.innerText?.trim().slice(0, 100) || null,
        target_url: href,
        position_in_view: Number(positionInView),
        internal: href ? href.startsWith(window.location.origin) : false,
    };
};

/**
 * Initializes the click tracker with a callback for event handling.
 * @param {Function} callback - Function to process click events.
 * @param {Object} meta - Metadata object with page_id and session_id.
 */
export function initClickTracker(callback, meta) {
    const handleClick = debounce((event) => {
        const target = event.target.closest('[data-track], a, button');
        if (!target) return;

        const eventData = getClickEventData(target, meta);
        callback(eventData);
    }, DEBOUNCE_DELAY);

    document.body.addEventListener('click', handleClick, { passive: true });
}