((Drupal, once) => {
  Drupal.behaviors.commerceInbox = {
    attach: (context) => {
      once('commerceInboxMessageToggle', '.inbox-message', context).forEach(
        (message) => {
          message.addEventListener('click', () => {
            if (message.classList.contains('opened')) {
              return;
            }
            message.classList.add('opened');
            if (message.classList.contains('unread')) {
              Drupal.ajax({
                url: Drupal.url(
                  `admin/commerce/inbox-message/${message.dataset.messageId}/read`,
                ),
              }).execute();
            }
          });
          if (message.querySelector('.close')) {
            message.querySelector('.close').addEventListener('click', (e) => {
              e.stopPropagation();
              e.target.closest('.inbox-message').classList.remove('opened');
            });
          }
        },
      );
    },
  };
})(Drupal, once);
