<?php

namespace Drupal\replication\Normalizer;

use Drupal\serialization\Normalizer\ListNormalizer;

class RevisionInfoItemListNormalizer extends ListNormalizer {

  /**
   * @var string[]
   */
  protected $supportedInterfaceOrClass = ['Drupal\multiversion\Plugin\Field\FieldType\RevisionInfoItemList'];

  /**
   * @var string[]
   */
  protected $format = ['json'];

  /**
   * {@inheritdoc}
   */
  public function normalize($field, $format = NULL, array $context = []) {
    if (!empty($context['query']['revs_info'])) {
      return parent::normalize($field, $format, $context);
    }
    return [];
  }
}
