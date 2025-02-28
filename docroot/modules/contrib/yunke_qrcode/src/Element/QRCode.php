<?php

namespace Drupal\yunke_qrcode\Element;

use Drupal\Component\Render\MarkupInterface;
use Drupal\Component\Utility\Html as HtmlUtility;
use Drupal\Component\Utility\Xss;
use \Drupal\Core\Render\Element\RenderElement;
use Drupal\Core\Render\Markup;
use Drupal\Core\Template\Attribute;

/**
 * Provides a render element that generates a QR code from the front-end JS.
 *
 * QR code are generated automatically from javascript, It relies on jquery.qrcode.min.js
 *
 * @see https://github.com/jeromeetienne/jquery-qrcode
 *
 * Properties:
 * - #render: Presentation of QR code element, "canvas" OR "table"
 * - #width: QR code width, Unit: px,default 256 px
 * - #height: QR code height, Unit: px,default 256 px
 * - #correctLevel: This affects the anti-pollution ability of QR
 *   code,An integer with ranging from 0 to 3,The corresponding
 *   fault-tolerant values are: 0(15%)  1(7%)  2(30%)  3(25%)
 * - #typeNumber: QR code version, An integer between 1 and 40, manual
 *   setting is not recommended, The recommended value is -1. This
 *   will allow the system to calculate automatically
 * - #background: The background color of QR code
 * - #foreground: The foreground color of QR code
 * - #text: The content encoded by QR code
 * - #tag: HTML tag to place QR code, only allow div、span、p
 * - #attributes: (array, optional) HTML attributes to apply to the tag. The
 *   attributes are escaped, see \Drupal\Core\Template\Attribute.
 * - #value: (string, optional) A string containing the textual contents of
 *   the tag.
 * - #noscript: (bool, optional) When set to TRUE, the markup
 *   (including any prefix or suffix) will be wrapped in a <noscript> element.
 *
 *
 * Usage example:
 * @code
 * $qrcode['yunke'] = [
 *   '#type' => 'yunke_qrcode',
 *   '#text' => $this->t('will nice'),
 * ];
 * @endcode
 *
 * @RenderElement("yunke_qrcode")
 */
class QRCode extends RenderElement {

  /**
   * Valid elements that can place a QR code.
   */
  protected static $validElements = [
    'div',
    'span',
    'p',
  ];

  /**
   * {@inheritdoc}
   */
  public function getInfo() {
    $class = static::class;
    return [
      '#render' => "canvas",
      // Presentation of QR code element, "canvas" OR "table"

      // For Chinese developers who are not familiar with English,
      // the following is an explanation in Chinese，the same below
      //二维码元素的呈现方式 canvas画布或者table表格

      '#width' => 256,
      //QR code width, Unit: px,default 256 px
      //二维码宽，单位px 默认为256

      '#height' => 256,
      //QR code height, Unit: px,default 256 px
      //二维码高，单位px 默认为256

      '#correctLevel' => 1,
      //This affects the anti-pollution ability of QR code,An integer with ranging from 0 to 3
      //The corresponding fault-tolerant values are: 0(15%)  1(7%)  2(30%)  3(25%)
      //容错等级，取值范围为 [0-3]的整数，分别指代抗污损性能为：0(15%)  1(7%)  2(30%)  3(25%)
      //注意不是数字越大容错越高，4级容错分别是：L级容错7%数字1表示,M:15%:0,Q:25%:3,H:30%:2 ,容错越高二维码越复杂，但污损可以越严重

      '#typeNumber' => -1,
      // QR code version, An integer between 1 and 40, manual setting is not recommended
      //The recommended value is -1. This will allow the system to calculate automatically
      // 二维码版本Version，值越大基础单元格越多（可容纳的信息越大），1-40的取值，小于1将自动计算， 默认值为-1（且推荐-1）
      //这涉及二维码基础，Version1是21x21规格的矩阵，Version2是25x25，Version3是29x29，每增加一个version，规格就会增加4，
      //公式是：(V-1)*4 + 21（V是版本号），最高Version 40，(40-1)*4+21 = 177，所以最高是177 x 177的正方形。

      '#background' => "#ffffff",
      //The background color of QR code
      //背景色 默认为“#ffffff” 白色

      '#foreground' => "#000000",
      //The foreground color of QR code
      //前景色 默认为“#000000” 黑色

      '#text' => 'yunke',
      //The content encoded by QR code
      //被二维码编码内容

      '#tag' => 'div',
      //HTML tag to place QR code, only allow div、span、p
      //包装二维码的HTML标签，只允许div、span、p

      '#pre_render' => [
        [$class, 'preRenderHtmlTag'],
      ],
      '#attributes' => [],
      '#value'      => '',
    ];
  }

  public static function preRenderHtmlTag($element) {
    //Sets the selector class to find QR code elements
    //设置查找二维码元素的选择器类
    $QRElementClass = 'yunke_qrcode_element';
    $element['#attached']['drupalSettings']['QRElementClass'] = $QRElementClass;
    if (isset($element['#attached']['library'])) {
      $element['#attached']['library'][] = 'yunke_qrcode/qrcode';
    }
    else {
      $element['#attached']['library'] = ['yunke_qrcode/qrcode'];
    }

    if (!isset($element['#attributes'])) {
      $element['#attributes'] = ['class' => [$QRElementClass]];
    }
    if (!isset($element['#attributes']['class'])) {
      $element['#attributes']['class'] = [$QRElementClass];
    }
    else {
      if (!is_array($element['#attributes']['class'])) {
        $element['#attributes']['class'] = [$element['#attributes']['class']];
      }
      $element['#attributes']['class'] = array_merge($element['#attributes']['class'], [$QRElementClass]);
    }
    $element['#attributes']['data-render'] = $element['#render'];
    $element['#attributes']['data-width'] = $element['#width'];
    $element['#attributes']['data-height'] = $element['#height'];
    $element['#attributes']['data-correct-level'] = $element['#correctLevel']; //L  has to be lowercase
    $element['#attributes']['data-type-number'] = $element['#typeNumber']; //N  has to be lowercase
    $element['#attributes']['data-background'] = $element['#background'];
    $element['#attributes']['data-foreground'] = $element['#foreground'];
    $element['#attributes']['data-text'] = $element['#text'];

    $attributes = new Attribute($element['#attributes']);

    $escaped_tag = HtmlUtility::escape($element['#tag']);
    if (!in_array($escaped_tag, self::$validElements)) {
      $escaped_tag = 'div';
    }
    $open_tag = '<' . $escaped_tag . $attributes . '>';
    $close_tag = '</' . $escaped_tag . ">\n";

    $markup = $element['#value'] instanceof MarkupInterface ? $element['#value'] : Xss::filterAdmin($element['#value']);
    $element['#markup'] = Markup::create($markup);

    $prefix = isset($element['#prefix']) ? $element['#prefix'] . $open_tag : $open_tag;
    $suffix = isset($element['#suffix']) ? $close_tag . $element['#suffix'] : $close_tag;
    if (!empty($element['#noscript'])) {
      $prefix = '<noscript>' . $prefix;
      $suffix .= '</noscript>';
    }
    $element['#prefix'] = Markup::create($prefix);
    $element['#suffix'] = Markup::create($suffix);
    return $element;
  }

}
