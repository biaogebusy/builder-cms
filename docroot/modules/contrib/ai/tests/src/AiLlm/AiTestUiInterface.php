<?php

namespace Drupal\Tests\ai\AiLlm;

use Drupal\Core\Form\FormStateInterface;

/**
 * An interface for tests that can be modified and run via the AI Test UI.
 *
 * @phpstan-require-extends \Drupal\Tests\ai\AiLlm\AiProviderTestBase
 */
interface AiTestUiInterface {

  /**
   * The model agnostic data provider.
   *
   * @param string|null $model
   *   The model to generate test cases for.
   *
   * @return \Generator<int|string, array>
   *   The test cases.
   */
  public static function dataProvider(?string $model): \Generator;

  /**
   * The configuration form for the AI Test UI.
   *
   * @param array<int, string> $config
   *   The existing config (may be empty).
   *
   * @return array<string, mixed>
   *   The config for elements.
   */
  public static function getRunConfigForm(array $config): array;

  /**
   * Extract the run data fom the submitted form values.
   *
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   *
   * @return array<int, string>
   *   The run data.
   */
  public static function getSubmittedRunData(FormStateInterface $form_state): array;

}
