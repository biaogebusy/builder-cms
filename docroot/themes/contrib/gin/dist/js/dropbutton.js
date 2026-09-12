((Drupal, once) => {
  Drupal.behaviors.ginDropbutton = {
    attach: function(context) {
      once("ginDropbutton", ".dropbutton-multiple:has(.dropbutton--gin)", context).forEach((el => {
        const toggle = el.querySelector(".dropbutton__toggle");
        toggle && toggle.addEventListener("click", (() => {
          this.positionDropdown(el);
        }));
      }));
    },
    positionDropdown: function(el) {
      const secondaryAction = el.querySelector(".secondary-action"), dropMenu = el.querySelector(".dropbutton__items");
      if (!secondaryAction || !dropMenu) return;
      const toolbarTotalHeight = (parseFloat(getComputedStyle(document.documentElement).getPropertyValue("--gin-toolbar-y-offset")) || 0) + (parseFloat(getComputedStyle(document.documentElement).getPropertyValue("--gin-toolbar-height")) || 0), boundingRect = secondaryAction.getBoundingClientRect(), spaceBelow = window.innerHeight - boundingRect.bottom, halfVh = Math.floor(window.innerHeight / 2), upperBound = el.closest("form") || document.querySelector("#block-gin-content") || document.body, upperTop = Math.max(upperBound.getBoundingClientRect().top, 0), effectiveSpaceAbove = Math.max(boundingRect.top - upperTop, 0);
      dropMenu.style.position = "absolute", dropMenu.style.overflowY = "auto", spaceBelow >= effectiveSpaceAbove ? (dropMenu.style.top = "100%", 
      dropMenu.style.bottom = "auto", dropMenu.style.maxHeight = `${Math.max(spaceBelow - 32, 120)}px`) : (dropMenu.style.top = "auto", 
      dropMenu.style.bottom = "100%", dropMenu.style.maxHeight = Math.max(Math.min(effectiveSpaceAbove, halfVh), 0) - toolbarTotalHeight + "px");
    }
  };
})(Drupal, once);