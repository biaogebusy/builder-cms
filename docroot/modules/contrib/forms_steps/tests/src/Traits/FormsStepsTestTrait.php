<?php

namespace Drupal\Tests\forms_steps\Traits;

use Drupal\user\Entity\Role;
use Drupal\Core\Url;

/**
 * Trait to help tests setup and operations.
 *
 * @package Drupal\Tests\forms_steps\Traits
 */
trait FormsStepsTestTrait {

  /**
   * Data used to build the test.
   *
   * @var array
   */
  private $data;

  /**
   * A simple user.
   *
   * @var object
   */
  private $user;

  /**
   * Perform initial setup tasks that run before every test method.
   */
  public function formsStepsSetup() {
    $permissions = [
      'administer forms_steps',
      'administer content types',
      'administer nodes',
      'administer forms_steps',
      'administer display modes',
      'administer node fields',
      'administer node display',
      'administer node form display',
    ];

    $this->data = [
      'form_display_modes' => [
        1 => [
          'label' => 'Test Form Mode',
          'id' => 'test_form_mode',
        ],
      ],
      'forms_steps' => [
        'label' => 'Test Form Step',
        'id' => 'test_form_step',
        'description' => 'Test Form Step description',
        'steps' => [
          1 => [
            'label' => 'Add Test Step 1',
            'id' => 'add_test_step_article',
            'target_form_mode' => 'default',
            'target_entity_type' => 'node',
            'target_entity_bundle' => 'article',
            'url' => '/my_test_form/step_1',
            'previous' => NULL,
          ],
          2 => [
            'label' => 'Edit Test Step 2',
            'id' => 'edit_test_step_article',
            'target_form_mode' => 'node.test_form_mode',
            'target_entity_type' => 'node',
            'target_entity_bundle' => 'article',
            'url' => '/my_test_form/step_2',
            'previous' => 'Previous',
          ],
          3 => [
            'label' => 'Add Test Step 3',
            'id' => 'add_test_step_page',
            'target_form_mode' => 'default',
            'target_entity_type' => 'node',
            'target_entity_bundle' => 'page',
            'url' => '/my_test_form/step_3',
            'previous' => 'Previous',
          ],
          4 => [
            'label' => 'Edit Test Step 4',
            'id' => 'edit_test_step_article_bis',
            'target_form_mode' => 'node.test_form_mode',
            'target_entity_type' => 'node',
            'target_entity_bundle' => 'article',
            'url' => '/my_test_form/step_4',
            'previous' => 'Previous',
          ],
        ],
      ],
    ];

    $this->checkPermissions($permissions);
    $this->user = $this->drupalCreateUser($permissions);

    // Login.
    $this->drupalLogin($this->user);
  }

  /**
   * Creation of the forms modes.
   */
  public function formsModesCreation() {

    // Creation of the page node.
    $this->createContentType(
      [
        'type' => 'page',
      ]
    );

    // Creation of all form display modes.
    foreach ($this->data['form_display_modes'] as $form_display_mode) {
      // Access form mode add page.
      $this->drupalGet(
        Url::fromRoute(
          'entity.entity_form_mode.add_form',
          ['entity_type_id' => 'node']
        )
      );

      // Add a form mode.
      $this->submitForm([
        'label' => $form_display_mode['label'],
        'id' => $form_display_mode['id'],
      ], t('Save'));

      Role::load($this->user->getRoles()[1])
        ->grantPermission('use node.' . $form_display_mode['id'] . ' form mode')
        ->save();
    }

    // Create Article content type.
    $this->createContentType(
      [
        'type' => 'article',
      ]
    );

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

    // Activate Test Form Modes as a custom display mode.
    foreach ($this->data['form_display_modes'] as $form_display_mode) {
      $this->submitForm([
        "display_modes_custom[${form_display_mode['id']}]" => $form_display_mode['id'],
      ], t('Save'));
    }

    // Configure the visible fields.
    $this->drupalGet(
      Url::fromRoute(
        'entity.entity_form_display.node.form_mode',
        [
          'node_type' => 'article',
          'form_mode_name' => $form_display_mode['id'],
        ]
      )
    );

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

    // Test the creation of a form step.
    $this->submitForm([
      'label' => $this->data['forms_steps']['label'],
      'id' => $this->data['forms_steps']['id'],
      'description' => $this->data['forms_steps']['description'],
    ], t('Save'));

    // Perform steps creation.
    foreach ($this->data['forms_steps']['steps'] as $step) {
      // Access step add page of the form step.
      $this->drupalGet(
        Url::fromRoute(
          'entity.forms_steps.add_step_form',
          [
            'forms_steps' => $this->data['forms_steps']['id'],
          ]
        )
      );

      // Test the creation of an add step.
      $this->submitForm([
        'label' => $step['label'],
        'id' => $step['id'],
        'target_form_mode' => $step['target_form_mode'],
        'target_entity_bundle' => $step['target_entity_bundle'],
        'target_entity_type' => $step['target_entity_type'],
        'url' => $step['url'],
      ], t('Save'));

      if (!is_null($step['previous'])) {
        $this->drupalGet(Url::fromRoute(
          'entity.forms_steps.edit_step_form',
          [
            'forms_steps' => $this->data['forms_steps']['id'],
            'forms_steps_step' => $step['id'],
          ]
        ));
        // Update step with previous label.
        $this->submitForm([
          'display_previous' => TRUE,
          'previous_label' => $step['previous'],
        ], t('Save'));
      }
    }
  }

}
