<?php

namespace Drupal\Tests\forms_steps\Functional;

use Drupal\Tests\BrowserTestBase;
use Drupal\Tests\forms_steps\Traits\FormsStepsTestTrait;

/**
 * Functional tests for Forms Steps.
 *
 * @package Drupal\Tests\forms_steps\Unit
 * @coversClass \Drupal\forms_steps\Service\FormsStepsManager
 * @requires module forms_steps
 * @group forms_steps
 */
class FormsStepsNavigationTest extends BrowserTestBase {

  use FormsStepsTestTrait;

  /**
   * Default theme to use for the functional tests.
   *
   * @var string
   */
  protected $defaultTheme = 'stark';

  /**
   * Modules to install.
   *
   * @var array
   */
  protected static $modules = [
    'node',
    'entity_test',
    'field',
    'field_ui',
    'forms_steps',
  ];

  /**
   * Setup the env for current test using trait methods.
   */
  protected function setUp(): void {
    parent::setUp();

    $this->formsStepsSetup();
    $this->formsModesCreation();
  }

  /**
   * Test the navigation in steps.
   */
  public function testNavigation() {
    // @todo Seems that we have a bug in core, new form class not correctly.
    // defined coz of cache.
    drupal_flush_all_caches();

    // Access the step 1.
    $this->drupalGet($this->data['forms_steps']['steps'][1]['url']);

    // Check status code.
    $this->assertSession()->statusCodeEquals(200);

    $this->assertSession()
      ->pageTextContains($this->data['forms_steps']['steps'][1]['label']);

    $value = 'This is an article Test Titre content';
    $this->submitForm([
      'title[0][value]' => $value,
    ], t('Save'));

    // Access step 2.
    $this->assertSession()
      ->pageTextContains($this->data['forms_steps']['steps'][2]['label']);
    $this->assertStringContainsStringIgnoringCase($this->data['forms_steps']['steps'][2]['url'], $this->getUrl());
    $this->assertSession()->pageTextContains($value);

    $value2 = 'This is an article Test Titre content 2';
    $this->submitForm([
      'title[0][value]' => $value2,
    ], t('Save'));

    // Access step 3.
    $this->assertSession()
      ->pageTextContains($this->data['forms_steps']['steps'][3]['label']);
    $this->assertStringContainsStringIgnoringCase($this->data['forms_steps']['steps'][3]['url'], $this->getUrl());

    $value3 = 'This is a page Test Titre content';
    $this->submitForm([
      'title[0][value]' => $value3,
    ], t('Save'));

    // Access step 4.
    $this->assertSession()
      ->pageTextContains($this->data['forms_steps']['steps'][4]['label']);
    $this->assertStringContainsStringIgnoringCase($this->data['forms_steps']['steps'][4]['url'], $this->getUrl());
    $this->assertSession()->fieldExists('title[0][value]');
    $this->assertSession()->buttonExists('Previous');
    $this->assertSession()->fieldValueEquals('title[0][value]', $value2);

    // Access step 3.
    $this->submitForm([], 'Previous');
    $this->assertSession()
      ->pageTextContains($this->data['forms_steps']['steps'][3]['label']);
    $this->assertSession()->fieldValueEquals('title[0][value]', $value3);

    // Access step 2.
    $this->submitForm([], 'Previous');
    $this->assertSession()
      ->pageTextContains($this->data['forms_steps']['steps'][2]['label']);
    $this->assertSession()->fieldValueEquals('title[0][value]', $value2);

    // Access step 1.
    $this->submitForm([], 'Previous');
    $this->assertSession()
      ->pageTextContains($this->data['forms_steps']['steps'][1]['label']);
    $this->assertSession()->fieldValueEquals('title[0][value]', $value2);
    $this->assertSession()->buttonNotExists('Previous');
  }

}
