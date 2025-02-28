<?php

declare(strict_types = 1);

namespace Drupal\sms_sendtophone\Plugin\Field\FieldFormatter;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\FormatterBase;
use Drupal\Core\Url;

/**
 * Defines a SMS field formatter.
 *
 * @FieldFormatter(
 *   id = \Drupal\sms_sendtophone\Plugin\Field\FieldFormatter\SmsLinkFormatter::PLUGIN_ID,
 *   label = @Translation("SMS Link"),
 *   field_types = {"text"}
 * )
 */
class SmsLinkFormatter extends FormatterBase {

  public const PLUGIN_ID = 'sms_link';

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode) {
    $element = [];
    foreach ($items as $delta => $item) {
      $text = strip_tags($item->value);
      $element[$delta] = [
        '#type' => 'markup',
        'text' => [
          '#type' => 'markup',
          '#markup' => $text,
          '#prefix' => '<span class="sms-sendtophone-inline">',
          '#suffix' => '</span>',
        ],
        'link' => [
          '#type' => 'link',
          '#prefix' => ' (',
          '#suffix' => ')',
          '#title' => $this->t('Send to phone'),
          '#url' => Url::fromRoute('sms_sendtophone.page', ['type' => 'field'], ['query' => ['text' => $text, 'destination' => \Drupal::destination()->get()]]),
          '#attributes' => [
            'title' => $this->t('Send this text via SMS.'),
            'class' => 'sms-sendtophone',
          ],
        ],
      ];
    }
    return $element;
  }

}
