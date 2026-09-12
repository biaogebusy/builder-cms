<?php

declare(strict_types=1);

namespace Drupal\admin_toolbar_search\Constants;

/**
 * Constants for the Admin Toolbar Search module.
 */
final class AdminToolbarSearchConstants {

  /**
   * HTML IDs used to display the admin toolbar search.
   *
   * @see admin_toolbar_search_toolbar()
   * @see \Drupal\Tests\admin_toolbar_search\Functional\AdminToolbarSearchSettingsFormTest
   *
   * @var array<string, string>
   */
  const ADMIN_TOOLBAR_SEARCH_HTML_IDS = [
    'search_tab' => 'admin-toolbar-search-tab',
    'search_field_tab' => 'admin-toolbar-search-field-tab',
    'search_toolbar_item' => 'toolbar-item-administration-search',
    'search_tray' => 'toolbar-item-administration-search-tray',
    'search_input' => 'admin-toolbar-search-input',
    'search_field_input' => 'admin-toolbar-search-field-input',
  ];

}
