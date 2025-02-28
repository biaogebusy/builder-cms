<?php

namespace Drupal\Tests\image_effects\Functional;

use Drupal\Tests\BrowserTestBase;

/**
 * Settings form test.
 *
 * @group image_effects
 */
class SettingsFormTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'image_effects',
    'color',
    // @todo this test is not executed since the 'jquery_colorpicker' module is
    // throwing deprecations in D9.
    // @see https://www.drupal.org/project/image_effects/issues/3296336
    // 'jquery_colorpicker',
    'file_mdm',
    'file_mdm_exif',
    'file_mdm_font',
  ];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * Admin user.
   *
   * @var \Drupal\user\UserInterface
   */
  protected $adminUser;

  /**
   * {@inheritdoc}
   */
  public function setUp(): void {
    parent::setUp();
    // Create a user and log it in.
    $this->adminUser = $this->drupalCreateUser([
      'administer site configuration',
      'administer image styles',
    ]);
    $this->drupalLogin($this->adminUser);
  }

  /**
   * Settings form test.
   */
  public function testSettingsForm() {
    $admin_path = '/admin/config/media/image_effects';

    // Get the settings form.
    $this->drupalGet($admin_path);

    // Change the default color selector.
    $edit = [
      'settings[color_selector][plugin_id]' => 'farbtastic',
    ];
    $this->submitForm($edit, 'Save configuration');

    // Check config changed.
    $this->assertEquals('farbtastic', \Drupal::config('image_effects.settings')->get('color_selector.plugin_id'));

    // Change the default image selector.
    $config = \Drupal::configFactory()->getEditable('image_effects.settings');
    $config->set('image_selector.plugin_id', 'dropdown')->save();
    $this->drupalGet($admin_path);
    $edit = [
      'settings[image_selector][plugin_settings][path]' => 'private://',
    ];
    $this->submitForm($edit, 'Save configuration');

    // Check config changed.
    $this->assertEquals(['path' => 'private://'], \Drupal::config('image_effects.settings')->get('image_selector.plugin_settings.dropdown'));

    // Change the default font selector.
    $config = \Drupal::configFactory()->getEditable('image_effects.settings');
    $config->set('font_selector.plugin_id', 'dropdown')->save();
    $this->drupalGet($admin_path);
    $edit = [
      'settings[font_selector][plugin_settings][path]' => 'public://',
    ];
    $this->submitForm($edit, 'Save configuration');

    // Check config changed.
    $this->assertEquals(['path' => 'public://'], \Drupal::config('image_effects.settings')->get('font_selector.plugin_settings.dropdown'));
  }

  /**
   * Test JQuery Colorpicker color selector.
   *
   * @todo this test is not executed since the 'jquery_colorpicker' module is
   *   throwing deprecations in D9.
   *
   * @see https://www.drupal.org/project/image_effects/issues/3296336
   */
  public function __testJqueryColorpickerSelector(): void {
    $admin_path = '/admin/config/media/image_effects';

    // Get the settings form.
    $this->drupalGet($admin_path);

    // Change the default color selector.
    $edit = [
      'settings[color_selector][plugin_id]' => 'jquery_colorpicker',
    ];
    $this->submitForm($edit, 'Save configuration');

    // Check config changed.
    $this->assertEquals('jquery_colorpicker', \Drupal::config('image_effects.settings')->get('color_selector.plugin_id'));

    // Verify that the 'jquery_colorpicker' module cannot be uninstalled.
    $this->assertNotEquals([], \Drupal::service('module_installer')->validateUninstall(['jquery_colorpicker']));

    // Back to the default color selector.
    $edit = [
      'settings[color_selector][plugin_id]' => 'html_color',
    ];
    $this->submitForm($edit, 'Save configuration');

    // Verify that the 'jquery_colorpicker' module can be uninstalled now.
    $this->assertTrue(\Drupal::service('module_installer')->uninstall(['jquery_colorpicker']));
  }

}
