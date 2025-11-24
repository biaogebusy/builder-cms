<?php

namespace Drupal\entity_theme_engine\Normalizer;

use Drupal\filter\Plugin\FilterInterface;

class TextItemNormalizer extends FieldItemNormalizer {

  /**
   * The interface or class that this Normalizer supports.
   *
   * @var string
   */
  protected $supportedInterfaceOrClass = ['Drupal\text\Plugin\Field\FieldType\TextItemBase','Drupal\Core\Field\Plugin\Field\FieldType\StringItemBase'];

  /**
   * {@inheritdoc}
   */
  public function normalize($field, $format = NULL, array $context = []) {
    $data = parent::normalize($field, $format, $context);
    $data['render'] = [
      '#type' => 'processed_text',
      '#text' => $data['value'],
      '#format' => isset($data['format'])?$data['format']:NULL,
      '#langcode' => $field->getLangcode(),
    ];
    if(isset($context['#no_render']) && $context['#no_render']) {
      if (isset($data['format'])) {
        /** @var \Drupal\filter\Entity\FilterFormat $format **/
        $format = \Drupal\filter\Entity\FilterFormat::load($data['format']);
        if ($format) {
          $text = $data['value'];
          $filters = $format->filters();
          $enable_filters = ['entity_embed', 'entity_link'];
          foreach ($filters as $filter) {
            if (in_array($filter->getPluginId(), $enable_filters)) {
              $result = $filter->process($text, $field->getLangcode());
              $text = (string)$result->getProcessedText();
            }
          }
          $data['value'] = $text;
          $data['value'] = str_replace("\r\n", '', $data['value']);
          $data['value'] = str_replace("\n", ' ', $data['value']);
        }
      }
    }
    return $data;
  }

}
