<?php

namespace Drupal\Tests\panelizer\Functional;

use Drupal\Component\Serialization\Json;
use Drupal\Tests\BrowserTestBase;

/**
 * Confirm that the IPE functionality works.
 *
 * @group panelizer
 */
class PanelizerIpeTest extends BrowserTestBase {

  use PanelizerTestTrait;

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'node',
    'field_ui',
    'user',
    'panels_ipe',
    'panelizer',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->createContentType(['type' => $this->content_type]);

    $this->container->get('panelizer')
      ->setPanelizerSettings('node', $this->content_type, 'default', [
        'enable' => TRUE,
        'allow' => FALSE,
        'custom' => TRUE,
        'default' => 'default',
      ]);

    // Reload all caches.
    $this->rebuildAll();
  }

  /**
   * The content type that will be tested against.
   *
   * @string
   */
  protected $content_type = 'page';

  /**
   * Create a user with the required permissions.
   *
   * @param array $perms
   *   Any additiona permissions that need to be added.
   *
   * @return \Drupal\user\Entity\User
   *   The user account that was created.
   */
  protected function createAdminUser(array $perms = []) {
    $perms += [
      // From system.
      'access administration pages',

      // Content permissions.
      'access content',
      'administer content types',
      'administer nodes',
      'create page content',
      'edit any page content',
      'edit own page content',

      // From Field UI.
      'administer node display',

      // From Panels.
      'access panels in-place editing',
    ];
    return $this->drupalCreateUser($perms);
  }

  /**
   * Test that the IPE functionality as user 1, which should cover all options.
   */
  public function testAdminUser() {
    // Create a test node.
    $node = $this->createTestNode();

    // Log in as user 1.
    $this->loginUser1();

    // Load the test node.
    $this->drupalGet('node/' . $node->id());
    $this->assertSession()->statusCodeEquals(200);

    // Confirm the JSON Drupal settings are appropriate.
    $drupalSettings = NULL;
    $matches = [];
    if (preg_match('@<script type="application/json" data-drupal-selector="drupal-settings-json">([^<]*)</script>@', $this->getSession()->getPage()->getContent(), $matches)) {
      $drupalSettings = Json::decode($matches[1]);
    }
    $this->assertNotNull($drupalSettings);
    if (!empty($drupalSettings)) {
      $this->assertArrayHasKey('panels_ipe', $drupalSettings);
      $this->assertArrayHasKey('regions', $drupalSettings['panels_ipe']);
      $this->assertArrayHasKey('layout', $drupalSettings['panels_ipe']);
      $this->assertArrayHasKey('user_permission', $drupalSettings['panels_ipe']);
      $this->assertArrayHasKey('panels_display', $drupalSettings['panels_ipe']);
      $this->assertArrayHasKey('unsaved', $drupalSettings['panels_ipe']);
      $this->assertArrayHasKey('panelizer', $drupalSettings);
      $this->assertArrayHasKey('entity', $drupalSettings['panelizer']);
      $this->assertArrayHasKey('entity_type_id', $drupalSettings['panelizer']['entity']);
      $this->assertEquals($drupalSettings['panelizer']['entity']['entity_type_id'], 'node');
      $this->assertArrayHasKey('entity_id', $drupalSettings['panelizer']['entity']);
      $this->assertEquals($drupalSettings['panelizer']['entity']['entity_id'], $node->id());
      $this->assertArrayHasKey('user_permission', $drupalSettings['panelizer']);
      $this->assertArrayHasKey('revert', $drupalSettings['panelizer']['user_permission']);
      $this->assertArrayHasKey('save_default', $drupalSettings['panelizer']['user_permission']);
    }
  }

  /**
   * Confirm the 'administer panelizer' permission works.
   */
  public function testAdministerPanelizerPermission() {
    // Create a test node.
    $node = $this->createTestNode();

    // Create a new user with the permissions being tested.
    $perms = [
      'administer panelizer',
    ];
    $account = $this->createAdminUser($perms);
    $this->drupalLogin($account);

    // Load the test node.
    $this->drupalGet('node/' . $node->id());
    $this->assertSession()->statusCodeEquals(200);

    // Confirm the appropriate DOM structures are present for the IPE.
    $drupalSettings = NULL;
    $matches = [];
    if (preg_match('@<script type="application/json" data-drupal-selector="drupal-settings-json">([^<]*)</script>@', $this->getSession()->getPage()->getContent(), $matches)) {
      $drupalSettings = Json::decode($matches[1]);
    }
    $this->assertNotNull($drupalSettings);
    if (!empty($drupalSettings)) {
      $this->assertArrayHasKey('panels_ipe', $drupalSettings);
      $this->assertArrayHasKey('regions', $drupalSettings['panels_ipe']);
      $this->assertArrayHasKey('layout', $drupalSettings['panels_ipe']);
      $this->assertArrayHasKey('user_permission', $drupalSettings['panels_ipe']);
      $this->assertArrayHasKey('panels_display', $drupalSettings['panels_ipe']);
      $this->assertArrayHasKey('unsaved', $drupalSettings['panels_ipe']);
      $this->assertArrayHasKey('panelizer', $drupalSettings);
      $this->assertArrayHasKey('entity', $drupalSettings['panelizer']);
      $this->assertArrayHasKey('entity_type_id', $drupalSettings['panelizer']['entity']);
      $this->assertEquals($drupalSettings['panelizer']['entity']['entity_type_id'], 'node');
      $this->assertArrayHasKey('entity_id', $drupalSettings['panelizer']['entity']);
      $this->assertEquals($drupalSettings['panelizer']['entity']['entity_id'], $node->id());
      $this->assertArrayHasKey('user_permission', $drupalSettings['panelizer']);
      $this->assertArrayHasKey('revert', $drupalSettings['panelizer']['user_permission']);
      $this->assertArrayHasKey('save_default', $drupalSettings['panelizer']['user_permission']);
      $this->assertTrue($drupalSettings['panelizer']['user_permission']['revert']);
      $this->assertTrue($drupalSettings['panelizer']['user_permission']['save_default']);
    }
  }

  /**
   * @todo Confirm the 'set panelizer default' permission works.
   */
  // public function testSetDefault() {
  // }

  /**
   * @todo Confirm the 'administer panelizer $entity_type_id $bundle defaults'
   * permission works.
   */
  // public function testAdministerEntityDefaults() {
  // }

  /**
   * @todo Confirm the 'administer panelizer $entity_type_id $bundle content'
   * permission works.
   */
  public function testAdministerEntityContentPermission() {
    // Need the node for the tests below, so create it now.
    $node = $this->createTestNode();

    $perms = [
      'administer panelizer node page content',
    ];
    $drupalSettings = $this->setupPermissionTests($perms, $node);
    $this->assertNotNull($drupalSettings);

    // @todo How to tell if the user can change the display or add new items vs
    // other tasks?
    if (!empty($drupalSettings)) {
      $this->assertArrayHasKey('panels_ipe', $drupalSettings);
      $this->assertArrayHasKey('regions', $drupalSettings['panels_ipe']);
      $this->assertArrayHasKey('layout', $drupalSettings['panels_ipe']);
      $this->assertArrayHasKey('user_permission', $drupalSettings['panels_ipe']);
      $this->assertArrayHasKey('panels_display', $drupalSettings['panels_ipe']);
      $this->assertArrayHasKey('unsaved', $drupalSettings['panels_ipe']);
      $this->assertArrayHasKey('panelizer', $drupalSettings);
      $this->assertArrayHasKey('entity', $drupalSettings['panelizer']);
      $this->assertArrayHasKey('entity_type_id', $drupalSettings['panelizer']['entity']);
      $this->assertEquals($drupalSettings['panelizer']['entity']['entity_type_id'], 'node');
      $this->assertArrayHasKey('entity_id', $drupalSettings['panelizer']['entity']);
      $this->assertEquals($drupalSettings['panelizer']['entity']['entity_id'], $node->id());
      $this->assertArrayHasKey('user_permission', $drupalSettings['panelizer']);
      $this->assertArrayHasKey('revert', $drupalSettings['panelizer']['user_permission']);
      $this->assertArrayHasKey('save_default', $drupalSettings['panelizer']['user_permission']);
    }
  }

  /**
   * @todo Confirm the 'administer panelizer $entity_type_id $bundle layout'
   * permission works.
   */
  public function testAdministerEntityLayoutPermission() {
    // Need the node for the tests below, so create it now.
    $node = $this->createTestNode();

    // Test with just the 'layout' permission
    $perms = [
      'administer panelizer node page layout',
    ];
    $drupalSettings = $this->setupPermissionTests($perms, $node);
    $this->assertNotNull($drupalSettings);

    if (!empty($drupalSettings)) {
      $this->assertFalse(isset($drupalSettings['panels_ipe']));
      $this->assertFalse(isset($drupalSettings['panelizer']));
    }

    // Make sure the user is logged out before doing another pass.
    $this->drupalLogout();

    // Test with the 'revert' and the 'content' permission.
    $perms = [
      // The permission to be tested.
      'administer panelizer node page layout',
      // This permission has to be enabled for the 'revert' permission to work.
      'administer panelizer node page content',
    ];
    $drupalSettings = $this->setupPermissionTests($perms, $node);
    $this->assertNotNull($drupalSettings);

    // @todo How to tell if the user can change the layout vs other tasks?
    if (!empty($drupalSettings)) {
      $this->assertArrayHasKey('panels_ipe', $drupalSettings);
      $this->assertArrayHasKey('regions', $drupalSettings['panels_ipe']);
      $this->assertArrayHasKey('layout', $drupalSettings['panels_ipe']);
      $this->assertArrayHasKey('user_permission', $drupalSettings['panels_ipe']);
      $this->assertArrayHasKey('panels_display', $drupalSettings['panels_ipe']);
      $this->assertArrayHasKey('unsaved', $drupalSettings['panels_ipe']);
      $this->assertArrayHasKey('panelizer', $drupalSettings);
      $this->assertArrayHasKey('entity', $drupalSettings['panelizer']);
      $this->assertArrayHasKey('entity_type_id', $drupalSettings['panelizer']['entity']);
      $this->assertEquals($drupalSettings['panelizer']['entity']['entity_type_id'], 'node');
      $this->assertArrayHasKey('entity_id', $drupalSettings['panelizer']['entity']);
      $this->assertEquals($drupalSettings['panelizer']['entity']['entity_id'], $node->id());
      $this->assertArrayHasKey('user_permission', $drupalSettings['panelizer']);
      $this->assertArrayHasKey('revert', $drupalSettings['panelizer']['user_permission']);
      $this->assertArrayHasKey('save_default', $drupalSettings['panelizer']['user_permission']);
      $this->assertFalse($drupalSettings['panelizer']['user_permission']['revert']);
      $this->assertFalse($drupalSettings['panelizer']['user_permission']['save_default']);
    }
  }

  /**
   * @todo Confirm the 'administer panelizer $entity_type_id $bundle revert'
   * permission works.
   */
  public function testAdministerEntityRevertPermission() {
    // Need the node for the tests below, so create it now.
    $node = $this->createTestNode();

    // Test with just the 'revert' permission
    $perms = [
      'administer panelizer node page revert',
    ];
    $drupalSettings = $this->setupPermissionTests($perms, $node);
    $this->assertNotNull($drupalSettings);

    if (!empty($drupalSettings)) {
      $this->assertArrayNotHasKey('panels_ipe', $drupalSettings);
      $this->assertArrayNotHasKey('panelizer', $drupalSettings);
    }

    // Make sure the user is logged out before doing another pass.
    $this->drupalLogout();

    // Test with the 'revert' and the 'content' permission.
    $perms = [
      // The permission to be tested.
      'administer panelizer node page revert',
      // This permission has to be enabled for the 'revert' permission to work.
      'administer panelizer node page content',
    ];
    $drupalSettings = $this->setupPermissionTests($perms, $node);
    $this->assertNotNull($drupalSettings);

    if (!empty($drupalSettings)) {
      $this->assertArrayHasKey('panels_ipe', $drupalSettings);
      $this->assertArrayHasKey('regions', $drupalSettings['panels_ipe']);
      $this->assertArrayHasKey('layout', $drupalSettings['panels_ipe']);
      $this->assertArrayHasKey('user_permission', $drupalSettings['panels_ipe']);
      $this->assertArrayHasKey('panels_display', $drupalSettings['panels_ipe']);
      $this->assertArrayHasKey('unsaved', $drupalSettings['panels_ipe']);
      $this->assertArrayHasKey('panelizer', $drupalSettings);
      $this->assertArrayHasKey('entity', $drupalSettings['panelizer']);
      $this->assertArrayHasKey('entity_type_id', $drupalSettings['panelizer']['entity']);
      $this->assertEquals($drupalSettings['panelizer']['entity']['entity_type_id'], 'node');
      $this->assertArrayHasKey('entity_id', $drupalSettings['panelizer']['entity']);
      $this->assertEquals($drupalSettings['panelizer']['entity']['entity_id'], $node->id());
      $this->assertArrayHasKey('user_permission', $drupalSettings['panelizer']);
      $this->assertArrayHasKey('revert', $drupalSettings['panelizer']['user_permission']);
      $this->assertArrayHasKey('save_default', $drupalSettings['panelizer']['user_permission']);
      $this->assertTrue($drupalSettings['panelizer']['user_permission']['revert']);
      $this->assertFalse($drupalSettings['panelizer']['user_permission']['save_default']);
    }
  }

  /**
   * Do the necessary setup work for the individual permissions tests.
   *
   * @param array $perms
   *   Any additiona permissions that need to be added.
   * @param obj $node
   *   The node to test against, if none provided one will be generated.
   *
   * @return array
   *   The full drupalSettings JSON structure in array format.
   */
  protected function setupPermissionTests(array $perms, $node = NULL) {
    // Create a new user with the permissions being tested.
    $account = $this->createAdminUser($perms);
    $this->drupalLogin($account);

    // Make sure there's a test node to work with.
    if (empty($node)) {
      $node = $this->createTestNode();
    }

    // Load the test node.
    $this->drupalGet('node/' . $node->id());
    $this->assertSession()->statusCodeEquals(200);

    // Extract the drupalSettings structure and return it.
    $drupalSettings = [];
    $matches = [];
    if (preg_match('@<script type="application/json" data-drupal-selector="drupal-settings-json">([^<]*)</script>@', $this->getSession()->getPage()->getContent(), $matches)) {
      $drupalSettings = Json::decode($matches[1]);
    }
    return $drupalSettings;
  }

}
