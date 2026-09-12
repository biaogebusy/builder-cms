<?php

declare(strict_types=1);

namespace Drupal\Tests\admin_toolbar_tools\Traits;

use Drupal\admin_toolbar_tools\Constants\AdminToolbarToolsConstants;

/**
 * @file
 * Contains methods for creating entities in test cases for admin toolbar tools.
 */

/**
 * Adds common methods and objects for test classes requiring entity creation.
 *
 * Provides common functionality for any class using the trait to implement
 * Functional or FunctionalJavascript test cases:
 * - Define a test matrix with all the entity types and links to be tested.
 * - Create N entity bundles with random ID strings.
 * - Create and log in an administrative user with all the required permissions.
 * - Helper functions to create and update entity type bundles.
 *
 * Classes using the trait should to ensure the necessary modules are enabled.
 *
 * @see \Drupal\Tests\admin_toolbar_tools\Functional\AdminToolbarToolsExtraLinksTest
 */
trait AdminToolbarToolsEntityCreationTrait {

  /**
   * A user with the necessary permissions to access the links to be tested.
   *
   * @var \Drupal\user\UserInterface
   */
  protected $adminUser;

  /**
   * The entity types testing matrix containing all the links to be tested.
   *
   * Each entity type to be tested is defined by an array of parameters:
   * - 'bundle_ids': The random IDs to be tested for the created entity bundles.
   *   - 'menu_links_offset': The offset used to skip default entity bundles.
   *     Use a negative value to ignore the 'max_bundle_number' setting and
   *     test all available entity bundles.
   *   - 'bundle_id_prefix': The prefix used for the created entity bundles
   *     labels, so they could be ordered before or after the default ones.
   *   - 'bundle_weight': The weight to be used for the created entity bundles,
   *     mostly used for user roles to be ordered before the default ones.
   * - 'bundle_links': The links to be tested under 'Structure'.
   *   - 'add_type_link_text': The 'Add {entity type}' link text to be tested.
   *   - 'edit_bundle_link_text': The 'Edit {entity type}' link text to be
   *     tested, used mostly as an exception for taxonomy vocabularies. Defaults
   *     to the entity bundle label with the first letter capitalized.
   *   - 'all_types_link_text': The 'All {entity types}' link text to be tested,
   *     used mostly as an exception for menus. Defaults to 'All types'.
   *   - 'base_url': The base admin route for the entity type to be tested.
   *   - 'base_operation': The base operation for the entity type to be tested,
   *     defaults to 'manage'.
   *   - 'operations': The operations links to be tested for the entity type for
   *     operations provided by the 'field_ui' or 'menu_ui' modules, such as
   *     'fields', 'display', 'delete', 'permissions', 'add', etc...
   * - 'add_content_links': The links to be tested under 'Content'.
   *   - 'overview_page': The overview page link to be tested.
   *   - 'add_item_page': The 'Add {entity type}' link to be tested.
   * - 'required_permissions': The permissions required to access the entity
   *   bundles and menu links for the test.
   *
   * Entity type bundles currently tested for: Block content, Comment, Contact,
   * Media, Menu, Node, Taxonomy, User and Views.
   *
   * The idea behind this test matrix is to try keeping everything related to
   * the entity types in one place, making it easier to maintain and update as
   * needed, for example, adding or removing an entity type to be tested, or
   * testing different 'field_ui' operations, etc...
   *
   * @var array<array<mixed>>
   */
  protected $testEntityTypesExtraLinks = [
    // Test core module: 'block_content'.
    'block_content_type' => [
      // The block content types links to be tested under 'Structure'.
      'bundle_links' => [
        // Define the base block content types admin route to be tested.
        'base_url' => 'admin/structure/block-content',
        // Define the 'field_ui' operations links to be tested.
        'operations' => ['fields', 'permissions', 'delete'],
      ],
      // The 'add' content links to be tested under 'Content'.
      'add_content_links' => [
        // Test the block content overview page.
        'overview_page' => [
          'label' => 'Blocks',
          'base_url' => 'admin/content/block',
          'position' => 2,
        ],
        // The add block content page.
        'add_item_page' => [
          'label' => 'Add content block',
          'base_url' => 'block/add',
        ],
      ],
      // The permissions required to access the block content types and library.
      'required_permissions' => [
        'access block library',
        'administer block content',
        'administer block_content fields',
        'administer block types',
      ],
    ],
    // Test core module: 'comment'.
    'comment_type' => [
      // The comment types links to be tested under 'Structure'.
      'bundle_links' => [
        'base_url' => 'admin/structure/comment',
        'operations' => ['fields', 'permissions', 'delete'],
      ],
      // The 'add' content links to be tested under 'Content'.
      // Note: There is no 'Add comment' link, since comments are added to nodes
      // or other entities.
      'add_content_links' => [
        // Test the comment overview page.
        'overview_page' => [
          'label' => 'Comments',
          'base_url' => 'admin/content/comment',
          'position' => 3,
          'css_classes' => 'toolbar-icon toolbar-icon-comment-admin',
        ],
      ],
      // The permissions required to access the comment types and comments.
      'required_permissions' => [
        'administer comment fields',
        'administer comment types',
        'administer comments',
      ],
    ],
    // Test core module: 'contact'.
    'contact_form' => [
      // The random IDs to be tested for the created contact forms.
      'bundle_ids' => [
        // By default there is one contact form created with ID 'personal', so
        // the forms created for the tests are prefixed with 'AA' to be ordered
        // before the default one.
        // Note: no menu link offset is needed in this very specific case, since
        // the Personal contact form does not have an 'Edit' link operation.
        'bundle_id_prefix' => 'AA',
      ],
      // The contact forms links to be tested under 'Structure'.
      'bundle_links' => [
        // Test the 'Add contact form' link text.
        'add_type_link_text' => 'Add contact form',
        'base_url' => 'admin/structure/contact',
        'operations' => ['fields', 'permissions', 'delete'],
      ],
      // The permissions required to access the contact forms.
      'required_permissions' => [
        'administer contact forms',
        'administer contact_message fields',
      ],
    ],
    // Test core module: 'media'.
    'media_type' => [
      // The media types links to be tested under 'Structure'.
      'bundle_links' => [
        // Test the 'Add media type' link text.
        'add_type_link_text' => 'Add media type',
        'base_url' => 'admin/structure/media',
        // Test all 'field_ui' operations.
        'operations' => [
          'fields',
          'form-display',
          'display',
          'permissions',
          'delete',
        ],
      ],
      // The 'add' content links to be tested under 'Content'.
      'add_content_links' => [
        // Test the media overview page.
        'overview_page' => [
          'label' => 'Media',
          'base_url' => 'admin/content/media',
          'position' => 5,
        ],
        // The add media page.
        'add_item_page' => [
          'label' => 'Add media',
          'base_url' => 'media/add',
        ],
      ],
      // The permissions required to access and manage media.
      'required_permissions' => [
        // The following block of 'media' related permissions is needed to test
        // Media types configuration routes (fields, display, etc...).
        'access media overview',
        'administer media',
        'administer media fields',
        'administer media form display',
        'administer media display',
        'administer media types',
        'access files overview',
      ],
    ],
    // Test core modules: 'menu' and 'menu_ui'.
    'menu' => [
      // The random IDs to be tested for the created menus.
      'bundle_ids' => [
        // By default there are five un-deletable menus created, so the menus
        // created for the test are prefixed with 'Z' to be ordered after the
        // default ones with an offset of 5 to skip them.
        'menu_links_offset' => 5,
        'bundle_id_prefix' => 'Z',
      ],
      // The menu types links to be tested under 'Structure'.
      'bundle_links' => [
        // Test the 'Add menu' link text.
        'add_type_link_text' => 'Add menu',
        // Test the custom 'All menus' link text, defaults to 'All types'.
        'all_types_link_text' => 'All menus',
        'base_url' => 'admin/structure/menu',
        // Define the 'menu_ui' operations to be tested.
        'operations' => ['add', 'delete'],
      ],
      // The permissions required to access and manage menus.
      'required_permissions' => [
        // Permission required for testing 'menu_ui' routes.
        'administer menu',
      ],
    ],
    // Test core module: 'node'.
    'node_type' => [
      // The content types links to be tested under 'Structure'.
      'bundle_links' => [
        // Test the 'Add content type' link text.
        'add_type_link_text' => 'Add content type',
        'base_url' => 'admin/structure/types',
        // Test all 'field_ui' operations.
        'operations' => [
          'fields',
          'form-display',
          'display',
          'permissions',
          'delete',
        ],
      ],
      // The 'add' content links to be tested under 'Content'.
      'add_content_links' => [
        // Test the content overview page.
        'overview_page' => [
          'label' => 'Content',
          'base_url' => 'admin/content',
          'position' => 2,
          'css_classes' => 'toolbar-icon toolbar-icon-system-admin-content',
        ],
        // The add content page.
        'add_item_page' => [
          'label' => 'Add content',
          'base_url' => 'node/add',
        ],
      ],
      // The permissions required to access and manage content types.
      'required_permissions' => [
        'administer content types',
        // Required to test 'field_ui' routes, under 'Structure'.
        'administer node fields',
        'administer node form display',
        'administer node display',
        // Required to test links under 'Content'.
        'administer nodes',
        'access content overview',
        // Needed to test Add node type links, ex: '/node/add/bbn8bu4e'.
        'bypass node access',
      ],
    ],
    // Test core module: 'taxonomy'.
    'taxonomy_vocabulary' => [
      // The taxonomy vocabularies links to be tested under 'Structure'.
      'bundle_links' => [
        // Test the 'Add vocabulary' link text.
        'add_type_link_text' => 'Add vocabulary',
        // Other entity types use the label of the entity type for the 'Edit'
        // operation link text, but not taxonomy vocabularies, which use 'Edit'.
        'edit_bundle_link_text' => 'Edit',
        'base_url' => 'admin/structure/taxonomy',
        // Define the 'field_ui' operations to be tested.
        'operations' => [
          'overview',
          'overview/fields',
          'overview/permissions',
          'delete',
        ],
      ],
      // The permissions required to access and manage taxonomy vocabularies.
      'required_permissions' => [
        'access taxonomy overview',
        'administer taxonomy',
        'administer taxonomy_term fields',
      ],
    ],
    // Test core module: 'user'.
    'user_role' => [
      // The random IDs to be tested for the created user roles.
      'bundle_ids' => [
        // Use a negative offset to ignore the 'max_bundle_number' setting and
        // force all the created roles to be tested.
        'menu_links_offset' => -1,
        // Set the base weight for the created roles to '-1' to be ordered
        // before the default un-editable ones: 'anonymous' and 'authenticated'.
        'bundle_weight' => -1,
      ],
      // The user roles links to be tested under 'People'.
      'bundle_links' => [
        // Test the 'Add role' link text.
        'add_type_link_text' => 'Add role',
        // There is no 'All roles' link added by the module. Use the default
        // 'Roles' overview page for this test.
        'all_types_link_text' => 'Roles',
        'base_url' => 'admin/people/roles',
        // The permissions operation links have to be tested separately, since
        // they have a different base route, so use an empty operation so the
        // expected order of the links could stay consistent.
        'operations' => ['', 'delete'],
      ],
      // The 'add' content links to be tested under 'People'.
      'add_content_links' => [
        // Test the user overview page 'People'.
        'overview_page' => [
          'label' => 'People',
          'base_url' => 'admin/people',
          'position' => 5,
          'css_classes' => 'toolbar-icon toolbar-icon-entity-user-collection',
        ],
      ],
      // The permissions required to access and manage users and roles.
      'required_permissions' => [
        'administer users',
        'administer permissions',
      ],
    ],
    // Test core module: 'views'.
    'view' => [
      // The random IDs to be tested for the created views.
      'bundle_ids' => [
        // Use a negative offset to ignore the 'max_bundle_number' setting and
        // force all the created views to be tested.
        'menu_links_offset' => -1,
        // Prefix created views with 'AA' to be ordered before the default ones.
        'bundle_id_prefix' => 'AA',
      ],
      'bundle_links' => [
        // Test the 'Add view' link text.
        'add_type_link_text' => 'Add view',
        // There is no 'All views' link added by the module. Use the default
        // 'Views' overview page.
        'all_types_link_text' => 'Views',
        'base_url' => 'admin/structure/views',
        // Define the base operation route to be tested, defaults to 'manage'.
        'base_operation' => 'view',
        // The module currently does not add any operation links for views.
        'operations' => [],
      ],
      // The permissions required to access and manage views.
      'required_permissions' => [
        'administer views',
        // Required to access the reports pages added by the 'views' module.
        'access site reports',
      ],
    ],
  ];

