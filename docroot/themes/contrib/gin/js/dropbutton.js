((Drupal, once) => {
  Drupal.behaviors.ginDropbutton = {
    attach: function (context) {
      once('ginDropbutton', '.dropbutton-multiple:has(.dropbutton--gin)', context).forEach(el => {
        const toggle = el.querySelector('.dropbutton__toggle');
        if (!toggle) return;

        toggle.addEventListener('click', () => {
          this.positionDropdown(el);
        });
      });
    },

    positionDropdown: function (el) {
      const secondaryAction = el.querySelector('.secondary-action');
      const dropMenu = el.querySelector('.dropbutton__items');
      if (!secondaryAction || !dropMenu) return;

      // Read the admin top bar height from the CSS custom property (0 if
      // absent).
      const topBarHeight = parseFloat(
        getComputedStyle(document.documentElement).getPropertyValue('--gin-toolbar-y-offset')
      ) || 0;
      // Read the secondary toolbar height from the CSS custom property (0 if
      // absent).
      const toolbarHeight = parseFloat(
        getComputedStyle(document.documentElement).getPropertyValue('--gin-toolbar-height')
      ) || 0;
      // Total the primary and secondary toolbar heights.
      const toolbarTotalHeight = topBarHeight + toolbarHeight;

      // Breathing room from viewport edges.
      const boundingRect = secondaryAction.getBoundingClientRect();
      const spaceBelow = window.innerHeight - boundingRect.bottom;
      const gap = 32;
      const halfVh = Math.floor(window.innerHeight / 2);
      const upperBound = el.closest('form') || document.querySelector('#block-gin-content') || document.body;
      const upperTop = Math.max(upperBound.getBoundingClientRect().top, 0);
      const effectiveSpaceAbove = Math.max(boundingRect.top - upperTop, 0);

      dropMenu.style.position = 'absolute';
      dropMenu.style.overflowY = 'auto';

      if (spaceBelow >= effectiveSpaceAbove) {
        dropMenu.style.top = '100%';
        dropMenu.style.bottom = 'auto';
        dropMenu.style.maxHeight = `${Math.max(spaceBelow - gap, 120)}px`;
      } else {
        dropMenu.style.top = 'auto';
        dropMenu.style.bottom = '100%';
        dropMenu.style.maxHeight = `${Math.max(Math.min(effectiveSpaceAbove, halfVh), 0) - toolbarTotalHeight}px`;
      }
    },
  };
})(Drupal, once);
