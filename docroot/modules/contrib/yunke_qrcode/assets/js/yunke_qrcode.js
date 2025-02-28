/**
 * @yunke_qrcode
 * Responsible for generating QR code
 * 生成二维码
 */

(function ($, Drupal) {
  /**
   * Perform document ready tasks through the Drupal API
   * 通过Drupal API 执行文档就绪任务
   */
  Drupal.behaviors.yunkeQRcodeAutoAttach = {
    attach(context, settings) {
      const $context = $(context);
      let QRElementClass = settings.QRElementClass ? settings.QRElementClass : 'yunke_qrcode_element';
      QRElementClass = '.' + QRElementClass;
      let elements;
      elements = $context.find(QRElementClass);
      elements.once('yunke_qrcode').each(function (i) {
        let QRElement = $(this);
        let elementOption = {
          render: QRElement.data("render"),
          width: QRElement.data("width"),
          height: QRElement.data("height"),
          correctLevel: QRElement.data("correct-level"),
          typeNumber: QRElement.data("type-number"),
          background: QRElement.data("background"),
          foreground: QRElement.data("foreground"),
          text: QRElement.data("text") === undefined ? undefined : Drupal.yunkeQRcode.toUtf8(QRElement.data("text")),
        };

        option = $.extend({}, Drupal.yunkeQRcode.defaultOption, elementOption);
        QRElement.qrcode(option);
      });
    },
    detach(context, settings, trigger) {
      const $context = $(context);
      let elements;
      if (trigger === 'unload') {
        //nothing to do
      }
    },
  };


  /**
   * Handles character conversion, default values, etc
   * Make Asian characters correctly encoded by QR code
   * 处理字符转化、默认值等问题
   * @namespace
   */
  Drupal.yunkeQRcode = Drupal.yunkeQRcode || {

    /**
     * Convert the string to utf8
     * 将字符串转化为utf8
     */
    toUtf8(str = '') {
      var out, i, len, c;
      out = "";
      len = str.length;
      for (i = 0; i < len; i++) {
        c = str.charCodeAt(i);
        if ((c >= 0x0001) && (c <= 0x007F)) {
          out += str.charAt(i);
        } else if (c > 0x07FF) {
          out += String.fromCharCode(0xE0 | ((c >> 12) & 0x0F));
          out += String.fromCharCode(0x80 | ((c >> 6) & 0x3F));
          out += String.fromCharCode(0x80 | ((c >> 0) & 0x3F));
        } else {
          out += String.fromCharCode(0xC0 | ((c >> 6) & 0x1F));
          out += String.fromCharCode(0x80 | ((c >> 0) & 0x3F));
        }
      }
      return out;
    },

    defaultOption: {
      render: "canvas", // canvas画布方式、table表格方式，默认为canvas
      width: 256, //二维码宽，单位px 默认为256
      height: 256,//二维码高，单位px 默认为256
      correctLevel: 1,//容错等级，可取值范围为 [0-3]，分别指代含有15%，7%，30%，25% 的纠错码，默认为最高的 30%(2)；
      //注意不是数字越大容错越高，4级容错分别是：L级容错7%数字1表示,M:15%:0,Q:25%:3,H:30%:2 ,容错越高二维码越复杂，但污损可以越严重
      typeNumber: -1,
      // 二维码版本Version，值越大基础单元格越多（可容纳的信息越大），1-40的取值，小于1将自动计算， 默认值为-1（且推荐-1）
      //这涉及二维码基础，Version1是21x21规格的矩阵，Version2是25x25，Version3是29x29，每增加一个version，规格就会增加4，
      //公式是：(V-1)*4 + 21（V是版本号），最高Version 40，(40-1)*4+21 = 177，所以最高是177 x 177的正方形。
      background: "#ffffff", //背景色 默认为“#ffffff” 白色
      foreground: "#000000", //前景色 默认为“#000000” 黑色
      text: 'none',//二维码编码内容
    },

  };
}(jQuery, Drupal));
