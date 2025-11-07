<?php

declare(strict_types = 1);

namespace Drupal\entity_share_diff\Diff;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Diff\DiffFormatter as CoreDiffFormatter;

/**
 * Diff formatter which renders a table, with structured padding in HTML.
 */
class DiffFormatter extends CoreDiffFormatter {

  /**
   * Is the Diff supposed to be output in an HTML page?
   *
   * @var bool
   */
  public $htmlOutput = FALSE;

  /**
   * Creates a DiffFormatter to render diffs in a table.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory.
   */
  public function __construct(ConfigFactoryInterface $config_factory) {
    parent::__construct($config_factory);
    $config = $config_factory->get('entity_share_diff.settings');
    $context_settings = $config->get('context');
    if ($context_settings) {
      $this->leading_context_lines = $context_settings['lines_leading'];
      $this->leading_context_lines = $context_settings['lines_trailing'];
    }
  }

  /**
   * {@inheritdoc}
   */
  protected function addedLine($line) {
    $output = parent::addedLine($line);
    return $this->improvePadding($output);
  }

  /**
   * {@inheritdoc}
   */
  protected function deletedLine($line) {
    $output = parent::deletedLine($line);
    return $this->improvePadding($output);
  }

  /**
   * {@inheritdoc}
   */
  protected function contextLine($line) {
    $output = parent::contextLine($line);
    return $this->improvePadding($output);
  }

  /**
   * Helper function: replaces initial plain spaces with HTML spaces in markup.
   *
   * @param array $output
   *   An array representing a table row.
   *
   * @return array
   *   An array representing a table row.
   */
  protected function improvePadding(array $output) {
    if (!$this->htmlOutput) {
      return $output;
    }
    $markup = $output[1]['data']['#markup'];
    $trimmed_markup = ltrim($markup);
    $diff_length = strlen($markup) - strlen($trimmed_markup);
    if ($diff_length > 0) {
      $output[1]['data']['#markup'] = str_repeat('&nbsp;', $diff_length) . $trimmed_markup;
    }
    return $output;
  }

}
