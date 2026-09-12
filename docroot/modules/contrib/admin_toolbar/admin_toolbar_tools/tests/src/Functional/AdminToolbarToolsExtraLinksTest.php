<?php

declare(strict_types=1);

namespace Drupal\Tests\admin_toolbar_tools\Functional;

use Drupal\admin_toolbar_tools\Constants\AdminToolbarToolsConstants;
use Drupal\Tests\BrowserTestBase;
use Drupal\Tests\admin_toolbar\Traits\AdminToolbarHelperTestTrait;
use Drupal\Tests\admin_toolbar_tools\Traits\AdminToolbarToolsEntityCreationTrait;

/**
 * Test the Admin Toolbar Tools extra links feature.
 *
 * Test module's plugin derivative class 'ExtraLinks' and its integration with
 * Admin Toolbar Search. Except the external integrations, such as devel or
 * project_browser, almost all the links added by the 'ExtraLinks' class are
 * tested here:
 * - Content entity bundles links: block_content, media, content (node),
 *   taxonomy, menu, etc... All the links added under 'admin/structure'.
 * - Add content links: All the links added under 'admin/content'.
 * - User links: All the links added under 'admin/people', logout, etc...
 * - Custom links: Additional links added by modules such as 'Used in views',
 *   'Files', 'Media library', etc... Ensure undeletable menus or roles do not
 *   have a delete link and disabled views are not included in the menu links.
 * - Integration with 'admin_toolbar_search': Test the 'SearchLinks' class used
 *   in the Controller, returns the expected links.
 * - Update and delete entity type bundles: Ensure the extra links are rebuilt
 *   when entity bundles are updated or deleted.
 *
 * @see \Drupal\admin_toolbar_tools\Plugin\Derivative\ExtraLinks
 * @see \Drupal\admin_toolbar_search\SearchLinks
 * @see admin_toolbar/admin_toolbar_tools/admin_toolbar_tools.module
 *
 * @group admin_toolbar
 * @group admin_toolbar_tools
 */
class AdminToolbarToolsExtraLinksTest extends BrowserTestBase {

