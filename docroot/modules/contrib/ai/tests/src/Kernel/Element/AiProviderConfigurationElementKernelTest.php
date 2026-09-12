<?php

declare(strict_types=1);

namespace Drupal\Tests\ai\Kernel\Element;

use Drupal\Core\Form\FormInterface;
use Drupal\Core\Form\FormState;
use Drupal\Core\Form\FormStateInterface;
use Drupal\KernelTests\KernelTestBase;
use Drupal\ai\Element\AiProviderConfiguration;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Kernel tests for the ai_provider_configuration element's config wrapper.
 *
 * Covers the fix for https://www.drupal.org/i/3586636: the "Configuration"
 * details wrapper must collapse to a plain container whenever there are no
 * configuration fields to show, while keeping the AJAX wrapper HTML id
 * intact so a later selection can still rebuild it.
 *
 * @coversDefaultClass \Drupal\ai\Element\AiProviderConfiguration
 *
 * @group ai
 * @group 3586636
 */
#[RunTestsInSeparateProcesses]
class AiProviderConfigurationElementKernelTest extends KernelTestBase implements FormInterface {

  /**
   * Modules to enable.
   *
   * @var array
   */
  protected static $modules = [
    'ai',
    'ai_test',
    'key',
    'file',
    'user',
    'field',
    'system',
  ];

  /**
   * The #operation_type used for the element under test.
   */
  protected string $operationType = 'chat';

  /**
   * The #default_value used for the element under test, if any.
   */
  protected ?array $elementDefaultValue = NULL;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installConfig(['ai', 'ai_test']);
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'ai_provider_configuration_element_kernel_test_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $form['provider_config'] = [
      '#type' => 'ai_provider_configuration',
      '#title' => 'AI provider',
      '#operation_type' => $this->operationType,
    ];
    if ($this->elementDefaultValue !== NULL) {
      $form['provider_config']['#default_value'] = $this->elementDefaultValue;
    }
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state): void {
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
  }

  /**
   * Builds the form and returns the processed ai_provider_configuration.
   *
   * @return array
   *   The processed element.
   */
  protected function buildElement(): array {
    $form_state = new FormState();
    $form = \Drupal::formBuilder()->buildForm($this, $form_state);
    $this->assertArrayHasKey('config', $form['provider_config'], 'The element has a config wrapper.');
    return $form['provider_config'];
  }

  /**
   * Asserts the config wrapper is collapsed to an empty container.
   *
   * @param array $config
   *   The config wrapper sub-element.
   */
  protected function assertCollapsedWrapper(array $config): void {
    $this->assertSame('container', $config['#type'], 'The config wrapper is collapsed to a container.');
    $this->assertArrayNotHasKey('#title', $config, 'The collapsed wrapper has no title.');
    $this->assertArrayNotHasKey('#open', $config, 'The collapsed wrapper has no open flag.');
    $this->assertSame('edit-provider_config-config', $config['#attributes']['id'], 'The collapsed wrapper keeps the AJAX wrapper HTML id.');
    $this->assertArrayNotHasKey('max_tokens', $config, 'The collapsed wrapper contains no configuration fields.');
  }

  /**
   * A provider with configuration options keeps the details wrapper.
   */
  public function testConfiguredProviderKeepsDetailsWrapper(): void {
    $this->operationType = 'chat';
    $this->elementDefaultValue = [
      'use_default' => FALSE,
      'provider' => 'echoai',
      'model' => 'gpt-test',
      'config' => [],
    ];
    $config = $this->buildElement()['config'];
    $this->assertSame('details', $config['#type'], 'The config wrapper stays a details element.');
    $this->assertArrayHasKey('#title', $config, 'The details wrapper keeps its title.');
    $this->assertArrayHasKey('max_tokens', $config, 'The chat configuration fields are present.');
    $this->assertArrayHasKey('temperature', $config, 'The chat configuration fields are present.');
  }

  /**
   * A provider without configuration options collapses the wrapper.
   *
   * The ai_test EchoProvider has no configuration block for the rerank
   * operation type in its api_defaults.yml.
   */
  public function testEmptySchemaCollapsesWrapper(): void {
    $this->operationType = 'rerank';
    $this->elementDefaultValue = [
      'use_default' => FALSE,
      'provider' => 'echoai',
      'model' => 'gpt-test',
      'config' => [],
    ];
    $this->assertCollapsedWrapper($this->buildElement()['config']);
  }

  /**
   * No selection and no site-wide default collapses the wrapper.
   */
  public function testNoSelectionAndNoDefaultProviderCollapsesWrapper(): void {
    $this->operationType = 'chat';
    $this->elementDefaultValue = NULL;
    $this->assertCollapsedWrapper($this->buildElement()['config']);
  }

  /**
   * A default provider that cannot be instantiated collapses the wrapper.
   *
   * A stale entry in ai.settings default_providers (e.g. the provider module
   * was uninstalled) makes createInstance() throw, which must not leave an
   * empty "Configuration" details box behind.
   */
  public function testUnavailableDefaultProviderCollapsesWrapper(): void {
    $this->config('ai.settings')
      ->set('default_providers.chat', [
        'provider_id' => 'missing_provider',
        'model_id' => 'missing_model',
      ])
      ->save();
    $this->operationType = 'chat';
    $this->elementDefaultValue = NULL;
    $this->assertCollapsedWrapper($this->buildElement()['config']);
  }

  /**
   * The AJAX rebuild keeps the wrapper id when collapsing.
   *
   * The container built from scratch in ajaxCallback() has no #array_parents,
   * so core's container preprocessing does not copy #id into the attributes;
   * the element must set the HTML id explicitly or the AJAX wrapper target
   * disappears from the DOM.
   */
  public function testAjaxCallbackPreservesWrapperIdWhenCollapsed(): void {
    $this->operationType = 'chat';
    $this->elementDefaultValue = NULL;

    $form_state = new FormState();
    $form = \Drupal::formBuilder()->buildForm($this, $form_state);

    $form_state->setUserInput(['provider_config' => ['provider_model' => '']]);
    $form_state->setTriggeringElement($form['provider_config']['provider_model']);
    $result = AiProviderConfiguration::ajaxCallback($form, $form_state);

    $this->assertSame('container', $result['#type'], 'The rebuilt wrapper is collapsed to a container.');
    $this->assertSame('edit-provider_config-config', $result['#attributes']['id'], 'The collapsed wrapper keeps the AJAX wrapper HTML id.');
  }

  /**
   * The AJAX rebuild restores the details wrapper for a configurable model.
   */
  public function testAjaxCallbackRebuildsDetailsWithFields(): void {
    $this->operationType = 'chat';
    $this->elementDefaultValue = NULL;

    $form_state = new FormState();
    $form = \Drupal::formBuilder()->buildForm($this, $form_state);

    $form_state->setUserInput(['provider_config' => ['provider_model' => 'echoai__gpt-test']]);
    $form_state->setTriggeringElement($form['provider_config']['provider_model']);
    $result = AiProviderConfiguration::ajaxCallback($form, $form_state);

    $this->assertSame('details', $result['#type'], 'The rebuilt wrapper is a details element.');
    $this->assertSame('edit-provider_config-config', $result['#id'], 'The details wrapper keeps the AJAX wrapper id.');
    $this->assertArrayHasKey('max_tokens', $result, 'The chat configuration fields are rebuilt.');
  }

}
