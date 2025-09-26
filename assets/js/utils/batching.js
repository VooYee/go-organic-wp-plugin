/**
 * Batching Utility Module
 * Manages event batching with a configurable delay for efficient processing.
 *
 * @version 1.0.0
 * @author Purple Box
 */

export function batchEvents(callback, delay = 10000) {
    let buffer = [];

    function flush() {
        if (buffer.length > 0) {
            const batchedData = { data: buffer.slice() };
            callback(batchedData);
            buffer = [];
        }
    }

    return {
        push: (event) => buffer.push(event),
        start: () => setInterval(flush, delay),
        flush,
    };
}