  use AdminToolbarHelperTestTrait;
  use AdminToolbarToolsEntityCreationTrait;

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'admin_toolbar',
    'admin_toolbar_tools',
    // Test 'admin_toolbar_search' integration with the 'SearchLinks' class.
    'admin_toolbar_search',
    // The following modules are required to test the 'ExtraLinks' feature.
    'block_content',
    'comment',
    'contact',
    'field_ui',
    // Enable the 'media_library' module to test the 'Media library' link.
    'media_library',
    'menu_ui',
    'node',
    'taxonomy',
    'views_ui',
  ];

  /**
   * Test Admin Toolbar Tools plugin derivative class 'ExtraLinks'.
   *
   * Call several methods to test all the links added by the module:
   * - Test all extra links added under 'Structure'.
   * - Test all extra links added under 'Content' ('Add content' links).
   * - Test user, roles and permissions related extra links.
   * - Test custom extra links not tested in other methods.
   * - Test Admin Toolbar Search controller and service class 'SearchLinks'.
   * - Test updating and deleting entity type bundles.
   *
   * @return void
   *   Nothing to return.
   *
   * @throws \Behat\Mink\Exception\ExpectationException
   *
   * @see \Drupal\admin_toolbar_tools\Plugin\Derivative\ExtraLinks::getDerivativeDefinitions()
   * @see \Drupal\admin_toolbar_search\SearchLinks::getLinks()
   */
  public function testAdminToolbarToolsExtraLinks(): void {
    // Test all extra links added under 'Structure'.
    $this->assertToolbarMenuContentEntityBundleLinks();

    // Test all extra links added under 'Add content'.
    $this->assertToolbarMenuContentEntityAddLinks();

    // Test all user, roles and permissions related extra links.
    $this->assertToolbarMenuExtraLinksUser();

    // Test custom extra links not tested in other methods.
    $this->assertToolbarMenuExtraLinksCustom();

    // Test Admin Toolbar Search controller and service class 'SearchLinks'.
    $this->assertSearchLinksControllerResponse();

    // Test updating and deleting entity type bundles.
    // This method should be called last since it modifies entity bundles.
    $this->assertToolbarMenuExtraLinksUpdateDelete();
  }

  /**
   * Test all extra links added under 'Structure' ('admin/structure').
   *
   * For each entity type with bundles created for the test, check:
   * - The 'Add {entity type}' link, if available.
   * - The 'All types' link when there are more bundles than the
   *   'max_bundle_number' config variable.
   * - Ensure all entity bundle links below the 'max_bundle_number' are found.
   * - Ensure entity bundle links over the 'max_bundle_number' are *not* found.
   *
   * Test integration with 'field_ui' and 'menu_ui' tasks extra links.
   * Links are all tested with expected url, label, position and CSS classes.
   *
   * @return void
   *   Nothing to return.
   *
   * @throws \Behat\Mink\Exception\ExpectationException
   *
   * @see \Drupal\admin_toolbar_tools\Plugin\Derivative\ExtraLinks::getDerivativeDefinitions()
   */
  protected function assertToolbarMenuContentEntityBundleLinks() {
    // Loop through all the tested entity types to check the links in the menu.
    foreach ($this->testEntityTypesExtraLinks as $parameters_array) {
      // Get the chunked array of test entity bundle IDs.
      $test_entity_bundle_values_chunks = $parameters_array['bundle_ids']['chunked'];
      // Get the entity type bundle links configuration.
      $test_entity_type_bundle_links = $parameters_array['bundle_links'] ?? ['base_url' => ''];
      // Get the base operation for the entity type to be tested. This option
      // is mostly to support views with 'view' otherwise defaults to 'manage'.
      $base_operation = $test_entity_type_bundle_links['base_operation'] ?? 'manage';
      // Define the base operation url of the entity bundle to be tested.
      $test_entity_bundle_base_operation = '/' . $test_entity_type_bundle_links['base_url'] . '/' . $base_operation . '/';
      // Initialize the base position of the entity bundle links based on
      // whether links 'All types' or 'Add {entity type}' are displayed.
      $test_entity_bundle_link_base_position = !empty($test_entity_bundle_values_chunks[1]) + !empty($test_entity_type_bundle_links['add_type_link_text']);

      /* Test 'Add {entity type}' link. */

      // Test extra link 'Add {entity type}'.
      if (!empty($test_entity_type_bundle_links['add_type_link_text'])) {
        // Check the 'Add entity type' menu item exists. Currently, not all
        // entity types have an 'Add type' link, e.g. Block content or Comment.
        // Ensure it is the first or second menu item, depending on whether the
        // link 'All types' is also displayed.
        $this->assertAdminToolbarMenuLinkExists('/' . $test_entity_type_bundle_links['base_url'] . '/add', $test_entity_type_bundle_links['add_type_link_text'], $test_entity_bundle_link_base_position);
      }

      /* Test extra links below the 'max_bundle_number' are found. */

      if (!empty($parameters_array['bundle_ids']['menu_links_offset']) && $parameters_array['bundle_ids']['menu_links_offset'] > 0) {
        // Adjust the base position of the entity bundle links if there is an
        // offset defined, for example to support the 'Add menu' and 'All menus'
        // links added by the 'menu_ui' module.
        $test_entity_bundle_link_base_position += $parameters_array['bundle_ids']['menu_links_offset'];
      }

      // Ensure entity bundle links, below the 'max_bundle_number' are displayed
      // in the toolbar with all the provided operations.
      foreach ($test_entity_bundle_values_chunks[0] as $entity_bundle_id) {
        // Special case for taxonomy vocabularies: The 'Edit' link corresponds
        // to the 'manage' base operation.
        $edit_bundle_link_text = $test_entity_type_bundle_links['edit_bundle_link_text'] ?? ucfirst($entity_bundle_id);
        // Ensure the links are ordered as expected. If there is no edit link,
        // the position is incremented, otherwise, it should always be the first
        // link 'Edit' from field operations (used for taxonomy vocabularies).
        $test_entity_bundle_link_base_position++;
        $test_bundle_position = empty($test_entity_type_bundle_links['edit_bundle_link_text']) ? $test_entity_bundle_link_base_position : 1;
        // Test the exact match is found in the menu links ('Edit').
        $this->assertAdminToolbarMenuLinkExists($test_entity_bundle_base_operation . $entity_bundle_id, $edit_bundle_link_text, $test_bundle_position);

        // Ensure entity bundle operations links are found.
        if (!empty($test_entity_type_bundle_links['operations'])) {
          // Special case for taxonomy vocabularies 'Edit' link tested above.
          $test_bundle_position = empty($test_entity_type_bundle_links['edit_bundle_link_text']) ? 1 : 2;
          // Loop through all the operations defined for the entity type.
          foreach ($test_entity_type_bundle_links['operations'] as $test_entity_bundle_operation) {
            // Skip empty operations. Mostly used for roles permissions links,
            // since they have a different base route and are tested separately.
            if (empty($test_entity_bundle_operation)) {
              $test_bundle_position++;
              continue;
            }

            // Special case for taxonomy vocabularies overview link.
            $test_entity_bundle_operation_key = str_replace('overview/', '', $test_entity_bundle_operation);
            // Test the url of the entity bundle operation is found in the
            // menu links in the toolbar with the expected label and position.
            if (!empty(AdminToolbarToolsConstants::ENTITY_BUNDLE_OPERATIONS_LABELS[$test_entity_bundle_operation_key])) {
              $this->assertAdminToolbarMenuLinkExists($test_entity_bundle_base_operation . $entity_bundle_id . '/' . $test_entity_bundle_operation, AdminToolbarToolsConstants::ENTITY_BUNDLE_OPERATIONS_LABELS[$test_entity_bundle_operation_key], $test_bundle_position++);
            }
            else {
              // Special case for taxonomy vocabularies 'overview' operation:
              // Ensure the link has the bundle id as label and the expected
              // position.
              $this->assertAdminToolbarMenuLinkExists($test_entity_bundle_base_operation . $entity_bundle_id . '/' . $test_entity_bundle_operation, ucfirst($entity_bundle_id), $test_entity_bundle_link_base_position);
            }
          }
        }
      }

      /* Test extra links over the 'max_bundle_number' are *not* found. */

      if (!empty($test_entity_bundle_values_chunks[1])) {
        // Test the extra link 'All types' added when there are more entity type
        // bundles than the 'max_bundle_number' config variable.
        $all_types_link_text = $parameters_array['bundle_links']['all_types_link_text'] ?? 'All types';
        // Ensure the 'All types' link is always displayed as the first item.
        $this->assertAdminToolbarMenuLinkExists($parameters_array['bundle_links']['base_url'], $all_types_link_text, 1);

        // Ensure entity bundle links, over the 'max_bundle_number' are *not*
        // displayed in the toolbar.
        foreach ($test_entity_bundle_values_chunks[1] as $entity_bundle_id) {
          // Test the base operation for the entity bundle is *not* found in the
          // menu links in the toolbar.
          $this->assertAdminToolbarMenuLinkNotExists($base_operation . '/' . $entity_bundle_id);
        }
      }
    }
  }

  /**
   * Test all extra links added under 'Add content'.
   *
   * For each entity type with 'Add content' links, test:
   * - The 'Content overview' link.
   * - The 'Add {entity type}' links.
   * - Ensure all entity bundle 'Add content' links are found.
   *
   * Links are all tested with expected url, label, position and CSS classes.
   *
   * @return void
   *   Nothing to return.
   *
   * @throws \Behat\Mink\Exception\ExpectationException
   *
   * @see \Drupal\admin_toolbar_tools\Plugin\Derivative\ExtraLinks::getDerivativeDefinitions()
   */
  protected function assertToolbarMenuContentEntityAddLinks() {
    // Loop through all the tested entity types.
    foreach ($this->testEntityTypesExtraLinks as $parameters_array) {
      // Skip entity types without 'Add content' links.
      if (!empty($parameters_array['add_content_links'])) {

        // Test the 'Content overview' link.
        if (!empty($parameters_array['add_content_links']['overview_page'])) {
          $overview_page = $parameters_array['add_content_links']['overview_page'];
          $this->assertAdminToolbarMenuLinkExists('/' . $overview_page['base_url'], $overview_page['label'], $overview_page['position'] ?? 0, $overview_page['css_classes'] ?? '');
        }

        // Test the 'Add {entity type}' links for test entity bundles.
        if (!empty($parameters_array['add_content_links']['add_item_page'])) {
          $add_item_page = $parameters_array['add_content_links']['add_item_page'];
          // Check the 'Add <entity type>' menu item exists, for example:
          // 'media/add', 'node/add', etc...
          $this->assertAdminToolbarMenuLinkExists('/' . $add_item_page['base_url'], $add_item_page['label'], 1);

          // Currently, add content links do not have a maximum limit.
          foreach ($parameters_array['bundle_ids']['raw'] as $key => $entity_type_bundle_id) {
            // Ensure all entity bundle add content links are found.
            $this->assertAdminToolbarMenuLinkExists('/' . $add_item_page['base_url'] . '/' . $entity_type_bundle_id, ucfirst($entity_type_bundle_id), $key + 1);
          }
        }
      }
    }
  }

  /**
   * Custom extra links not tested in the main test method.
   *
   * Test custom extra links provided by the module that could not be tested in
   * other methods:
   * - Test undeletable menus do *not* have a delete link.
   * - Test disabled views are *not* displayed in menu links.
   * - Test custom links: 'Media library', 'Files' and 'Used in views'.
   *
   * Links are all tested with expected url, label, position and CSS classes.
   *
   * @return void
   *   Nothing to return.
   *
   * @throws \Behat\Mink\Exception\ExpectationException
   *
   * @see \Drupal\admin_toolbar_tools\Plugin\Derivative\ExtraLinks::getDerivativeDefinitions()
   */
  protected function assertToolbarMenuExtraLinksCustom() {

    /* Test undeletable menus do *not* have a delete link. */

    // Undeletable menus defined by Drupal core.
    $un_deletable_menus = [
      'account',
      'admin',
      'footer',
      'main',
      'tools',
    ];
    $test_entity_bundle_base_operation = '/' . $this->testEntityTypesExtraLinks['menu']['bundle_links']['base_url'] . '/manage/';

    foreach ($un_deletable_menus as $un_deletable_menu_id) {
      // Test the menu id is *not* found in the menu links in the toolbar.
      $this->assertAdminToolbarMenuLinkNotExists($un_deletable_menu_id . '/delete');
      // Check the 'Add link' exists for undeletable menus.
      $this->assertAdminToolbarMenuLinkExists($test_entity_bundle_base_operation . $un_deletable_menu_id . '/add', 'Add link', 1);
    }

    /* Test disabled views are *not* displayed in menu links. */

    // Check disabled views are *not* found in the menu links.
    $disabled_view_ids = array_keys(\Drupal::entityTypeManager()->getStorage('view')->loadByProperties(['status' => FALSE]));
    foreach ($disabled_view_ids as $disabled_view_id) {
      // Check the bundle id of the disabled view is not found in the menu.
      $this->assertAdminToolbarMenuLinkNotExists($disabled_view_id);
    }

    /* Test custom extra links are displayed. */

    // Test the custom extra links provided by the module that could not be
    // tested in the main 'AdminToolbarToolsExtraLinksTest' class.
    $custom_extra_links = [
      // Test integration with 'media_library': Check the 'Media library' and
      // 'Files' links under 'Content'.
      [
        'url' => 'admin/content/media-grid',
        'text' => 'Media library',
        'position' => 2,
      ],
      [
        'url' => 'admin/content/files',
        'text' => 'Files',
        'position' => version_compare(\Drupal::VERSION, '10', '<') ? 3 : 4,
      ],
      // Test integration with 'views_ui': Check the 'Used in views' link under
      // 'Reports'.
      [
        'url' => 'admin/reports/fields/views-fields',
        'text' => 'Used in views',
      ],
    ];

    // Check all the custom extra links are found in the admin toolbar menu.
    foreach ($custom_extra_links as $custom_extra_link) {
      // Check the custom extra link exists in the menu links in the toolbar.
      $this->assertAdminToolbarMenuLinkExists($custom_extra_link['url'], $custom_extra_link['text'], $custom_extra_link['position'] ?? 0);
    }
  }

  /**
   * Check user, roles and permissions related extra links.
   *
   * Test all the links under 'People':
   * - Check the 'Add user' and 'Permissions' links.
   * - Ensure undeletable roles do *not* have a delete link.
   * - Ensure all roles including undeletable ones have an 'Edit permissions'
   *   link.
   *
   * Links are all tested with expected url, label, position and CSS classes.
   *
   * @return void
   *   Nothing to return.
   *
   * @throws \Behat\Mink\Exception\ExpectationException
   *
   * @see \Drupal\admin_toolbar_tools\Plugin\Derivative\ExtraLinks::getDerivativeDefinitions()
   */
  protected function assertToolbarMenuExtraLinksUser() {
    // Test the 'Add user' and 'Permissions' links under 'People'.
    $this->assertAdminToolbarMenuLinkExists('admin/people/create', 'Add user', 1);
    $this->assertAdminToolbarMenuLinkExists('admin/people/permissions', 'Permissions', 2);

    // Define the base operation url for the permissions links to be tested.
    $test_entity_bundle_permissions_operation = 'admin/people/permissions/';

    // Ensure undeletable roles do *not* have a delete link.
    $un_deletable_roles = ['anonymous', 'authenticated'];

    foreach ($un_deletable_roles as $un_deletable_role_id) {
      // Test the exact match is *not* found in the menu links in the toolbar.
      $this->assertAdminToolbarMenuLinkNotExists($un_deletable_role_id . '/delete');
      // Check the 'Edit permissions' link exists for undeletable roles.
      $this->assertAdminToolbarMenuLinkExists($test_entity_bundle_permissions_operation . $un_deletable_role_id, 'Edit permissions', 1);
    }

    // Check the 'Edit permissions' link exists for all roles and is always
    // displayed as the first item.
    foreach ($this->testEntityTypesExtraLinks['user_role']['bundle_ids']['raw'] as $entity_type_bundle_id) {
      $this->assertAdminToolbarMenuLinkExists($test_entity_bundle_permissions_operation . $entity_type_bundle_id, 'Edit permissions', 1);
    }

  }

  /**
   * Check the search JSON response contains the correct test extra links.
   *
   * Test Admin Toolbar Search controller, service class 'SearchLinks' and that
   * the route 'admin_toolbar.search' returns the expected JSON:
   * Links provided by the admin toolbar tools module to block content, media,
   * content types, etc... operation routes, such as edit, add, manage, etc...
   * Ensure test entity bundle IDs below the 'max_bundle_number' are not found
   * in the response, since they should already be included in the toolbar
   * admin menu.
   *
   * Links are all tested with expected url and label.
   *
   * @return void
   *   Nothing to return.
   *
   * @throws \Behat\Mink\Exception\ExpectationException
   *
   * @see \Drupal\admin_toolbar_search\Controller\AdminToolbarSearchController::search()
   * @see \Drupal\admin_toolbar_search\SearchLinks::getLinks()
   */
  protected function assertSearchLinksControllerResponse() {
    /** @var \Drupal\Tests\WebAssert $assert_session */
    $assert_session = $this->assertSession();
    // Initialize a flag to check if the response should be empty.
    $assert_empty_response = TRUE;

    // Test that the route admin_toolbar.search returns the expected JSON.
    $this->drupalGet('admin/admin-toolbar-search');

    // Loop through all the tested entity types to check the links in the JSON.
    foreach ($this->testEntityTypesExtraLinks as $entity_type => $parameters_array) {
      // Get the chunked array of test entity bundle IDs to test the module's
      // feature 'max_bundle_number' logic.
      $test_entity_bundle_values_chunks = $parameters_array['bundle_ids']['chunked'];
      // Get the base operation for the entity type to be tested. This option
      // is mostly to support views with 'view' otherwise defaults to 'manage'.
      $base_operation = $parameters_array['bundle_links']['base_operation'] ?? 'manage';
      // Get the entity type label.
      $entity_type_label = \Drupal::entityTypeManager()->getDefinition($entity_type)->getLabel();

      /* Test extra links not displayed in the toolbar are found in the JSON. */

      // The second chunk could be empty, if the number of test entity bundles
      // is lower than the 'max_bundle_number' config variable or if this
      // feature is not implemented for certain entity types.
      if (!empty($test_entity_bundle_values_chunks[1])) {
        // If there is at least one entity type with more bundles than the
        // 'max_bundle_number', the response should not be empty.
        $assert_empty_response = FALSE;
        // Escape the slashes '/' characters as if they were JSON encoded and
        // escape them a second time for the regex used to match the response.
        // Prefix the route with '.*' to allow absolute or relative paths
        // depending on the site setup.
        $test_entity_type_route_escaped = str_replace('/', '\\\\\/', '.*/' . $parameters_array['bundle_links']['base_url'] . '/' . $base_operation . '/');

        // Test all entity bundle links for each entity type. The order of
        // display is not important, since the links are loaded in a JS array.
        foreach ($test_entity_bundle_values_chunks[1] as $entity_bundle_id) {
          // Check for the exact route of the entity bundle: Edit.
          $prefix = $parameters_array['bundle_ids']['bundle_id_prefix'] ?? '';
          // Define the expected label of the entity type bundle link.
          // Anti-slash characters have to be double escaped for the regex.
          $test_entity_type_bundle_label = '/{"labelRaw":"' . $entity_type_label . ' \\\\u003E ' . $prefix . ucfirst($entity_bundle_id);
          // Test the exact match is found in the JSON response. Use a regex to
          // allow absolute or relative paths depending on the site setup.
          $assert_session->responseMatches($test_entity_type_bundle_label . ' \\\\u003E Edit","value":"' . $test_entity_type_route_escaped . $entity_bundle_id . '"}/');

          // Test the links for provided entity bundle operations.
          if (!empty($parameters_array['bundle_links']['operations'])) {
            foreach ($parameters_array['bundle_links']['operations'] as $test_entity_bundle_operation) {
              // Skip empty operations. Mostly used for roles permissions links.
              if (empty($test_entity_bundle_operation)) {
                continue;
              }

              // Check the url of the entity bundle operation is found.
              $replaced_string = str_replace('/', '\\\\\/', $entity_bundle_id . '/' . $test_entity_bundle_operation);
              // Special case for taxonomy vocabularies overview link.
              $test_entity_bundle_operation_key = str_replace('overview/', '', $test_entity_bundle_operation);
              // Special case for taxonomy vocabularies 'overview' operation:
              // It should not have an operation label.
              $test_entity_bundle_operation_label = empty(AdminToolbarToolsConstants::ENTITY_BUNDLE_OPERATIONS_LABELS[$test_entity_bundle_operation_key]) ? '' : ' \\\\u003E ' . AdminToolbarToolsConstants::ENTITY_BUNDLE_OPERATIONS_LABELS[$test_entity_bundle_operation_key];
              // Test the url of the entity bundle operation is found in the
              // JSON response with the expected label. Use a regex to allow
              // absolute or relative paths depending on the site setup.
              $assert_session->responseMatches($test_entity_type_bundle_label . $test_entity_bundle_operation_label . '","value":"' . $test_entity_type_route_escaped . $replaced_string . '"}/');
            }
          }
        }

        /* Test extra links displayed in the toolbar are not found in the JSON. */

        // Check admin toolbar tools extra links are not loaded for the items
        // included in the toolbar admin menu. If there is no second chunk,
        // there is point to test the links are not found in the JSON.
        foreach ($test_entity_bundle_values_chunks[0] as $entity_bundle_id) {
          // Ensure the entity bundle ID is *not* found at all in the JSON.
          $assert_session->responseNotContains($entity_bundle_id);
        }
      }
    }

    // If all entity types have a number of bundles below the settings
    // 'max_bundle_number', the response should be an empty array.
    if ($assert_empty_response) {
      // Ensure the response is empty.
      $assert_session->responseContains('[]');
    }
  }

  /**
   * Ensure extra links are rebuilt when entity bundles are updated or deleted.
   *
   * Test module's entity update and delete hooks implementations to ensure the
   * extra links are rebuilt correctly.
   *
   * Test deleting and updating the first entity bundles:
   * - Delete the first entity bundle and update the label of the second one.
   * - Ensure the deleted entity bundle is *not* found in the menu links.
   * - Ensure the updated entity bundle label is found in the menu links.
   *
   * Links are all tested with expected url, label, position and CSS classes.
   *
   * @return void
   *   Nothing to return.
   *
   * @throws \Behat\Mink\Exception\ExpectationException
   *
   * @see \Drupal\admin_toolbar_tools\AdminToolbarToolsHelper::getRebuildEntityTypes()
   * @see admin_toolbar_tools_entity_update()
   * @see admin_toolbar_tools_entity_delete()
   */
  protected function assertToolbarMenuExtraLinksUpdateDelete() {
    /** @var \Drupal\Tests\WebAssert $assert_session */
    $assert_session = $this->assertSession();

    // Array to store the deleted and updated entity bundle IDs.
    $test_entity_bundle_ids = [];

    // Loop through all the tested entity types to delete and update bundles.
    foreach ($this->testEntityTypesExtraLinks as $entity_type => $parameters_array) {
      // Get the first chunk array of test entity bundle IDs: they should always
      // be displayed no matter the 'max_bundle_number' config value.
      $test_entity_bundle_first_chunk = $parameters_array['bundle_ids']['chunked'][0] ?? [];

      // Shift out the first entity bundle id to be deleted and store it.
      $test_entity_bundle_ids[$entity_type]['deleted_bundle_id'] = array_shift($test_entity_bundle_first_chunk);
      // Delete the entity type bundle.
      \Drupal::entityTypeManager()->getStorage($entity_type)
        ->load($test_entity_bundle_ids[$entity_type]['deleted_bundle_id'])
        ->delete();

      // Store the updated entity bundle label, prefixed with 'AAA' to ensure it
      // is ordered first alphabetically, so it appears in the menu links.
      $test_entity_bundle_ids[$entity_type]['updated_bundle']['label'] = 'AAA' . $this->randomMachineName();
      $test_entity_bundle_ids[$entity_type]['updated_bundle']['id'] = $test_entity_bundle_first_chunk[0];
      // Update the label of the first entity bundle of the first chunk.
      $this->updateEntityTypeBundleLabel($entity_type, $test_entity_bundle_ids[$entity_type]['updated_bundle']['id'], $test_entity_bundle_ids[$entity_type]['updated_bundle']['label']);

      // Get the second chunk array of test entity bundle IDs: test the class
      // 'SearchLinks' returns the updated JSON response.
      $test_entity_bundle_second_chunk = $parameters_array['bundle_ids']['chunked'][1] ?? [];
      if (!empty($test_entity_bundle_second_chunk)) {
        // Pop out the last entity bundle id to be deleted and store it.
        $test_entity_bundle_ids[$entity_type]['deleted_bundle_id_max'] = array_pop($test_entity_bundle_second_chunk);
        // Delete the entity type bundle.
        \Drupal::entityTypeManager()->getStorage($entity_type)
          ->load($test_entity_bundle_ids[$entity_type]['deleted_bundle_id_max'])
          ->delete();

        // Store the updated entity bundle label, prefixed with 'zzz' to ensure
        // it is ordered last alphabetically, so it appears in the search links.
        $test_entity_bundle_ids[$entity_type]['updated_bundle_max']['label'] = 'zzz' . $this->randomMachineName();
        $test_entity_bundle_ids[$entity_type]['updated_bundle_max']['id'] = end($test_entity_bundle_second_chunk);
        // Update the label of the last entity bundle of the second chunk.
        $this->updateEntityTypeBundleLabel($entity_type, $test_entity_bundle_ids[$entity_type]['updated_bundle_max']['id'], $test_entity_bundle_ids[$entity_type]['updated_bundle_max']['label']);
      }
    }

    // Reload the admin page to ensure the HTML and JS are reloaded.
    $this->drupalGet('admin');

    // Loop through all the stored entity bundle ids to check the links.
    foreach ($test_entity_bundle_ids as $entity_type => $test_entity_bundle_values) {
      // Check the deleted entity bundle is *not* found in the menu links.
      $this->assertAdminToolbarMenuLinkNotExists($test_entity_bundle_values['deleted_bundle_id']);

      $parameters_array = $this->testEntityTypesExtraLinks[$entity_type];
      // Get the base operation for the entity type to be tested. This option
      // is mostly to support views with 'view' otherwise defaults to 'manage'.
      $base_operation = $parameters_array['bundle_links']['base_operation'] ?? 'manage';
      // Get the edit operation for taxonomy vocabularies.
      $edit_bundle_operation = !empty($parameters_array['bundle_links']['edit_bundle_link_text']) ? '/overview' : '';
      // Define the base operation url of the entity bundle to be tested. The
      // bundle id is the second of the first chunk, since the first one was
      // deleted and the array was not modified by reference.
      $test_entity_bundle_id_base_operation = '/' . $parameters_array['bundle_links']['base_url'] . '/' . $base_operation . '/' . $test_entity_bundle_values['updated_bundle']['id'] . $edit_bundle_operation;
      // Initialize the base position of the entity bundle links based on
      // whether links 'All types' or 'Add {entity type}' are displayed.
      $test_entity_bundle_link_base_position = !empty($parameters_array['bundle_ids']['chunked'][1]) + !empty($parameters_array['bundle_links']['add_type_link_text']);

      // Check the updated entity bundle label is found in the menu links.
      $this->assertAdminToolbarMenuLinkExists($test_entity_bundle_id_base_operation, $test_entity_bundle_values['updated_bundle']['label'], $test_entity_bundle_link_base_position + 1);
    }

    // Now test that the route admin_toolbar.search returns the expected JSON.
    $this->drupalGet('admin/admin-toolbar-search');

    // Loop through all the stored entity bundle ids to check the links.
    foreach ($test_entity_bundle_ids as $entity_type => $test_entity_bundle_values) {
      // Skip entity types without a second chunk of bundles, such as views.
      $parameters_array = $this->testEntityTypesExtraLinks[$entity_type];
      if (empty($parameters_array['bundle_ids']['chunked'][1])) {
        continue;
      }

      // Check the deleted entity bundle is *not* found in the response.
      $assert_session->responseNotContains($test_entity_bundle_values['deleted_bundle_id_max']);

      // Get the base operation for the entity type to be tested. This option
      // is mostly to support views with 'view' otherwise defaults to 'manage'.
      $base_operation = $parameters_array['bundle_links']['base_operation'] ?? 'manage';
      // Escape the slashes '/' characters as if they were JSON encoded and
      // escape them a second time for the regex used to match the response.
      // Prefix the route with '.*' to allow absolute or relative paths
      // depending on the site setup.
      $test_entity_type_route_escaped = str_replace('/', '\\\\\/', '.*/' . $parameters_array['bundle_links']['base_url'] . '/' . $base_operation . '/' . $test_entity_bundle_values['updated_bundle_max']['id']);
      // Get the entity type label.
      $entity_type_label = \Drupal::entityTypeManager()->getDefinition($entity_type)->getLabel();
      // Define the expected regex for the entity type bundle link.
      // Anti-slash characters have to be double escaped for the regex.
      $test_entity_type_bundle_link_regex = '/{"labelRaw":"' . $entity_type_label . ' \\\\u003E ' . $test_entity_bundle_values['updated_bundle_max']['label'] . ' \\\\u003E Edit","value":"' . $test_entity_type_route_escaped . '"}/';
      // Test the exact match is found in the JSON response. Use a regex to
      // allow absolute or relative paths depending on the site setup.
      $assert_session->responseMatches($test_entity_type_bundle_link_regex);
    }
  }

}
