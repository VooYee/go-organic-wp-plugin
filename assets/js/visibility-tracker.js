/**
 * Visibility Tracker Module
 * Detects when elements become visible using IntersectionObserver for read events and media engagement.
 *
 * @version 1.0.1
 * @author purple box
 * @see https://developer.mozilla.org/en-US/docs/Web/API/IntersectionObserver
 */

// console.log('Visibility Tracker loaded'); // Debug: Check if file is loaded

/**
 * Initializes the visibility tracker with a selector and callback.
 * @param {string} selector - CSS selector for elements to track.
 * @param {Object} options - Configuration options (e.g., threshold).
 * @param {Function} callback - Function to handle visibility events.
 * @returns {void} No batcher returned, handled by parent module.
 */
export function initVisibilityTracker(selector, options = {}, callback) {
  const observer = new IntersectionObserver((entries) => {
    const events = entries.map((entry) => {
      if (entry.isIntersecting) {
        return {
          type: 'visible',
          id: entry.target.id || entry.target.dataset.trackId || `anon_${Date.now()}`,
          ts: Date.now()
        };
      }
    }).filter(event => event !== undefined); // Filter out undefined entries

    if (events.length) {
      callback(events); // Kirim array events ke callback
    }
  }, {
    threshold: options.threshold || 0.25, // Default 25% visibility
    ...options
  });

  document.querySelectorAll(selector).forEach((el) => observer.observe(el));
}