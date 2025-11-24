/**
 * @file
 * Defines callback to copy link to clipboard.
 */

((Drupal) => {
  Drupal.behaviors.commerceCopyLink = {
    attach: (context) => {
      once('commerce-copy-link', '.commerce-copy-link', context).forEach(
        (copyLink) => {
          copyLink.addEventListener('click', (e) => {
            const target = e.target;
            const link = target.dataset.link;
            navigator.clipboard.writeText(link).then(
              () => {
                // Update icon and title after successful copy process.
                const title = target.getAttribute('title');
                target.classList.add('copied');
                target.setAttribute('title', Drupal.t('Copied!'));

                // Restore original icon and title after some time.
                setTimeout(() => {
                  target.classList.remove('copied');
                  target.setAttribute('title', title);
                }, 2000);
              },
              () => {
                console.log('Failed to copy link');
              },
            );
          });
        },
      );
    },
  };
})(Drupal);
