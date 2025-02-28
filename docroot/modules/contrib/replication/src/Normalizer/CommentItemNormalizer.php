<?php

namespace Drupal\replication\Normalizer;

use \Drupal\serialization\Normalizer\FieldItemNormalizer;

class CommentItemNormalizer extends FieldItemNormalizer  {

  /**
   * The interface or class that this Normalizer supports.
   *
   * @var string
   */
  protected $supportedInterfaceOrClass = 'Drupal\comment\Plugin\Field\FieldType\CommentItem';

  /**
   * @var string[]
   */
  protected $format = ['json'];

  /**
   * {@inheritdoc}
   */
  public function normalize($field, $format = NULL, array $context = []) {
    $value = $field->getValue();
    $field_info = [];
    $reference_fields = ['cid', 'last_comment_uid'];
    foreach ($value as $key => $item) {
      if (in_array($key, $reference_fields) && is_numeric($item)) {
        $field_info[$key] = NULL;
      }
      else {
        $field_info[$key] = $item;
      }
    }

    return $field_info;
  }

}
