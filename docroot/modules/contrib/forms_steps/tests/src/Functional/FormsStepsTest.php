<?php

namespace Drupal\Tests\forms_steps\Functional;

use Drupal\Tests\BrowserTestBase;
use Drupal\Tests\forms_steps\Traits\FormsStepsTestTrait;
use Drupal\user\Entity\Role;
use Drupal\Core\Url;

/**
 * Tests for the Forms Steps module.
 *
 * @group forms_steps
 * @runInSeparateProcess
 */
class FormsStepsTest extends BrowserTestBase {

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
    'field',
    'field_ui',
    'forms_steps',
  ];

  /**
   * Perform initial setup tasks that run before every test method.
   */
  public function setUp(): void {
    parent::setUp();

    $this->formsStepsSetup();
  }

  /**
   * Tests the creation and use of a form step.
   */
  public function testFormsSteps() {
    // Create page content type.
    $this->createContentType([
      'type' => 'page',
    ]);

    // Creation of all form display modes.
    foreach ($this->data['form_display_modes'] as $form_display_mode) {
      // Access form mode add page.
      $this->drupalGet(
        Url::fromRoute(
          'entity.entity_form_mode.add_form',
          ['entity_type_id' => 'node']
        )
      );
      $this->assertSession()->statusCodeEquals(200);
      // Add a form mode.
      $this->submitForm([
        'label' => $form_display_mode['label'],
        'id' => $form_display_mode['id'],
      ], t('Save'));

      $this->assertSession()->pageTextContains(
        'Saved the ' . $form_display_mode['label'] . ' form mode.'
      );

      Role::load($this->user->getRoles()[1])
        ->grantPermission('use node.' . $form_display_mode['id'] . ' form mode')
        ->save();
    }

    // Create Article content type.
    $this->createContentType([
      'type' => 'article',
    ]);

    Role::load($this->user->getRoles()[1])
      ->grantPermission("administer nodes")
      ->grantPermission("create article content")
      ->grantPermission("create page content")
      ->grantPermission("edit any article content")
      ->grantPermission("edit any page content")
      ->save();

    // Access article's form display page.
    $this->drupalGet(
      Url::fromRoute(
        'entity.entity_form_display.node.default',
        ['node_type' => 'article']
      )
    );
    $this->assertSession()->statusCodeEquals(200);

    // Activate Test Form Modes as a custom display mode.
    foreach ($this->data['form_display_modes'] as $form_display_mode) {
      $this->submitForm([
        "display_modes_custom[${form_display_mode['id']}]" => $form_display_mode['id'],
      ], t('Save'));

      $this->assertSession()->pageTextContains(
        "The ${form_display_mode['label']} mode now uses custom display settings."
      );
    }

    // @todo Seems that we have a bug in core, new form class not correctly.
    // defined coz of cache.
    drupal_flush_all_caches();

    // Configure the visible fields.
    $this->drupalGet(Url::fromRoute('entity.entity_form_display.node.form_mode', [
      'node_type' => 'article',
      'form_mode_name' => $form_display_mode['id'],
    ]));
    $this->assertSession()->statusCodeEquals(200);
    $this->submitForm([
      'fields[title][region]' => 'content',
      'fields[body][region]' => 'hidden',
      'fields[status][region]' => 'hidden',
      'fields[uid][region]' => 'hidden',
      'fields[created][region]' => 'hidden',
      'fields[promote][region]' => 'hidden',
      'fields[sticky][region]' => 'hidden',
    ], t('Save'));

    // Access forms steps add page.
    $this->drupalGet(Url::fromRoute('entity.forms_steps.add_form'));
    $this->assertSession()->statusCodeEquals(200);
    // Test the creation of a form step.
    $this->submitForm([
      'label' => $this->data['forms_steps']['label'],
      'id' => $this->data['forms_steps']['id'],
      'description' => $this->data['forms_steps']['description'],
    ], t('Save'));
    $this->assertSession()->pageTextContains(
      'Forms Steps ' . $this->data['forms_steps']['label'] . ' has been created.'
    );

    // Perform steps creation.
    foreach ($this->data['forms_steps']['steps'] as $step) {
      // Access step add page of the form step.
      $this->drupalGet(
        Url::fromRoute(
          'entity.forms_steps.add_step_form',
          ['forms_steps' => 'test_form_step']
        )
      );
      $this->assertSession()->statusCodeEquals(200);

      // Test the creation of an add step.
      $this->submitForm([
        'label' => $step['label'],
        'id' => $step['id'],
        'target_form_mode' => $step['target_form_mode'],
        'target_entity_type' => $step['target_entity_type'],
        'target_entity_bundle' => $step['target_entity_bundle'],
        'url' => $step['url'],
      ], t('Save'));

      $this->assertSession()->pageTextContains(
        'Created ' . $step['label'] . ' step.'
      );
    }

    // @todo Seems that we have a bug in core, new form class not correctly.
    // defined coz of cache.
    drupal_flush_all_caches();

    // Test the flow/
    // Access the step 1.
    $this->drupalGet($this->data['forms_steps']['steps'][1]['url']);

    // Check status code.
    $this->assertSession()->statusCodeEquals(200);

    $this->assertSession()
      ->pageTextContains($this->data['forms_steps']['steps'][1]['label']);

    $value = 'This is a Test Titre content';
    $this->submitForm([
      'title[0][value]' => $value,
    ], t('Save'));

    // Access step 2.
    $this->assertSession()
      ->pageTextContains($this->data['forms_steps']['steps'][2]['label']);
    $this->assertStringContainsStringIgnoringCase($this->data['forms_steps']['steps'][2]['url'], $this->getUrl());
    $this->assertSession()->pageTextContains($value);
  }

}
