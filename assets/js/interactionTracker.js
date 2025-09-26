/**
 * Interaction Tracker Module
 * Tracks interactions with elements tagged with data-interaction (e.g., accordion, FAQ, tabs).
 * Manages state toggling and emits detailed event data.
 *
 * @version 1.0.0
 * @author Purple Box
 */

/**
 * Generates a unique ID for elements without one.
 * @returns {string} Unique identifier.
 */
const generateUniqueId = () => `int_${Date.now()}_${Math.random().toString(36).substr(2, 5)}`;

/**
 * Extracts interaction event data from a target element.
 * @param {Element} el - The interacted element.
 * @returns {Object} Interaction event data.
 */
const getInteractionEventData = (el) => {
    const interactionType = el.dataset.interaction;
    const label = el.innerText.trim().slice(0, 100) || el.getAttribute('id') || 'unknown';
    const elementId = el.id || generateUniqueId();
    const currentState = el.getAttribute('data-state') || 'closed';
    const action = currentState === 'open' ? 'open' : 'close';

    el.setAttribute('data-state', action === 'open' ? 'open' : 'closed');

    return {
        event_type: 'interaction',
        element_type: interactionType,
        element_id: elementId,
        interaction_type: action,
        label,
        ts: Date.now(),
    };
};

/**
 * Initializes the interaction tracker with a callback.
 * @param {Function} callback - Function to process interaction events.
 */
export function initInteractionTracker(callback) {
    document.body.addEventListener('click', (event) => {
        const target = event.target.closest('[data-interaction]');
        if (!target) return;

        const eventData = getInteractionEventData(target);
        callback(eventData);
        // console.log('Interaction Event:', eventData); // Debug log
    });
}