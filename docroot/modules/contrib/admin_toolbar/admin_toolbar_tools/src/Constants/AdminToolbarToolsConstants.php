<?php

declare(strict_types=1);

namespace Drupal\admin_toolbar_tools\Constants;

/**
 * Constants for the admin_toolbar_tools module.
 */
final class AdminToolbarToolsConstants {

  /**
   * CSS classes used by default for testing all the added extra links.
   *
   * @var string
   *
   * @see \Drupal\Tests\admin_toolbar_tools\Traits\AdminToolbarToolsEntityCreationTrait:setUp()
   * @see \Drupal\Tests\admin_toolbar_tools\Functional\AdminToolbarToolsExtraLinksCustomTest:setUp()
   */
  const ADMIN_TOOLBAR_TOOLS_EXTRA_LINKS_TEST_CSS_CLASSES = 'toolbar-icon toolbar-icon-admin-toolbar-tools-extra-links';

  /**
   * An array of operation labels for entity bundles.
   *
   * @var array<string>
   *
   * @see \Drupal\Tests\admin_toolbar_tools\Functional\AdminToolbarToolsExtraLinksTest::assertToolbarMenuContentEntityBundleLinks()
   * @see \Drupal\Tests\admin_toolbar_tools\Functional\AdminToolbarToolsExtraLinksTest::assertSearchLinksControllerResponse()
   */
  const ENTITY_BUNDLE_OPERATIONS_LABELS = [
    // The operation labels for entity types using 'field_ui' operations.
    'fields' => 'Manage fields',
    'form-display' => 'Manage form display',
    'display' => 'Manage display',
    'permissions' => 'Manage permissions',
    'delete' => 'Delete',
    // The operation labels for entity types using 'menu_ui' operations.
    'add' => 'Add link',
  ];

}
