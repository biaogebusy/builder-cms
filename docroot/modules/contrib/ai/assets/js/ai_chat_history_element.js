(function (Drupal) {
  'use strict';

  Drupal.behaviors.aiAgentsChatHistoryElement = {
    attach: function (context, settings) {
      const draggables = once('draggable', '.chat-history-item', context);
      const containers = once('container', '.chat-history-wrapper', context);
      // Add dragstart and dragend events to draggables.
      draggables.forEach(draggable => {
        draggable.addEventListener('dragstart', () => {
          draggable.classList.add('dragging');
        })

        draggable.addEventListener('dragend', () => {
          draggable.classList.remove('dragging');
          // Reload the containers, so the items are in the correct order.
          let newContainers = window.document.querySelectorAll('.chat-history-wrapper');
          // Recalculate the weight on the forms.
          newContainers.forEach(container => {
            // Loop through all the items in the container.
            const items = container.querySelectorAll('.chat-history-item');
            items.forEach((item, index) => {
              // Set the weight of the item to its index.
              const weightInput = item.querySelector('.chat-history-weight');
              if (weightInput) {
                weightInput.value = index;
              }
            });
          });
        });
      })

      // Add dragover event to containers.
      containers.forEach(container => {
        container.addEventListener('dragover', e => {
          e.preventDefault();

          const afterElement = getDragAfterElement(container, e.clientY);
          const draggable = document.querySelector('.dragging');

          if (afterElement == null) {
            container.appendChild(draggable);
          } else {
            container.insertBefore(draggable, afterElement);
          }
        });
      })
      // Auto-resize textareas in chat history items.
      for (const textarea of once('auto-resize', '.chat-history-item textarea', context)) {
        const resize = () => {
          textarea.style.height = 'auto';
          textarea.style.height = textarea.scrollHeight + 'px';
        };

        textarea.addEventListener('input', resize);
        resize();
      }

      // Auto-resize textareas in tool call items.
      for (const textarea of once('auto-resize-tool-calls', '.tool-call-item textarea', context)) {
        const resize = () => {
          textarea.style.height = 'auto';
          textarea.style.height = textarea.scrollHeight + 'px';
        };

        textarea.addEventListener('input', resize);
        resize();
      }

      // Handle role changes to show/hide relevant fields
      for (const roleSelect of once('role-change', '.chat-history-role', context)) {
        // Function to update visibility based on role
        const updateVisibility = function(select) {
          const chatMessage = select.closest('.chat-message');
          if (!chatMessage) return;

          const toolCallsContainer = chatMessage.querySelector('.tool-calls-container');
          const toolCallIdRef = chatMessage.querySelector('.tool-call-id-reference');

          // Hide all role-specific fields first
          if (toolCallsContainer) {
            toolCallsContainer.style.display = 'none';
          }
          if (toolCallIdRef) {
            toolCallIdRef.closest('.form-item').style.display = 'none';
          }

          // Show relevant fields based on selected role
          if (select.value === 'assistant' && toolCallsContainer) {
            toolCallsContainer.style.display = 'block';
          } else if (select.value === 'tool' && toolCallIdRef) {
            toolCallIdRef.closest('.form-item').style.display = 'block';
          }
        };

        roleSelect.addEventListener('change', function() {
          updateVisibility(this);
        });

        // Set initial visibility without triggering change event (which would cause AJAX loop)
        updateVisibility(roleSelect);
      }

    }
  }


  // Helper function to get the element after which the dragged element inserts.
  function getDragAfterElement(container, y) {
    const draggableElements = [...container.querySelectorAll('.draggable:not(.dragging)')]

    return draggableElements.reduce((closest, child) => {
      const box = child.getBoundingClientRect();
      const offset = y - box.top - box.height / 2;
      if (offset < 0 && offset > closest.offset) {
        return { offset: offset, element: child };
      } else {
        return closest;
      }
    }, { offset: Number.NEGATIVE_INFINITY }).element;
  }

})(Drupal);
