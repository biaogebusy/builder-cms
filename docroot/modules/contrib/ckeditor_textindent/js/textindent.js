(function($) {
    $('p[data-text-indent]').each(function() {
        this.setAttribute('style', 'text-indent:'+jQuery(this).data('text-indent'));
    });
})(jQuery);