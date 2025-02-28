(function (Drupal, $) {
  $.fn.extend({
    smsSend: function () {
      reSend(60, this);
    }
  });

  function reSend(seconds, obj) {
    if (seconds > 1) {
      seconds--;
      var message = Drupal.t('Resend after @seconds seconds', {'@seconds': seconds});
      $(obj).val(message).attr("disabled", true);//禁用按钮
      // 定时1秒调用一次
      setTimeout(function () {
        reSend(seconds, obj);
      }, 1000);
    } else {
      $(obj).val(Drupal.t('Obtain Code')).attr("disabled", false);//启用按钮
    }
  }
})(Drupal, jQuery);