  /**
   * {@inheritdoc}
   *
   * Extend parent's 'setUp' method to create all the required entity types
   * bundles and a test user with all the necessary permissions:
   * - Set the 'max_bundle_number' configuration setting.
   * - Create N entity bundles with random ID strings.
   * - Create and log in an administrative user.
   *
   * Set in this function the number of entities to create and the value of the
   * config setting 'max_bundle_number' used in the tests.
   */
  public function setUp(): void {
    parent::setUp();

    // Lower a bit the maximum number of bundles, so the tests could run a bit
    // faster, since fewer objects would have to be created (default value: 20).
    // Do not lower this value below 5 to test menu types, since there are 5
    // undeletable menu types created by default ('admin', 'main', etc...).
    $max_bundle_number = 7;
    $this->config('admin_toolbar_tools.settings')
      ->set('max_bundle_number', $max_bundle_number)
      ->save();

    // Set the default CSS classes used for testing links in the admin toolbar.
    $this->testAdminToolbarDefaultLinkCssClass = AdminToolbarToolsConstants::ADMIN_TOOLBAR_TOOLS_EXTRA_LINKS_TEST_CSS_CLASSES;

    /* Create custom objects. */

    // The number of custom entity bundles to create: Block content, Media, Node
    // types, menus, etc... The greater this value and the longer the tests will
    // take to run. Keep this value above the 'max_bundle_number' to fully test
    // the expected behavior of the module.
    $entity_bundle_ids_count = 10;

    // Create N content types with random ID strings, stored for the test in the
    // array $testEntityTypesExtraLinks['bundle_ids'] in 'raw' and 'chunked'.
    $this->createRandomEntityTypeBundles($max_bundle_number, $entity_bundle_ids_count);

    // Remove the 'permissions' operation link for 'comment_type' and
    // 'contact_form' entity types for Drupal 10 and below, since they do not
    // have a 'Manage permissions' link in these versions.
    if (version_compare(\Drupal::VERSION, '11', '<')) {
      if (($key = array_search('permissions', $this->testEntityTypesExtraLinks['comment_type']['bundle_links']['operations'])) !== FALSE) {
        unset($this->testEntityTypesExtraLinks['comment_type']['bundle_links']['operations'][$key]);
      }
      if (($key = array_search('permissions', $this->testEntityTypesExtraLinks['contact_form']['bundle_links']['operations'])) !== FALSE) {
        unset($this->testEntityTypesExtraLinks['contact_form']['bundle_links']['operations'][$key]);
      }
    }

    // Skip tests for 'block_content_type' for versions lower than Drupal 10.
    if (version_compare(\Drupal::VERSION, '10', '<')) {
      unset($this->testEntityTypesExtraLinks['block_content_type']);
      $this->testEntityTypesExtraLinks['comment_type']['add_content_links']['overview_page']['position'] = 2;
      $this->testEntityTypesExtraLinks['media_type']['add_content_links']['overview_page']['position'] = 4;
    }

    /* Setup users for the tests. */

    // Default permissions needed for the tests.
    $required_entity_permissions = [
      'access toolbar',
      // This permission is needed to test the 'SearchLinks' Controller.
      'use admin toolbar search',
      // Access to the menu links under '/admin/config/'.
      'access administration pages',
    ];
    // Merge all the required permissions for each entity type to be tested.
    foreach ($this->testEntityTypesExtraLinks as $parameters_array) {
      $required_entity_permissions = array_merge($required_entity_permissions, $parameters_array['required_permissions']);
    }

    // Create an admin user with access to the admin toolbar search and several
    // admin sections or routes, so the extra links added by the admin toolbar
    // tools module could be fully tested.
    $this->adminUser = $this->drupalCreateUser($required_entity_permissions);

    // Log in as an admin user to test admin pages.
    $this->drupalLogin($this->adminUser);

  }

