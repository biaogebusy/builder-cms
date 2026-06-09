(function (Drupal, once) {
  'use strict';

  Drupal.behaviors.aiGlobal = {
    attach: function (context) {
      const popoverShowDelay = 500; // ms

      once('ai-tooltip', '[data-ai-tooltip]', context).forEach(popoverTrigger => {
        let pendingShowTimer = null;
        let pendingHideTimer = null;

        const popoverId = 'ai-tooltip-' + Math.random().toString(36).substring(2, 15);
        popoverTrigger.setAttribute('popovertarget', popoverId);
        popoverTrigger.setAttribute('tabindex', '0');
        popoverTrigger.setAttribute('aria-describedby', popoverId);

        const popover = document.createElement('div');
        popover.classList.add('ai-tooltip');
        popover.setAttribute('popover', 'hint');
        popover.setAttribute('id', popoverId);
        popover.innerHTML = popoverTrigger.dataset.aiTooltip;
        document.body.appendChild(popover);

        const showTooltip = () => {
          // If a hide timer is pending, cancel it.
          if (pendingHideTimer) {
            clearTimeout(pendingHideTimer);
            pendingHideTimer = null;
          }
          // If a show timer is pending, cancel it.
          if (pendingShowTimer) {
            clearTimeout(pendingShowTimer);
          }
          // Set a new show timer.
          pendingShowTimer = setTimeout(() => {
            popover.showPopover({ source: popoverTrigger });
            pendingShowTimer = null;
          }, popoverShowDelay);
        }

        const hideTooltip = () => {
          // If a show timer is pending, cancel it.
          if (pendingShowTimer) {
            clearTimeout(pendingShowTimer);
            pendingShowTimer = null;
          }
          // If a hide timer is pending, cancel it.
          if (pendingHideTimer) {
            clearTimeout(pendingHideTimer);
          }
          // Set a new hide timer.
          pendingHideTimer = setTimeout(() => {
            popover.hidePopover();
            pendingHideTimer = null;
          }, popoverShowDelay);
        };

        popoverTrigger.addEventListener('mouseenter', showTooltip);
        popoverTrigger.addEventListener('mouseleave', hideTooltip);
        popoverTrigger.addEventListener('focusin', showTooltip);
        popoverTrigger.addEventListener('focusout', hideTooltip);
      });
    }
  }
})(Drupal, once);
