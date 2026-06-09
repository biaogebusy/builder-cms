<?php

declare(strict_types=1);

namespace Drupal\Tests\ai\FunctionalJavascript;

use Drupal\Tests\ai\FunctionalJavascriptTests\BaseClassFunctionalJavascriptTests;
use Drupal\user\UserInterface;

/**
 * Tests ai_provider_configuration value handling across nesting modes.
 *
 * @group ai
 * @group 3583705
 */
class AiProviderConfigurationElementNestingTest extends BaseClassFunctionalJavascriptTests {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'ai',
    'ai_test',
    'user',
    'system',
  ];

  /**
   * {@inheritdoc}
   */
  protected string $screenshotModuleName = 'ai';

  /**
   * {@inheritdoc}
   */
  protected bool $videoRecording = TRUE;

  /**
   * AI admin user.
   *
   * @var \Drupal\user\UserInterface
   */
  protected UserInterface $aiAdmin;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->setDefaultProvider('chat', 'echoai', 'gpt-test');

    $user = $this->drupalCreateUser([
      'administer ai',
    ]);
    $this->assertNotFalse($user);
    $this->aiAdmin = $user;
  }

  /**
   * Tests all nesting scenarios in one run.
   */
  public function testAllNestingScenarios(): void {
    foreach ($this->getScenarioMatrix() as $scenario) {
      $this->assertScenarioSubmission(
        $scenario['scroll_selector'],
        $scenario['select_selector'],
        $scenario['result_key'],
        $scenario['screenshot_prefix']
      );
    }
  }

  /**
   * Opens the test form and submits one scenario.
   *
   * @param string $scroll_selector
   *   The CSS selector to scroll into view for the scenario.
   * @param string $select_selector
   *   The CSS selector for the provider select.
   * @param string $result_key
   *   The result key rendered by the form after submit.
   * @param string $screenshot_prefix
   *   Prefix used for screenshots.
   */
  protected function assertScenarioSubmission(string $scroll_selector, string $select_selector, string $result_key, string $screenshot_prefix): void {
    $this->drupalLogin($this->aiAdmin);
    $this->drupalGet('/admin/config/ai/test-provider-configuration-values');

    $this->scrollToSelector($scroll_selector);
    $this->takeScreenshot($screenshot_prefix . '_initial');

    $page = $this->getSession()->getPage();
    $select = $page->find('css', $select_selector);
    $this->assertNotNull($select, sprintf('Could not find provider selector "%s".', $select_selector));

    $select->setValue('echoai__gpt-test');
    $this->scrollToSelector($scroll_selector);
    $this->takeScreenshot($screenshot_prefix . '_provider_selected');

    $page->pressButton('Submit');

    $result = $this->assertSession()->waitForElement('css', '#result-' . str_replace('_', '-', $result_key) . ' pre');
    $this->assertNotNull($result, sprintf('Could not find result container for "%s".', $result_key));

    $this->scrollToSelector('#result-' . str_replace('_', '-', $result_key));
    $this->takeScreenshot($screenshot_prefix . '_submitted');

    $result_text = $result->getText();
    $this->assertStringContainsString('[provider] => echoai', $result_text);
    $this->assertStringContainsString('[model] => gpt-test', $result_text);
    $this->assertStringContainsString('[config] => Array', $result_text);
  }

  /**
   * Returns the selector/result mapping for the four nesting scenarios.
   *
   * @return array<int, array<string, string>>
   *   The scenarios to execute.
   */
  protected function getScenarioMatrix(): array {
    return [
      [
        'scroll_selector' => 'select[name="provider_root_no_tree[provider_model]"]',
        'select_selector' => 'select[name="provider_root_no_tree[provider_model]"]',
        'result_key' => 'scenario_1_root_no_tree',
        'screenshot_prefix' => '1_root_without_tree',
      ],
      [
        'scroll_selector' => '#scenario-2',
        'select_selector' => '#scenario-2 select[name$="[provider_model]"]',
        'result_key' => 'scenario_2_root_tree',
        'screenshot_prefix' => '2_root_with_tree',
      ],
      [
        'scroll_selector' => '#scenario-3',
        'select_selector' => '#scenario-3 select[name$="[provider_model]"]',
        'result_key' => 'scenario_3_root_container_no_tree',
        'screenshot_prefix' => '3_root_container_without_tree',
      ],
      [
        'scroll_selector' => '#scenario-4',
        'select_selector' => '#scenario-4 select[name$="[provider_model]"]',
        'result_key' => 'scenario_4_subform_tree',
        'screenshot_prefix' => '4_subform_with_tree',
      ],
    ];
  }

  /**
   * Scrolls a selector into view before taking screenshots.
   *
   * @param string $selector
   *   The CSS selector to bring into view.
   */
  protected function scrollToSelector(string $selector): void {
    $this->getSession()->executeScript(sprintf(
      "document.querySelector(%s)?.scrollIntoView({behavior: 'instant', block: 'start'});",
      json_encode($selector, JSON_THROW_ON_ERROR)
    ));
  }

}
