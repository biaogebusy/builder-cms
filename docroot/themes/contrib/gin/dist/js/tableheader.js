((Drupal, once) => {
  Drupal.behaviors.ginTableHeader = {
    attach: context => {
      Drupal.ginTableHeader.init(context);
    }
  }, Drupal.ginTableHeader = {
    init: function(context) {
      once("ginTableHeader", ".sticky-enabled", context).forEach((el => {
        new IntersectionObserver((_ref => {
          let [e] = _ref;
          context.querySelector(".gin-table-scroll-wrapper") && (e.isIntersecting || e.intersectionRect.top !== Drupal.displace.offsets.top ? context.querySelector(".gin-table-scroll-wrapper").classList.remove("--is-sticky") : context.querySelector(".gin-table-scroll-wrapper").classList.add("--is-sticky"), 
          Drupal.displace(!0));
        }), {
          threshold: 1,
          rootMargin: `-${Drupal.displace.offsets.top}px 0px 0px 0px`
        }).observe(el.querySelector("thead"));
      }));
    }
  };
})(Drupal, once);