(Drupal => {
  Drupal.behaviors.ginSticky = {
    attach: context => {
      once("ginSticky", ".region-sticky-watcher").forEach((() => {
        const observer = new IntersectionObserver((_ref => {
          let [e] = _ref;
          const regionSticky = context.querySelector(".region-sticky");
          regionSticky.classList.toggle("region-sticky--is-sticky", e.intersectionRatio < 1), 
          regionSticky.toggleAttribute("data-offset-top", e.intersectionRatio < 1), Drupal.displace(!0);
        }), {
          threshold: [ 1 ]
        }), element = context.querySelector(".region-sticky-watcher");
        element && observer.observe(element);
      }));
    }
  };
})(Drupal);