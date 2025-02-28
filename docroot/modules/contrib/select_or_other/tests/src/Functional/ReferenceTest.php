<?php

namespace Drupal\Tests\select_or_other\Functional;

/**
 * Tests the the functionality of the Reference widget.
 *
 * @group select_or_other
 */
class ReferenceTest extends TestBase {

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  public function setUp(): void {
    parent::setUp();
    $field_settings = ['target_type' => 'taxonomy_term'];
    $widget = 'select_or_other_reference';
    $widgets = ['select_or_other_select', 'select_or_other_buttons'];
    $this->prepareTestFields('entity_reference', $field_settings, $widget, $widgets);
    $this->defaultPermissions[] = 'administer taxonomy';
    $user = $this->drupalCreateUser($this->defaultPermissions);
    $this->drupalLogin($user);
  }

  /**
   * Make sure an empty option is present when relevant.
   */
  public function testEmptyOption($empty_option = '') {
    parent::testEmptyOption('My cool new value');
  }

}
