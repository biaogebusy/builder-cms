/**
 * @file
 * Adds optional collapsible behavior to Commerce Admin Cards.
 */

((Drupal, once) => {
  let cardContentId = 0;

  Drupal.behaviors.commerceAdminCardCollapsible = {
    attach(context) {
      once(
        'commerce-admin-card-collapsible',
        '.commerce-admin-card--collapsible',
        context
      ).forEach((card) => {
        const trigger = card.querySelector(
          '[data-commerce-admin-card-trigger]'
        );
        const target = card.querySelector('[data-commerce-admin-card-target]');

        if (!trigger || !target) {
          return;
        }

        if (!target.id) {
          cardContentId += 1;
          target.id = `commerce-admin-card-content-${cardContentId}`;
        }

        trigger.setAttribute('aria-controls', target.id);

        const setState = (expanded) => {
          trigger.setAttribute('aria-expanded', expanded ? 'true' : 'false');
          target.hidden = !expanded;
          card.classList.toggle('commerce-admin-card--open', expanded);
        };

        const initialExpanded =
          trigger.getAttribute('aria-expanded') !== 'false';
        setState(initialExpanded);

        const toggle = () => {
          const isExpanded = trigger.getAttribute('aria-expanded') === 'true';
          setState(!isExpanded);
        };

        trigger.addEventListener('click', (event) => {
          event.preventDefault();
          toggle();
        });
      });
    },
  };
})(Drupal, once);
