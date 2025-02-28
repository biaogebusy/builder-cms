<?php

namespace Drupal\entity_theme_engine\Normalizer;


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
    return $data;
  }

}
