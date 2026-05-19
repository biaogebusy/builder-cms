<?php

namespace Drupal\simple_oauth_test\Plugin\ScopeGranularity;

use Drupal\Component\Plugin\Exception\PluginException;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\simple_oauth\Attribute\ScopeGranularity;
use Drupal\simple_oauth\Plugin\ScopeGranularityBase;

/**
 * The role scope granularity plugin.
 *
 * It grants access based on the string length of the permission.
 */
#[ScopeGranularity(
  'test',
  new TranslatableMarkup('Test'),
)]
class TestGranularity extends ScopeGranularityBase {

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
      'min_length' => NULL,
      'max_length' => NULL,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function validateConfiguration(array $configuration): void {
    if (!isset($configuration['min_length'])) {
      throw new PluginException('The "min_length" configuration value is required.');
    }
    if ($configuration['min_length'] < 1) {
      throw new PluginException('The "min_length" configuration value must be larger than 0.');
    }
    if (isset($configuration['max_length']) && $configuration['max_length'] < 1) {
      throw new PluginException('The "max_length" configuration value must be larger than 0.');
    }
  }

  /**
   * {@inheritdoc}
   */
  public function hasPermission(string $permission): bool {
    $length = strlen($permission);
    if ($length < $this->configuration['min_length']) {
      return FALSE;
    }
    if (isset($this->configuration['max_length']) && ($length > $this->configuration['max_length'])) {
      return FALSE;
    }
    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function getPermissions(): array {
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $form['min_length'] = [
      '#type' => 'number',
      '#title' => $this->t('Minimum length'),
      '#description' => $this->t('The minimum length of the permission string to grant access'),
      '#default_value' => $this->configuration['min_length'],
      '#min' => 1,
      '#required' => TRUE,
    ];
    $form['max_length'] = [
      '#type' => 'number',
      '#title' => $this->t('Maximum length'),
      '#description' => $this->t('The maximum length of the permission string to grant access'),
      '#default_value' => $this->configuration['max_length'],
      '#min' => 1,
    ];
    return $form;
  }

}
