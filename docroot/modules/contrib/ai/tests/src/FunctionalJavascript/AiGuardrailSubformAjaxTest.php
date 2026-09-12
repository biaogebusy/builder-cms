<?php

declare(strict_types=1);

namespace Drupal\Tests\ai\FunctionalJavascript;

use Drupal\Tests\ai\FunctionalJavascriptTests\BaseClassFunctionalJavascriptTests;
use Drupal\user\UserInterface;

/**
 * Tests that the guardrail settings subform loads over AJAX.
 *
 * Selecting a guardrail on the add form triggers an AJAX rebuild of the
 * settings wrapper. That rebuild writes the form and form state to the form
 * cache, which serializes the selected guardrail plugin. Guardrails holding
 * injected services used to make that write fail, so the subform never
 * appeared and the guardrail could not be configured at all.
 *
 * @see \Drupal\ai\Guardrail\AiGuardrailPluginBase
 * @see \Drupal\ai\Form\AiGuardrailForm
 *
 * @group ai
 * @group 3586620
 * @group 3586676
 *
 * @runTestsInSeparateProcesses
 */
class AiGuardrailSubformAjaxTest extends BaseClassFunctionalJavascriptTests {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'ai',
    'ai_test',
    'key',
    'file',
    'system',
    'user',
  ];

  /**
   * {@inheritdoc}
   */
  protected bool $videoRecording = TRUE;

  /**
   * Guardrail administrator.
   *
   * @var \Drupal\user\UserInterface
   */
  protected UserInterface $guardrailAdmin;

  /**
   * Guardrails to exercise, keyed by plugin ID.
   *
   * The value is a settings field that must appear in the subform once the
   * AJAX rebuild has completed, proving the subform actually rendered rather
   * than coming back empty.
   *
   * @var array<string, string>
   */
  protected const GUARDRAILS = [
    'input_length_limit' => 'guardrail_settings[max_length]',
    'restrict_to_topic' => 'guardrail_settings[valid_topics]',
    'regexp_guardrail' => 'guardrail_settings[regexp_pattern]',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $user = $this->drupalCreateUser([
      'administer guardrails',
      'administer ai',
      'access administration pages',
    ]);
    $this->assertNotFalse($user, 'The guardrail admin user should be created.');
    $this->guardrailAdmin = $user;

    // RestrictToTopic lists provider models in its subform, so give it a
    // provider to enumerate.
    $this->setDefaultProvider('chat', 'echoai', 'gpt-test');
  }

  /**
   * Selecting a guardrail loads its settings subform over AJAX.
   */
  public function testSubformLoadsForEachGuardrail(): void {
    $this->drupalLogin($this->guardrailAdmin);

    $assert = $this->assertSession();
    $page = $this->getSession()->getPage();

    $step = 1;
    foreach (self::GUARDRAILS as $plugin_id => $settings_field) {
      // Reload the form each time so every guardrail is selected from a clean
      // state rather than on top of the previous AJAX rebuild.
      $this->drupalGet('admin/config/ai/guardrails/add');
      $this->takeScreenshot(sprintf('%d_%s_form_loaded', $step, $plugin_id));

      $assert->selectExists('guardrail');
      $page->selectFieldOption('guardrail', $plugin_id);

      // The AJAX rebuild is what serializes the plugin into the form cache.
      $assert->assertWaitOnAjaxRequest();
      $this->takeScreenshot(sprintf('%d_%s_after_ajax', $step, $plugin_id));

      // A serialization failure surfaces as a WSOD or an AJAX error rather
      // than a rendered subform, so assert the field is really there.
      $field = $assert->waitForField($settings_field);
      $this->assertNotEmpty(
        $field,
        sprintf(
          'The %s guardrail settings subform must render %s after the AJAX rebuild.',
          $plugin_id,
          $settings_field
        )
      );

      // Drupal renders AJAX failures into a message region; none is expected.
      $assert->pageTextNotContains('An error occurred');
      $assert->pageTextNotContains('The website encountered an unexpected error');

      $step++;
    }
  }

  /**
   * Picking a different guardrail replaces the settings subform.
   *
   * The entity memoizes its plugin instance. If that memo is not invalidated
   * when the selected plugin ID changes, the first choice sticks and every
   * later selection keeps showing the previous guardrail's settings.
   */
  public function testSwitchingGuardrailReplacesSubform(): void {
    $this->drupalLogin($this->guardrailAdmin);

    $assert = $this->assertSession();
    $page = $this->getSession()->getPage();

    $this->drupalGet('admin/config/ai/guardrails/add');
    $this->takeScreenshot('1_form_loaded');

    // First selection.
    $page->selectFieldOption('guardrail', 'regexp_guardrail');
    $assert->assertWaitOnAjaxRequest();
    $this->assertNotEmpty(
      $assert->waitForField('guardrail_settings[regexp_pattern]'),
      'The first guardrail subform should render.'
    );
    $this->takeScreenshot('2_regexp_subform');

    // Switch to a different guardrail.
    $page->selectFieldOption('guardrail', 'input_length_limit');
    $assert->assertWaitOnAjaxRequest();
    $this->assertNotEmpty(
      $assert->waitForField('guardrail_settings[max_length]'),
      'The subform must switch to the newly selected guardrail.'
    );
    $this->takeScreenshot('3_length_subform');

    // The previous guardrail's settings must be gone, not merely hidden.
    $assert->fieldNotExists('guardrail_settings[regexp_pattern]');

    // Switch a third time to be sure it keeps following the selection.
    $page->selectFieldOption('guardrail', 'restrict_to_topic');
    $assert->assertWaitOnAjaxRequest();
    $this->assertNotEmpty(
      $assert->waitForField('guardrail_settings[valid_topics]'),
      'The subform must keep following further selections.'
    );
    $assert->fieldNotExists('guardrail_settings[max_length]');
    $this->takeScreenshot('4_topic_subform');
  }

  /**
   * A guardrail configured through the subform saves and reopens correctly.
   *
   * Covers the full round trip: the AJAX-loaded subform is submitted, the
   * entity is saved, and the edit form rebuilds the subform from stored
   * configuration.
   */
  public function testGuardrailSavesAndReopens(): void {
    $this->drupalLogin($this->guardrailAdmin);

    $assert = $this->assertSession();
    $page = $this->getSession()->getPage();

    $this->drupalGet('admin/config/ai/guardrails/add');
    $this->takeScreenshot('1_add_form');

    $page->fillField('label', 'Length guard');
    // The machine name element derives the ID from the label in JavaScript.
    $this->assertNotEmpty(
      $assert->waitForText('length_guard'),
      'The machine name should be derived from the label.'
    );

    $page->selectFieldOption('guardrail', 'input_length_limit');
    $assert->assertWaitOnAjaxRequest();
    $this->takeScreenshot('2_subform_loaded');

    $max_length = $assert->waitForField('guardrail_settings[max_length]');
    $this->assertNotEmpty($max_length, 'The max_length setting must render.');
    $max_length->setValue('123');
    $this->takeScreenshot('3_subform_filled');

    $page->pressButton('Save');
    $assert->waitForText('Length guard');
    $this->takeScreenshot('4_saved');

    // Reopening the saved guardrail must rebuild the subform with the value.
    $this->drupalGet('admin/config/ai/guardrails/length_guard');
    $this->takeScreenshot('5_edit_form');

    $assert->fieldValueEquals('guardrail_settings[max_length]', '123');
  }

}
