<?php

namespace Drupal\Tests\field_permissions\Functional;

use Drupal\Tests\node\Functional\NodeTestBase;

/**
 * Tests the field config edit form.
 *
 * @group field_permissions
 */
class FieldPermissionsFieldConfigEditFormTest extends NodeTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'field_permissions_test',
    'field_permissions',
    'node',
    'datetime',
    'field_ui',
  ];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * Tests that plugins can opt-out for a given field.
   *
   * @covers \Drupal\field_permissions\Plugin\FieldPermissionType\Base::appliesToField
   */
  public function testAppliesToField(): void {
    $assert = $this->assertSession();

    $this->drupalLogin($this->createUser([
      'administer field permissions',
      'bypass node access',
      'administer content types',
      'administer node fields',
    ]));
    $this->drupalGet('/admin/structure/types/manage/page/fields/node.page.body');

    // All plugins are exposed on the field config edit form.
    $assert->pageTextContains('Field visibility and permissions');
    $assert->fieldExists('Not set');
    $assert->fieldExists('Private');
    $assert->fieldExists('Test type');
    $assert->fieldExists('Custom permissions');

    // Allow 'test_access' plugin to opt-out.
    \Drupal::state()->set('field_permissions_test.applies_to_field', FALSE);
    $this->getSession()->reload();

    // Check that 'test_access' is not exposed anymore on the form.
    $assert->fieldExists('Not set');
    $assert->fieldExists('Private');
    $assert->fieldNotExists('Test type');
    $assert->fieldExists('Custom permissions');
  }

}
