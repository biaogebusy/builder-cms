/**
 * @file
 * Adds JavaScript functionality to the private message inbox block.
 */

((Drupal, drupalSettings, window, once) => {
  /**
   * Private message inbox block functionality.
   */
  class PrivateMessageInboxBlock {
    constructor() {
      this.container = null;
      this.loadingPrevInProgress = false;
      this.loadingNewInProgress = false;
      this.updateTimeoutId = null;
      this.previousButtonHandler = new Drupal.PrivateMessagePrevious(
        'load-previous-threads-button-wrapper',
        'load-previous-threads-button',
      );
    }

    /**
     * Initialize the block with default state and handlers.
     *
     * @param {HTMLElement} blockWrapper
     *   The inbox block.
     */
    init(blockWrapper) {
      this.container = blockWrapper;
      const threadId = this.container.querySelector('.private-message-thread')
        ?.dataset.threadId;
      if (threadId) {
        PrivateMessageInboxBlock.setActiveThread(threadId);
      }
      this.attachLoadOldButton();
      this.scheduleInboxUpdate();

      // Adds a subscriber to thread update.
      Drupal.PrivateMessageThreadEvent.subscribeToThreadChange(
        PrivateMessageInboxBlock.setActiveThread,
      );
    }

    /**
     * Sets the active thread visually.
     *
     * @param {string} threadId
     *   The thread ID.
     */
    static setActiveThread(threadId) {
      const activeThread = document.querySelector(
        '.private-message-thread--full-container .active-thread',
      );
      if (activeThread) {
        activeThread.classList.remove('active-thread');
      }

      const targetThread = document.querySelector(
        `.private-message-thread--full-container .private-message-thread[data-thread-id="${threadId}"]`,
      );
      if (targetThread) {
        targetThread.classList.remove('unread-thread');
        targetThread.classList.add('active-thread');
      }
    }

    /**
     * Updates the inbox with new threads.
     */
    updateInbox() {
      if (this.loadingNewInProgress) {
        return;
      }

      this.loadingNewInProgress = true;
      const ids = {};

      this.container
        .querySelectorAll('.private-message-thread-inbox')
        .forEach((el) => {
          ids[el.dataset.threadId] = el.dataset.lastUpdate;
        });

      Drupal.ajax({
        url: drupalSettings.privateMessageInboxBlock.loadNewUrl,
        submit: { ids },
        error: (err) => {
          window.location.reload();
          console.error(err);
        },
      })
        .execute()
        .always(() => {
          this.loadingNewInProgress = false;
        })
        .then(() => {
          this.scheduleInboxUpdate();
        });
    }

    /**
     * Sets a timeout for inbox updates.
     */
    scheduleInboxUpdate() {
      if (this.updateTimeoutId) {
        window.clearTimeout(this.updateTimeoutId);
      }
      const interval =
        drupalSettings.privateMessageInboxBlock.ajaxRefreshRate * 1000;
      if (interval) {
        this.updateTimeoutId = window.setTimeout(
          () => this.updateInbox(),
          interval,
        );
      }
    }

    /**
     * Appends older threads to the inbox.
     *
     * @param {string} threadsHtml
     *   HTML content of threads.
     */
    insertPreviousThreads(threadsHtml) {
      const newNodes = Drupal.PrivateMessageUtils.parseHTML(threadsHtml);

      Array.from(newNodes).forEach((node) => {
        const appendedElement = this.container.appendChild(node);

        Drupal.attachBehaviors(appendedElement);
        Drupal.PrivateMessageSlide.down(appendedElement, 300);
      });
    }

    /**
     * Handles loading older threads.
     *
     * @param {Event} e
     *   The click event.
     */
    loadOldThreads(e) {
      e.preventDefault();
      if (this.loadingPrevInProgress) {
        return;
      }

      this.loadingPrevInProgress = true;

      const oldestTimestamp = Array.from(
        this.container.querySelectorAll('.private-message-thread'),
      ).reduce((minTime, el) => {
        return Math.min(minTime, parseInt(el.dataset.lastUpdate, 10));
      }, Infinity);

      Drupal.ajax({
        url: drupalSettings.privateMessageInboxBlock.loadPrevUrl,
        submit: {
          timestamp: oldestTimestamp,
          count: drupalSettings.privateMessageInboxBlock.threadCount,
        },
        error: (err) => {
          window.location.reload();
          console.error(err);
        },
      })
        .execute()
        .always(() => {
          this.loadingPrevInProgress = false;
        });
    }

    /**
     * Reorders the inbox to show the newest threads first.
     *
     * @param {Array} threadIds
     *   Thread IDs in the desired order.
     * @param {Array} newThreads
     *   HTML content of new threads keyed by thread ID.
     */
    reorderInbox(threadIds, newThreads) {
      const existingThreads = {};

      this.container
        .querySelectorAll(':scope > .private-message-thread-inbox')
        .forEach((el) => {
          existingThreads[el.dataset.threadId] = el;
        });

      threadIds.forEach((threadId) => {
        if (newThreads[threadId]) {
          if (existingThreads[threadId]) {
            existingThreads[threadId].remove();
          }
          const newThreadContent = Drupal.PrivateMessageUtils.parseHTML(
            newThreads[threadId],
          );
          Array.from(newThreadContent).forEach((child) => {
            const appendedElement = this.container.appendChild(child);
            Drupal.attachBehaviors(appendedElement);
          });
        } else if (existingThreads[threadId]) {
          const appendedElement = this.container.appendChild(
            existingThreads[threadId],
          );
          Drupal.attachBehaviors(appendedElement);
        }
      });
    }

    /**
     * Attaches the "Load Older Threads" button handler.
     */
    attachLoadOldButton() {
      if (
        drupalSettings.privateMessageInboxBlock.totalThreads >
        drupalSettings.privateMessageInboxBlock.itemsToShow
      ) {
        this.previousButtonHandler.displayButton(this.container, (e) =>
          this.loadOldThreads(e),
        );
      }
    }
  }

  const privateMessageInboxBlock = new PrivateMessageInboxBlock();

  /**
   * Attaches the private message inbox block behavior.
   */
  Drupal.behaviors.privateMessageInboxBlock = {
    attach(context) {
      const containerFormContext = once(
        'private-message-inbox-block',
        '.block-private-message-inbox-block .private-message-thread--full-container',
        context,
      ).shift();

      if (!containerFormContext) {
        return;
      }

      privateMessageInboxBlock.init(containerFormContext);
    },
    detach(context) {
      privateMessageInboxBlock.previousButtonHandler.detachEventListener(
        context,
        privateMessageInboxBlock.loadOldThreads.bind(privateMessageInboxBlock),
      );
    },
  };

  /**
   * Custom AJAX commands for private message inbox.
   *
   * @param {Drupal.Ajax} ajax
   *   The Drupal Ajax object.
   * @param {object} response
   *   Object holding the server response.
   */
  Drupal.AjaxCommands.prototype.insertInboxOldPrivateMessageThreads = (
    ajax,
    response,
  ) => {
    if (response.threads) {
      privateMessageInboxBlock.insertPreviousThreads(response.threads);
    }

    if (!response.threads || !response.hasNext) {
      privateMessageInboxBlock.previousButtonHandler.slideDownButton();
    }
  };

  /**
   * Custom AJAX command to update the inbox with new threads.
   *
   * @param {Drupal.Ajax} ajax
   *   The Drupal Ajax object.
   * @param {object} response
   *   Object holding the server response.
   */
  Drupal.AjaxCommands.prototype.privateMessageInboxUpdate = (
    ajax,
    response,
  ) => {
    privateMessageInboxBlock.reorderInbox(
      response.threadIds,
      response.newThreads,
    );
  };

  /**
   * Custom AJAX command to trigger an inbox update.
   */
  Drupal.AjaxCommands.prototype.privateMessageTriggerInboxUpdate = () => {
    privateMessageInboxBlock.updateInbox();
  };
})(Drupal, drupalSettings, window, once);
