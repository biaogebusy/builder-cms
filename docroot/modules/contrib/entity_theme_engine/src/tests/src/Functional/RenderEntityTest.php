<?php

namespace Drupal\entity_theme_engine\Tests\Functional;

use Drupal\Tests\BrowserTestBase;
use Drupal\node\Entity\Node;

/**
 * Class RenderEntityTest
 * @package Drupal\entity_theme_engine\Tests\Functional
 */
class RenderEntityTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  public static $modules = ['node', 'user', 'entity_theme_engine'];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected $userId = 0;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    
    $this->drupalCreateContentType(['type' => 'article', 'name' => 'Article']);
    $this->user = $this->drupalCreateUser([
      'access administration pages', 'administer entity widget entities',
      'create article content'
    ]);
    $this->drupalLogin($this->user);
    $this->userId = $this->user->id();
  }

  /**
   * {@inheritdoc}
   */
  public function testAddWidget() {
    $this->drupalGet('/admin/structure/entity-widget');
    $this->assertSession()->pageTextContains('There are no entity widget entities yet.');

    $this->drupalGet('/admin/structure/entity-widget/add');
    $edit = [];
    $label = $this->randomMachineName();
    $edit['label'] = $label;
    $edit['id'] = strtolower($label);
    $edit['entity_type'] = 'node';
    $edit['bundle'] = 'article';
    $edit['display'] = 'default';
    $edit['template[value]'] = '<p>Hello World</p>';
    $this->submitForm($edit, 'Save');
    $this->assertSession()->statusCodeEquals(200);

    
    $this->drupalGet('/admin/structure/entity-widget');
    $this->assertSession()->pageTextNotContains('There are no entity widget entities yet.');
  }

  /**
   * @depends testAddWidget
   */
  public function testNodeTemplate() {
    $nids = \Drupal::entityTypeManager()->getStorage('node')->getQuery()
      ->accessCheck(FALSE)
      ->sort('nid', 'DESC')
      ->range(0, 1)
      ->execute();
    $max_nid = reset($nids);
    $test_nid = $max_nid + mt_rand(1000, 1000000);
    $title = $this->randomMachineName(8);
    $data = [
      'title' => $title,
      'body' => [['value' => $this->randomMachineName(32)]],
      'uid' => $this->userId,
      'type' => 'article',
      'nid' => $test_nid,
    ];
    /** @var \Drupal\node\NodeInterface $node */
    $node = Node::create($data);
    $node->enforceIsNew();
    $node->save();

    $this->drupalGet('/admin/structure/entity-widget/add');
    $edit = [];
    $label = $this->randomMachineName();
    $edit['label'] = $label;
    $edit['id'] = strtolower($label);
    $edit['entity_type'] = 'node';
    $edit['bundle'] = 'article';
    $edit['display'] = 'json';
    $edit['template[value]'] = '{content:"Hello World"}';
    $this->submitForm($edit, 'Save');
    $this->assertSession()->statusCodeEquals(200);

    $entity = \Drupal::entityTypeManager()->getStorage('node')->load($test_nid);
    $this->assertNotEmpty($entity);

    $entityWidgetService = $this->container->get('entity_theme_engine.entity_widget_service');
    $content = (string)$entityWidgetService->getEntityRawContent($entity, 'json');
    $this->assertStringContainsString('Hello World', $content);
    // $widget = $entityWidgetService->getWidget($entity, 'json');
    // $variables = $entityWidgetService->getRenderVariables([], $widget, $entity, []);
  }

}