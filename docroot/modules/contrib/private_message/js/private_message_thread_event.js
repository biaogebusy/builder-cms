/**
 * @file
 * Provides event handling for private message thread changes.
 */

((Drupal) => {
  class PrivateMessageThreadEvent {
    constructor() {
      // A map of event listeners for thread changes.
      this.threadChangeListeners = [];
    }

    /**
     * Emits a new thread ID and notifies all listeners.
     *
     * @param {string} threadId
     *   The new thread ID.
     */
    emitNewThreadId(threadId) {
      this.threadChangeListeners.forEach((listener) => {
        if (typeof listener === 'function') {
          listener(threadId);
        }
      });
    }

    /**
     * Subscribes a listener to thread ID changes.
     *
     * @param {Function} listener
     *   The listener function to execute on thread change.
     */
    subscribeToThreadChange(listener) {
      if (typeof listener === 'function') {
        this.threadChangeListeners.push(listener);
      }
    }

    /**
     * Unsubscribes a listener from thread ID changes.
     *
     * @param {Function} listener
     *   The listener function to remove.
     */
    unsubscribeFromThreadChange(listener) {
      this.threadChangeListeners = this.threadChangeListeners.filter(
        (l) => l !== listener,
      );
    }
  }

  // Expose the events object globally.
  Drupal.PrivateMessageThreadEvent = new PrivateMessageThreadEvent();
})(Drupal);
