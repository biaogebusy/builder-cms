((Drupal, once) => {
  Drupal.behaviors.managementLinks = {
    attach(context) {
      once(
        'managementLinksToggle',
        '.management-links-toggle',
        context,
      ).forEach((toggle) => {
        toggle.addEventListener('click', this.toggleLinks);
      });
    },
    toggleLinks: (event) => {
      const toggle = event.currentTarget;
      toggle.classList.toggle('active');
      const target = toggle.getAttribute('aria-controls');
      const targetElements = document.getElementsByClassName(target);
      [...targetElements].forEach((element) => {
        element.classList.toggle('show');
      });
    },
  };
})(Drupal, once);
