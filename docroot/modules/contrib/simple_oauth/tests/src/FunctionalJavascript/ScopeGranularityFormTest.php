<?php

namespace Drupal\Tests\simple_oauth\FunctionalJavascript;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\FunctionalJavascriptTests\WebDriverTestBase;
use Drupal\simple_oauth\Entity\Oauth2Scope;
use Drupal\simple_oauth\Plugin\ScopeGranularityInterface;

/**
 * Tests the scope granularity form.
 */
class ScopeGranularityFormTest extends WebdriverTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['simple_oauth_test'];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * The entity type manager used in the test.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $adminUser = $this->createUser([
      'add oauth2 scopes',
      'administer oauth2 scopes',
    ]);
    $this->drupalLogin($adminUser);

    $this->entityTypeManager = $this->container->get('entity_type.manager');
  }

  /**
   * Tests the granularity plugin form as part of the scope form.
   */
  public function testGranularityForm() {
    $this->drupalGet('/admin/config/people/simple_oauth/oauth2_scope/dynamic/add');
    $page = $this->getSession()->getPage();
    $page->fillField('Machine-readable name', 'test');
    $page->fillField('Description', 'Test');
    $page->checkField('Authorization Code');
    $page->selectFieldOption('Granularity', 'Test');
    $this->assertSession()->assertWaitOnAjaxRequest();
    $page->fillField('Minimum length', 5);
    $page->pressButton('Save scope');
    $scopeStorage = $this->entityTypeManager->getStorage('oauth2_scope');
    $scope = $scopeStorage->load('test');
    $this->assertInstanceOf(Oauth2Scope::class, $scope);
    $granularity = $scope->getGranularity();
    $this->assertInstanceOf(ScopeGranularityInterface::class, $granularity);
    $this->assertEquals('test', $granularity->getPluginId());
    $this->assertEquals(['min_length' => 5, 'max_length' => NULL], $granularity->getConfiguration());

    $this->drupalGet('/admin/config/people/simple_oauth/oauth2_scope/dynamic/test/edit');
    $page->fillField('Maximum length', 20);
    $page->pressButton('Save scope');
    $scopeStorage->resetCache(['test']);
    $scope = $scopeStorage->load('test');
    $this->assertInstanceOf(Oauth2Scope::class, $scope);
    $granularity = $scope->getGranularity();
    $this->assertInstanceOf(ScopeGranularityInterface::class, $granularity);
    $this->assertEquals('test', $granularity->getPluginId());
    $this->assertEquals(['min_length' => 5, 'max_length' => 20], $granularity->getConfiguration());

    $this->drupalGet('/admin/config/people/simple_oauth/oauth2_scope/dynamic/test/edit');
    $page->selectFieldOption('Granularity', 'Permission');
    $this->assertSession()->assertWaitOnAjaxRequest();
    $page->selectFieldOption('Permission', 'View published content');
    $page->pressButton('Save scope');
    $scopeStorage->resetCache(['test']);
    $scope = $scopeStorage->load('test');
    $this->assertInstanceOf(Oauth2Scope::class, $scope);
    $granularity = $scope->getGranularity();
    $this->assertInstanceOf(ScopeGranularityInterface::class, $granularity);
    $this->assertEquals('permission', $granularity->getPluginId());
    $this->assertEquals(['permission' => 'access content'], $granularity->getConfiguration());
  }

}
