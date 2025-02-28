<?php

namespace Drupal\Tests\entity_print\FunctionalJavascript;

use Drupal\FunctionalJavascriptTests\WebDriverTestBase;

/**
 * Entity Print Admin tests.
 *
 * @group Entity Print
 */
class EntityPrintAdminTest extends WebDriverTestBase {

  /**
   * Modules to enable.
   *
   * @var array
   */
  protected static $modules = ['node', 'entity_print_test', 'field', 'field_ui'];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * The node object to test against.
   *
   * @var \Drupal\node\Entity\Node
   */
  protected $node;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    // Create a content type and a dummy node.
    $this->drupalCreateContentType([
      'type' => 'page',
      'name' => 'Page',
    ]);
    $this->node = $this->drupalCreateNode();

    $account = $this->createUser([
      'bypass entity print access',
      'administer entity print',
      'access content',
      'administer content types',
      'administer node display',
      'administer user display',
    ]);
    $this->drupalLogin($account);
  }

  /**
   * Test the configuration form and expected settings.
   */
  public function testAdminSettings() {
    $assert = $this->assertSession();

    $this->drupalGet('/admin/config/content/entityprint');
    // The default implementation is Dompdf but that is not available in tests
    // make sure its settings form is not rendered.
    $assert->pageTextNotContains('Dompdf Settings');

    // Make sure we also get a warning telling us to install it.
    $assert->pageTextContains('Dompdf is not available because it is not configured. Please install with:');

    // Ensure saving the form without any PDF engine selected doesn't blow up.
    $this->submitForm([], 'Save configuration');

    // Assert the intial config values.
    $this->getSession()->getPage()->fillField('pdf', 'testprintengine');
    $this->assertSession()->assertWaitOnAjaxRequest();
    $this->getSession()->getPage()->fillField('word_docx', 'test_word_print_engine');
    $this->assertSession()->assertWaitOnAjaxRequest();

    $assert->fieldValueEquals('testprintengine[test_engine_setting]', 'initial value');
    $assert->fieldValueEquals('test_word_print_engine[test_word_setting]', 'my-default');

    // Ensure the plugin gets the chance to validate the form.
    $this->submitForm([
      'pdf' => 'testprintengine',
      'word_docx' => 'test_word_print_engine',
      'testprintengine[test_engine_setting]' => 'rejected',
    ], 'Save configuration');
    $assert->pageTextContains('Setting has an invalid value');

    $this->submitForm([
      'default_css' => 0,
      'force_download' => 0,
      'pdf' => 'testprintengine',
      'word_docx' => 'test_word_print_engine',
      'test_word_print_engine[test_word_setting]' => 'test word setting',
      'testprintengine[test_engine_setting]' => 'testvalue',
    ], 'Save configuration');

    /** @var \Drupal\entity_print\Entity\PrintEngineStorageInterface $config_entity */
    $config_entity = \Drupal::entityTypeManager()->getStorage('print_engine')->load('testprintengine');
    // Assert the expected settings were stored.
    $this->assertEquals('testprintengine', $config_entity->id());
    $this->assertEquals(['test_engine_setting' => 'testvalue', 'test_engine_suffix' => 'overridden'], $config_entity->getSettings());
    $this->assertEquals('entity_print_test', $config_entity->getDependencies()['module'][0]);

    $config_entity = \Drupal::entityTypeManager()->getStorage('print_engine')->load('test_word_print_engine');
    $this->assertEquals(['test_word_setting' => 'test word setting'], $config_entity->getSettings());

    // Assert that the testprintengine is actually used.
    $this->drupalGet('/print/pdf/node/1');
    $assert->pageTextContains('Using testprintengine - overridden');
  }

}
