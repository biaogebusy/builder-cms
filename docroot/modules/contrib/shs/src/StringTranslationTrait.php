<?php

namespace Drupal\shs;

use Drupal\Core\StringTranslation\StringTranslationTrait as StringTranslationTraitCore;

/**
 * Custom enhancements for the StringTranslationTrait.
 *
 * Using this trait will override the core functions t() and formatPlural() and
 * adds a default context. To override the context, set a protected variable
 * named $translationContext in your class.
 *
 * @see \Drupal\Core\StringTranslation\StringTranslationTrait
 *
 * @ingroup i18n
 */
trait StringTranslationTrait {

  /**
   * Default string translation context.
   *
   * @var string
   */
  protected $translationContext = 'shs';

  use StringTranslationTraitCore {
    t as tCore;
    formatPlural as formatPluralCore;
  }

  /**
   * {@inheritdoc}
   */
  protected function t($string, array $args = [], array $options = []) {
    if (empty($options['context'])) {
      $options['context'] = $this->translationContext;
    }

    return $this->tCore($string, $args, $options);
  }

  /**
   * {@inheritdoc}
   */
  protected function formatPlural($count, $singular, $plural, array $args = [], array $options = []) {
    if (empty($options['context'])) {
      $options['context'] = $this->translationContext;
    }

    return $this->formatPluralCore($count, $singular, $plural, $args, $options);
  }

}