  /**
   * Helper function to create N entity bundles with random ID strings.
   *
   * Used in the setup of the test to create N block content, media, content
   * types, menus, views, etc... The IDs of the created test entity bundles are
   * generated randomly, sorted alphabetically and stored in the 'raw' array
   * property of the test class.
   *
   * The array is also broken into two chunks and stored in the 'chunked' array
   * property, with the first one with the length of the 'max_bundle_number' and
   * the second one the rest of the items.
   * This is used to test the maximum number of links shown in the admin menu,
   * defined by the 'max_bundle_number' configuration setting.
   *
   * @param int $entity_bundle_ids_chunks_length
   *   The length of the chunks for the entity bundle IDs.
   * @param int $entity_bundle_ids_count
   *   The number of entity bundles to generate for the test.
   *
   * @return void
   *   Nothing to return.
   *
   * @see \Drupal\Tests\admin_toolbar_tools\Traits\AdminToolbarToolsEntityCreationTrait::setUp()
   * @see \Drupal\Tests\admin_toolbar_tools\Traits\AdminToolbarToolsEntityCreationTrait::createEntityTypeBundle()
   */
  protected function createRandomEntityTypeBundles(int $entity_bundle_ids_chunks_length = 0, int $entity_bundle_ids_count = 0): void {
    // Loop for each entity type defined in the test matrix.
    foreach ($this->testEntityTypesExtraLinks as $entity_type => $parameters_array) {
      // Modify the test array property of the class by reference.
      $test_entity_bundle_ids = &$this->testEntityTypesExtraLinks[$entity_type]['bundle_ids'];

      // Initialize the 'bundle_ids' array if not defined yet.
      if (!isset($parameters_array['bundle_ids']['raw'])) {
        $test_entity_bundle_ids['raw'] = [];
        $test_entity_bundle_ids['chunked'] = [];
      }

      // Create N entity bundles with random ID strings, stored for the test.
      for ($i = 0; $i < $entity_bundle_ids_count; $i++) {
        // Store in the array a randomly generated lowercase entity bundle ID.
        $test_entity_bundle_ids['raw'][] = strtolower($this->randomMachineName());
        // Create an entity bundle of the provided type with a random ID string.
        $this->createEntityTypeBundle($entity_type, $test_entity_bundle_ids['raw'][$i]);
      }

      // Sort the generated test entity bundle IDs in ascending order.
      sort($test_entity_bundle_ids['raw']);

      // Break the array of entity bundle IDs into two chunks to test the
      // maximum number of links shown in the admin menu.
      if (isset($parameters_array['bundle_ids']['menu_links_offset']) && !($parameters_array['bundle_ids']['menu_links_offset'] > 0)) {
        // If the 'menu_links_offset' is zero or negative, skip testing this
        // feature and do not break the array into chunks. This case is used
        // for user roles and views to test all the created bundles.
        $test_entity_bundle_ids['chunked'][0] = $test_entity_bundle_ids['raw'];
      }
      else {
        // Break the array of entity bundle IDs into two chunks, with the first
        // one having the chunk length and the second the rest of the elements.
        $chunk_length = $entity_bundle_ids_chunks_length - ($parameters_array['bundle_ids']['menu_links_offset'] ?? 0);
        $test_entity_bundle_ids['chunked'][0] = array_slice($test_entity_bundle_ids['raw'], 0, $chunk_length);
        $test_entity_bundle_ids['chunked'][1] = array_slice($test_entity_bundle_ids['raw'], $chunk_length);
      }
    }
  }

