/**
 * @file
 * Adds JavaScript functionality to private message threads.
 */

((Drupal, drupalSettings, window, once) => {
  let originalThreadId;

  /**
   * Private message thread functionality.
   *
   * Provides methods for loading, updating, and interacting with private
   * message threads dynamically. It integrates with Drupal's behaviors
   * and AJAX framework to ensure seamless updates and message retrieval.
   */
  class PrivateMessageThread {
    constructor() {
      // #private-message-page element
      this.threadWrapper = null;
      this.updateTimeoutId = null;
      this.loadingNewInProgress = false;
      this.loadingThreadInProgress = false;
      this.pushHistory = false;
      this.loadingPrevInProgress = false;
      this.previousButtonHandler = null;
    }

    /**
     * Initialize the thread state and set up event handlers.
     *
     * This method prepares the thread wrapper for interaction, initializes
     * the previous button, and schedules automatic updates for new messages.
     *
     * @param {ParentNode} threadWrapper
     *   The parent element containing the private message thread content.
     */
    init(threadWrapper) {
      this.threadWrapper = threadWrapper;

      this.createPreviousButton();
      this.scheduleThreadUpdate();
      this.attachPreviousButton();
    }

    /**
     * Retrieves the current thread ID from the DOM.
     *
     * @return {string|null}
     *   The ID of the active thread, or null if the element is not found.
     */
    getThreadId() {
      return this.threadWrapper?.querySelector('.private-message-thread')
        ?.dataset.threadId;
    }

    /**
     * Gets messages wrapper.
     *
     *  .private-message-wrapper parent element.
     *
     * @return {HTMLElement}
     *   Messages wrapper.
     */
    getMessagesWrapper() {
      return this.threadWrapper.querySelector(
        '.private-message-thread-messages .private-message-wrapper',
      ).parentNode;
    }

    /**
     * Creates previous button.
     */
    createPreviousButton() {
      const classWrapper =
        drupalSettings.privateMessageThread.messageOrder === 'asc'
          ? 'load-previous-position-before'
          : 'load-previous-position-after';

      this.previousButtonHandler = new Drupal.PrivateMessagePrevious(
        'load-previous-messages-button-wrapper',
        'load-previous-messages',
        classWrapper,
      );
    }

    /**
     * Sets a timeout for thread updates.
     */
    scheduleThreadUpdate() {
      if (this.updateTimeoutId) {
        window.clearTimeout(this.updateTimeoutId);
      }
      const interval = drupalSettings.privateMessageThread.refreshRate;
      if (interval) {
        this.updateTimeoutId = window.setTimeout(
          () => this.getNewMessages(),
          interval,
        );
      }
    }

    /**
     * Retrieves new messages from the server.
     *
     * Sends an AJAX request to fetch messages that are newer than the
     * latest message in the current thread. Ensures that only one request
     * is made at a time to avoid conflicts.
     */
    getNewMessages() {
      // Only attempt a retrieval if one is not already in progress.
      if (this.loadingNewInProgress) {
        return;
      }

      this.loadingNewInProgress = true;

      // Get the ID of the newest message. This will be used as a reference
      // server side to determine which messages to return to the browser.
      let newestId = 0;
      this.getMessagesWrapper()
        .querySelectorAll('.private-message')
        .forEach((el) => {
          const messageId = parseInt(el.dataset.messageId, 10);
          newestId = Math.max(messageId, newestId);
        });

      Drupal.ajax({
        url: drupalSettings.privateMessageThread.newMessageCheckUrl,
        submit: { threadid: this.getThreadId(), messageid: newestId },
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
          this.scheduleThreadUpdate();
        });
    }

    /**
     * Inserts messages into the thread.
     *
     * @param {string} messagesHtml
     *   The new messages.
     * @param {string} insertType
     *   Type of insert: 'new' or 'previous'
     * @param {boolean} hasNext
     *   Has next messages.
     */
    insertMessages(messagesHtml, insertType, hasNext) {
      const messages = Drupal.PrivateMessageUtils.parseHTML(messagesHtml);
      const messagesWrapper = this.getMessagesWrapper();
      const messageList = Array.from(messages);
      const atTheBeginning = PrivateMessageThread.addAtTheBeginning(insertType);
      const { insertStyle, insertSpeed } = drupalSettings.privateMessageThread;

      if (atTheBeginning) {
        messageList.reverse();
      }

      messageList.forEach((message) => {
        if (atTheBeginning) {
          messagesWrapper.prepend(message);
        } else {
          messagesWrapper.appendChild(message);
        }

        if (insertStyle === 'fade') {
          Drupal.PrivateMessageSlide.fadeIn(message, insertSpeed);
        } else {
          Drupal.PrivateMessageSlide.down(message, insertSpeed);
        }

        Drupal.attachBehaviors(message);
      });

      if (hasNext !== undefined && !hasNext) {
        this.previousButtonHandler.slideDownButton();
      }
    }

    /**
     * Loads a thread from the server.
     *
     * @param {string} threadId
     *   The thread ID.
     */
    loadThread(threadId) {
      // Only try loading the thread if a thread isn't already loading, and if the
      // requested thread is not the current thread.
      if (this.loadingThreadInProgress || threadId === this.getThreadId()) {
        return;
      }

      this.loadingThreadInProgress = true;
      Drupal.PrivateMessageDimmer.showDimmer(this.threadWrapper);

      Drupal.ajax({
        url: drupalSettings.privateMessageThread.loadThreadUrl,
        httpMethod: 'GET',
        submit: { id: threadId },
        error: (err) => {
          window.location.reload();
          console.error(err);
        },
      })
        .execute()
        .always(() => {
          this.loadingThreadInProgress = false;
        })
        .then(() => {
          this.scheduleThreadUpdate();

          // Emit the new thread ID for other scripts.
          Drupal.PrivateMessageThreadEvent.emitNewThreadId(threadId);
        });
    }

    /**
     * Replaces the current thread content with a new thread.
     *
     * This method detaches behaviors from the existing thread and attaches
     * them to the new thread. It also updates the browser's history if
     * necessary.
     *
     * @param {Object} threadHtml
     *   The HTML content of the new thread, typically retrieved from the server.
     */
    insertThread(threadHtml) {
      const originalThread = this.threadWrapper.querySelector(
        '.private-message-thread',
      );
      const newThreadWrapper = Drupal.PrivateMessageUtils.parseHTML(threadHtml);

      let newThread = null;
      Array.from(newThreadWrapper).forEach((node) => {
        newThread = node.querySelector('.private-message-thread');
      });

      if (!newThread) {
        return;
      }

      // Detach behaviors from the current thread.
      Drupal.detachBehaviors(originalThread);

      originalThread.replaceWith(newThread);
      originalThread.remove();

      // Reattach behaviors to the new thread, ensuring forms are included.
      Drupal.attachBehaviors(newThread);
      this.init(this.threadWrapper);

      if (this.pushHistory) {
        this.pushHistory = false;
        Drupal.history.push(
          { threadId: this.getThreadId() },
          document.title,
          drupalSettings.privateMessageThread.threadUrl,
        );
      }

      Drupal.PrivateMessageDimmer.hideDimmer();
    }

    /**
     * Insert button to load previous thread messages.
     */
    attachPreviousButton() {
      if (
        drupalSettings.privateMessageThread.messageTotal >
        drupalSettings.privateMessageThread.messageCount
      ) {
        const position =
          drupalSettings.privateMessageThread.messageOrder === 'asc'
            ? 'beforebegin'
            : 'afterend';

        this.previousButtonHandler.displayButton(
          this.getMessagesWrapper(),
          (e) => this.loadPreviousMessages(e),
          position,
        );
      }
    }

    /**
     * Handles loading older messages.
     *
     * @param {Event} e
     *   The click event.
     */
    loadPreviousMessages(e) {
      e.preventDefault();

      // Ensure that a load isn't already in progress.
      if (this.loadingPrevInProgress) {
        return;
      }

      this.loadingPrevInProgress = true;

      // Get the ID of the oldest message. This will be used for reference to
      // tell the server which messages it should send back.
      let oldestId;
      this.getMessagesWrapper()
        .querySelectorAll('.private-message')
        .forEach((el) => {
          const messageId = parseInt(el.dataset.messageId, 10);
          oldestId = !oldestId ? messageId : Math.min(messageId, oldestId);
        });

      Drupal.ajax({
        url: drupalSettings.privateMessageThread.previousMessageCheckUrl,
        httpMethod: 'GET',
        submit: { threadid: this.getThreadId(), messageid: oldestId },
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
     * Determines whether to add at the beginning.
     *
     * @param {string} insertType
     *   Type of insert: 'new' or 'previous'.
     * @return {boolean}
     *   TRUE if the message should be added at the beginning, FALSE otherwise.
     */
    static addAtTheBeginning(insertType) {
      const isAscending =
        drupalSettings.privateMessageThread.messageOrder === 'asc';
      return insertType === 'new' ? !isAscending : isAscending;
    }
  }

  const privateMessageThread = new PrivateMessageThread();

  /**
   * Click Handler executed when private message threads are clicked.
   *
   * Loads the thread into the private message window.
   * @param {Event} e The event.
   */
  function inboxThreadLinkListenerHandler(e) {
    e.preventDefault();
    const { threadId } = e.currentTarget.dataset;
    if (threadId && privateMessageThread) {
      privateMessageThread.pushHistory = true;
      privateMessageThread.loadThread(threadId);
    }
  }

  /**
   * Attaches the private message thread behavior.
   */
  Drupal.behaviors.privateMessageThread = {
    attach(context) {
      once(
        'inbox-thread-link-listener',
        '.private-message-inbox-thread-link',
        context,
      ).forEach((el) => {
        el.addEventListener('click', inboxThreadLinkListenerHandler);
      });

      const threadContainer = once(
        'private-message-thread',
        '.private-message-thread-full',
        context,
      ).shift();

      if (!threadContainer) {
        return;
      }

      privateMessageThread.init(threadContainer.parentNode);
      originalThreadId = privateMessageThread.getThreadId();
    },
    detach(context) {
      // Unbind the 'click' event from '.private-message-inbox-thread-link' elements
      const inboxThreadLinks = context.querySelectorAll(
        '.private-message-inbox-thread-link',
      );

      inboxThreadLinks.forEach((link) => {
        link.removeEventListener('click', inboxThreadLinkListenerHandler);
      });

      privateMessageThread.previousButtonHandler?.detachEventListener(
        context,
        privateMessageThread.loadPreviousMessages.bind(privateMessageThread),
      );
    },
  };

  // Integrates the script with the previous/next buttons in the browser.
  window.onpopstate = (e) => {
    if (e.state && e.state.threadId) {
      privateMessageThread.loadThread(e.state.threadId);
    } else {
      privateMessageThread.loadThread(originalThreadId);
    }
  };

  /**
   * Ajax commands insertPrivateMessages command callback.
   *
   * Triggered by scheduleThreadUpdate or clicking previous messages.
   *
   * @param {Drupal.Ajax} ajax
   *   The Drupal Ajax object.
   * @param {object} response
   *   Object holding the server response.
   */
  Drupal.AjaxCommands.prototype.insertPrivateMessages = (ajax, response) => {
    privateMessageThread.insertMessages(
      response.messages,
      response.insertType,
      response.hasNext,
    );
  };

  /**
   * Ajax commands loadNewPrivateMessages command callback.
   *
   * Triggered by submitting a new message.
   */
  Drupal.AjaxCommands.prototype.loadNewPrivateMessages = () => {
    privateMessageThread.getNewMessages();
  };

  /**
   * Ajax commands privateMessageInsertThread command callback.
   *
   * Triggered by click thread inbox listener.
   *
   * @param {Drupal.Ajax} ajax
   *   The Drupal Ajax object.
   * @param {object} response
   *   Object holding the server response.
   */
  Drupal.AjaxCommands.prototype.privateMessageInsertThread = (
    ajax,
    response,
  ) => {
    if (response.thread && response.thread.length) {
      privateMessageThread.insertThread(response.thread);
    }
  };
})(Drupal, drupalSettings, window, once);
