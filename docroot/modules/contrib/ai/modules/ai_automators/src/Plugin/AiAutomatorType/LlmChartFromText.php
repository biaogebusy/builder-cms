<?php

namespace Drupal\ai_automators\Plugin\AiAutomatorType;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\ai_automators\Attribute\AiAutomatorType;
use Drupal\ai_automators\PluginBaseClasses\Chart;

/**
 * The rules for an charts field.
 */
#[AiAutomatorType(
  id: 'llm_chart_from_text',
  label: new TranslatableMarkup('LLM: Chart From Text'),
  field_rule: 'chart_config',
  target: '',
)]
class LlmChartFromText extends Chart {


  /**
   * {@inheritDoc}
   */
  public $title = 'LLM: Chart From Text';

}