  /**
   * Helper function to create an entity type bundle with a random ID string.
   *
   * Provide a standard way to create an entity type bundle for any test class
   * using the trait.
   *
   * @param string $entity_type
   *   The entity type for which to create the bundle.
   * @param string $entity_type_bundle_id
   *   The entity type bundle ID to create.
   * @param string $entity_type_bundle_id_prefix
   *   (optional) A prefix to add to the entity type bundle label. Mostly used
   *   to force the alphabetical order of the created entity type bundles. This
   *   is useful to avoid mixing the created entity bundles with the default
   *   ones created by Drupal core, for example, 'contact', 'menu', 'user',
   *   'views', etc... so they could be ordered before or after.
   *   Defaults to an empty string (no prefix).
   *
   * @return void
   *   Nothing to return.
   *
   * @see \Drupal\Tests\admin_toolbar_tools\Traits\AdminToolbarToolsEntityCreationTrait::createRandomEntityTypeBundles()
   */
  protected function createEntityTypeBundle(string $entity_type, string $entity_type_bundle_id, string $entity_type_bundle_id_prefix = ''): void {
    // Add a prefix to the entity bundle ID, if provided.
    $prefix = empty($entity_type_bundle_id_prefix) ? $this->testEntityTypesExtraLinks[$entity_type]['bundle_ids']['bundle_id_prefix'] ?? '' : $entity_type_bundle_id_prefix;
    // The prefix is only added to the entity bundle label, not to the ID.
    $entity_type_bundle_label = $prefix . ucfirst($entity_type_bundle_id);

    // Prepare the values required to create the entity type bundle.
    switch ($entity_type) {
      // Special case for media types, since they require a 'source' value.
      case 'media_type':
        $value = [
          'id' => $entity_type_bundle_id,
          'label' => $entity_type_bundle_label,
          // Test with 'image' source, since it is available by default.
          'source' => 'image',
        ];
        break;

      // Special case for content types, since they require a 'type' value.
      case 'node_type':
        $value = [
          'type' => $entity_type_bundle_id,
          'name' => $entity_type_bundle_label,
        ];
        break;

      // Special case for taxonomy vocabularies, since they require a 'vid'.
      case 'taxonomy_vocabulary':
        $value = [
          'vid' => $entity_type_bundle_id,
          'name' => $entity_type_bundle_label,
        ];
        break;

      // Default case for entity types with no special creation parameters.
      // Support at least: block_content_type, comment_type, contact_form, menu,
      // user_role, view.
      default:
        $value = [
          'id' => $entity_type_bundle_id,
          'label' => $entity_type_bundle_label,
        ];
        break;
    }

    // Add a weight value if defined in the test matrix for the entity type.
    $entity_type_bundle_weight = $this->testEntityTypesExtraLinks[$entity_type]['bundle_ids']['bundle_weight'] ?? 0;
    if (!empty($entity_type_bundle_weight)) {
      $value['weight'] = $entity_type_bundle_weight;
    }

    // Create and save the entity type bundle.
    \Drupal::entityTypeManager()->getStorage($entity_type)
      ->create($value)
      ->save();
  }

