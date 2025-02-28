<?php

namespace Drupal\Tests\field_permissions\Functional;

use Drupal\node\Entity\NodeType;
use Drupal\Tests\BrowserTestBase;

/**
 * Tests the field config edit form.
 *
 * @group field_permissions
 */
class FieldPermissionsFieldConfigEditFormTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'field_permissions_test',
    'node',
    'field_ui',
  ];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * Tests that plugins can opt-out for a given field.
   *
   * @covers \Drupal\field_permissions\Plugin\FieldPermissionTypeInterface::appliesToField
   */
  public function testAppliesToField(): void {
    $node_type = NodeType::create(['type' => 'page']);
    $node_type->save();
    node_add_body_field($node_type);

    $this->drupalLogin($this->createUser([
      'administer field permissions',
      'administer node fields',
    ]));
    $this->drupalGet('/admin/structure/types/manage/page/fields/node.page.body');

    $assert = $this->assertSession();

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
