<?php

namespace Drupal\Tests\layout_builder_at\Functional;

/**
 * Layout Builder Asymmetric Translations install tests.
 *
 * @group layout_builder_at
 */
class LayoutBuilderAtInstallTest extends LayoutBuilderAtBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'block',
    'content_translation',
    'entity_test',
    'field_ui',
    'layout_builder',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->setUpViewDisplay();
    $this->setUpEntities();

    // No enable our module. hook_install() will make the field translatable.
    \Drupal::service('module_installer')->install(['layout_builder_at']);
    $this->resetAll();
    $this->rebuildContainer();
    $this->container->get('module_handler')->reload();
  }

  /**
   * Tests that existing fields become translatable on install of the module.
   *
   * @throws \Behat\Mink\Exception\ExpectationException
   * @throws \Behat\Mink\Exception\ResponseTextException
   */
  public function testInstall() {
    $assert_session = $this->assertSession();

    $this->drupalGet('entity_test_mul/structure/entity_test_mul/display/default/layout');

    // Make layout builder field translatable.
    $this->drupalGet('admin/config/regional/content-language');
    $edit = [
      'settings[entity_test_mul][entity_test_mul][fields][layout_builder__layout]' => TRUE,
    ];
    $assert_session->pageTextNotContains('Layout Builder does not support translating layouts.');
    $this->submitForm($edit, 'Save configuration');
  }

}