  /**
   * Helper function to update an entity type bundle label.
   *
   * Provide a standard way to update an entity type bundle label for any test
   * class using the trait.
   * This is used to test that the updated label is reflected in the admin menu
   * and that the links are still working after the update.
   *
   * @param string $entity_type
   *   The entity type of the bundle for which the label should be updated.
   * @param string $entity_type_bundle_id
   *   The ID of the entity type bundle for which the label should be updated.
   * @param string $entity_type_bundle_label
   *   The new entity type bundle label.
   *
   * @return void
   *   Nothing to return.
   *
   * @see \Drupal\Tests\admin_toolbar_tools\Functional\AdminToolbarToolsExtraLinksTest::assertToolbarMenuExtraLinksUpdateDelete()
   */
  protected function updateEntityTypeBundleLabel(string $entity_type, string $entity_type_bundle_id, string $entity_type_bundle_label): void {
    // Load the entity type bundle.
    /** @var \Drupal\Core\Config\Entity\ConfigEntityInterface|null $entity_type_bundle */
    $entity_type_bundle = \Drupal::entityTypeManager()->getStorage($entity_type)
      ->load($entity_type_bundle_id);

    // Update the label if the entity type bundle exists.
    if ($entity_type_bundle) {
      // Dynamically get the entity type label field name to support any type.
      $label_field = $entity_type_bundle->getEntityType()->getKey('label');
      // Update and save the entity type bundle label.
      $entity_type_bundle->set($label_field, $entity_type_bundle_label)->save();
    }
  }

}
