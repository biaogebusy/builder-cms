(function (Drupal, once) {
  'use strict';

  Drupal.behaviors.aiChatbot = {
    attach: function (context, settings) {

      once('ai-chatbot', 'deep-chat', context).forEach(($deepChat) => {
        const dropDownMenu = context.querySelector('.chat-dropdown');
        // If its not found, return.
        if (!dropDownMenu) {
          return;
        }

        const chatContainers = context.querySelectorAll('.chat-container');

        const chevron = context.querySelector('.chevron-icon');
        const menuButton = dropDownMenu.querySelector('.chat-dropdown-button');
        const clearHistory = dropDownMenu.querySelector('.clear-history');

        chatContainers.forEach((container) => {
        // Retrieve the unique chat ID
          const chatId = container.getAttribute('data-chat-id');
          if (!chatId) {
            console.warn('Chat container is missing a data-chat-id attribute.');
            return;
          }

          // Select the header element within the container
          const header = container.querySelector('.ai-deepchat--header');
          if (!header) {
            console.warn('Header with class .ai-deepchat--header not found in container:', container);
            return;
          }

          // Select the chat element within the container
          const chatElement = container.querySelector('.chat-element');
          if (!chatElement) {
            console.warn('Chat element with class .chat-element not found in container:', container);
            return;
          }

          // Optional: Select the toggle icon if present
          const toggleIcon = header.querySelector('.toggle-icon');

          // Function to update toggle icon (optional)
          const updateToggleIcon = (isOpen) => {
            if (toggleIcon) {
              toggleIcon.classList.toggle('is-opened', !isOpen);
              toggleIcon.classList.toggle('is-closed', isOpen);
            }
        };

        // Function to open the chat
        const openChat = () => {
          container.classList.add('chat-open');
          container.classList.remove('chat-collapsed');
          container.classList.remove('chat-collapsed-minimal');
          header.classList.add('active');
          header.setAttribute('aria-expanded', 'true');
          if (toggleIcon) updateToggleIcon(true);
          localStorage.setItem(`deepChatState_${chatId}`, 'open');
        };

        // Function to close the chat
        const closeChat = () => {
          container.classList.add('chat-collapsed');
          if (drupalSettings.ai_deepchat.collapse_minimal) {
            container.classList.add('chat-collapsed-minimal');
          }
          container.classList.remove('chat-open');
          header.classList.remove('active');
          header.setAttribute('aria-expanded', 'false');
          if (toggleIcon) updateToggleIcon(false);
          localStorage.removeItem(`deepChatState_${chatId}`);
        };

         // Toggle function
         const toggleChat = () => {
          if (container.classList.contains('chat-open')) {
            closeChat();
          } else {
            openChat();
          }
        };

        // Toggle menu
        const toggleMenu = (event) => {
          // Don't run parent event
          event.stopPropagation();
          // Toggle it
          dropDownMenu.classList.toggle('active');
          chevron.classList.toggle('chevron-icon--rotate');
        }

        // Attach click event listener to the header
        header.addEventListener('click', toggleChat);

        // Attach keypress event listener for accessibility (e.g., Enter or Space keys)
        header.addEventListener('keypress', (event) => {
          if (event.key === 'Enter' || event.key === ' ') {
            event.preventDefault();
            toggleChat();
          }
        });

        // Initialize state from localStorage
        const savedState = localStorage.getItem(`deepChatState_${chatId}`);
        if (savedState === 'open') {
          openChat();
        } else {
          closeChat();
        }

        // Toggle the menu
        menuButton.addEventListener('click', toggleMenu);

        // Menu items
        clearHistory.addEventListener('click', (e) => {
          Drupal.clearDeepchatMessages(e);
          toggleMenu(e);
        });
      })
      });
    }
  };

})(Drupal, once);
