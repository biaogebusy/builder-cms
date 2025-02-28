<?php

declare(strict_types=1);

namespace Drupal\Tests\easy_email\Functional;

use Drupal\Core\Serialization\Yaml;
use Drupal\easy_breadcrumb\EasyBreadcrumbConstants;
use Drupal\Tests\BrowserTestBase;

/**
 * Tests configuring easy_breadcrumb.
 *
 * @group easy_breadcrumb
 */
class EasyBreabcrumbConfigureTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['easy_breadcrumb'];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * Tests configuring easy_breadcrumb.
   */
  public function testAdministration() {
    $assert = $this->assertSession();

    $config_after_install = $this->config('easy_breadcrumb.settings')->get();
    $this->drupalGet('admin/config/user-interface/easy-breadcrumb');
    $assert->statusCodeEquals(403);

    $this->drupalLogin($this->createUser(['administer easy breadcrumb']));
    $this->drupalGet('admin/config/user-interface/easy-breadcrumb');
    $assert->statusCodeEquals(200);
    $this->submitForm([], 'Save configuration');
    $assert->statusCodeEquals(200);
    $assert->pageTextContainsOnce('The configuration options have been saved.');
    $this->assertSame($config_after_install, $this->config('easy_breadcrumb.settings')->get());
  }

  /**
   * Tests easy_breadcrumb_update_8006() on default install config.
   */
  public function test8006DefaultConfigurationUpdate() {
    $this->doUpdateTest(__DIR__ . '/fixtures/pre8006_default_configuration.yml');
  }

  /**
   * Tests easy_breadcrumb_update_8006() on config that has been saved via form.
   */
  public function test8006AfterFormSaveConfigurationUpdate() {
    $this->doUpdateTest(__DIR__ . '/fixtures/pre8006_after_form_save_configuration.yml');
  }

  /**
   * Tests easy_breadcrumb_update_8006().
   *
   * Calls the update method manually on a fixture.
   *
   * @param string $fixture
   *   Path to fixture.
   */
  protected function doUpdateTest($fixture) {
    $assert = $this->assertSession();
    \Drupal::moduleHandler()->loadInclude('easy_breadcrumb', 'install');

    // Reset the configuration to pre 8006 values. Directly write to the
    // database to avoid schema checking.
    $pre8006_install_configuration = Yaml::decode(file_get_contents($fixture));
    \Drupal::database()->update('config')
      ->fields([
        'data' => serialize($pre8006_install_configuration),
      ])
      ->condition('name', 'easy_breadcrumb.settings')
      ->condition('collection', '')
      ->execute();

    // Run the update.
    easy_breadcrumb_update_8006();
    $this->refreshVariables();

    $config_after_update = $this->config('easy_breadcrumb.settings')->get();
    $this->assertArrayNotHasKey('dependencies', $config_after_update);
    $this->assertArrayNotHasKey('add_structured_data_jsonld', $config_after_update);
    $this->assertArrayHasKey(EasyBreadcrumbConstants::ADD_STRUCTURED_DATA_JSON_LD, $config_after_update);
    $this->assertArrayHasKey(EasyBreadcrumbConstants::CAPITALIZATOR_FORCED_WORDS, $config_after_update);
    $this->assertArrayHasKey(EasyBreadcrumbConstants::INCLUDE_INVALID_PATHS, $config_after_update);
    $this->assertArrayHasKey(EasyBreadcrumbConstants::EXCLUDED_PATHS, $config_after_update);
    $this->assertArrayHasKey(EasyBreadcrumbConstants::REPLACED_TITLES, $config_after_update);
    $this->assertArrayHasKey(EasyBreadcrumbConstants::CUSTOM_PATHS, $config_after_update);
    $this->assertArrayHasKey(EasyBreadcrumbConstants::TITLE_SEGMENT_AS_LINK, $config_after_update);
    // Ensure that 'capitalizator_ignored_words' and
    // 'capitalizator_forced_words' are lists and not maps.
    $this->assertSame(array_keys($config_after_update[EasyBreadcrumbConstants::CAPITALIZATOR_IGNORED_WORDS]), array_keys(array_values($config_after_update[EasyBreadcrumbConstants::CAPITALIZATOR_IGNORED_WORDS])));
    $this->assertSame(array_keys($config_after_update[EasyBreadcrumbConstants::CAPITALIZATOR_FORCED_WORDS]), array_keys(array_values($config_after_update[EasyBreadcrumbConstants::CAPITALIZATOR_FORCED_WORDS])));

    // Easy configuration is not changed by visiting configuration form.
    $this->drupalLogin($this->createUser(['administer easy breadcrumb']));
    $this->drupalGet('admin/config/user-interface/easy-breadcrumb');
    $assert->statusCodeEquals(200);
    $this->submitForm([], 'Save configuration');
    $assert->statusCodeEquals(200);
    $assert->pageTextContainsOnce('The configuration options have been saved.');
    $this->assertSame($config_after_update, $this->config('easy_breadcrumb.settings')->get());
  }

}
