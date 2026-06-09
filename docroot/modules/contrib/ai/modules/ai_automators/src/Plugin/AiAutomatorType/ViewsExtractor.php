<?php

namespace Drupal\ai_automators\Plugin\AiAutomatorType;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\ai_automators\Attribute\AiAutomatorType;
use Drupal\ai_automators\PluginBaseClasses\ViewsToText;
use Drupal\ai_automators\PluginInterfaces\AiAutomatorTypeInterface;

/**
 * Views extraction automator.
 */
#[AiAutomatorType(
  id: 'views_extract_text_long',
  label: new TranslatableMarkup('Views: Text'),
  field_rule: 'string_long',
  target: '',
)]
class ViewsExtractor extends ViewsToText implements AiAutomatorTypeInterface {

  /**
   * {@inheritDoc}
   */
  public $title = 'Views: Text';

}
