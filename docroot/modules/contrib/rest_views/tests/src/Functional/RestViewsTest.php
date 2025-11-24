<?php

namespace Drupal\Tests\rest_views\Functional;

use Drupal\node\Entity\Node;
use Drupal\Tests\BrowserTestBase;
use Drupal\user\Entity\User;
use Drupal\views\Tests\ViewTestData;
use Symfony\Component\Serializer\SerializerInterface;

/**
 * Create a REST view and check that it renders correctly.
 *
 * @group rest_views
 */
class RestViewsTest extends BrowserTestBase {

  /**
   * Views used by this test.
   *
   * @var array
   */
  public static array $testViews = ['rest_views_test'];

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'views',
    'rest_views',
    'rest_views_test_config',
    'node',
  ];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * The serializer service.
   *
   * @var \Symfony\Component\Serializer\SerializerInterface
   */
  protected SerializerInterface $serializer;

  /**
   * {@inheritdoc}
   */
  protected function setUp($import_test_views = TRUE, $modules = ['rest_views_test_config']): void {
    parent::setUp();

    $this->serializer = $this->container->get('serializer');
    $this->drupalCreateContentType(['type' => 'page']);
    ViewTestData::createTestViews(static::class, $modules);
  }

  /**
   * Create a test node and check the view for it.
   *
   * @throws \Behat\Mink\Exception\ExpectationException
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function testRestView(): void {
    $account = User::create([
      'name'   => $this->randomMachineName(),
      'bundle' => 'user',
      'status' => TRUE,
    ]);
    $account->save();

    $node = Node::create([
      'uid'   => $account->id(),
      'type'  => 'page',
      'title' => $this->randomString(),
    ]);
    $node->save();

    // Showing the entity reference field requires "access user profiles" perm.
    $expected_data = [
      [
        'nid'   => +$node->id(),
        'uid'   => NULL,
        'title' => htmlentities($node->label()),
      ],
    ];

    $this->drupalGet('/rest-views-test', ['query' => ['_format' => 'json']]);
    $this->assertSession()->responseHeaderEquals('Content-type', 'application/json');
    $this->assertSession()->responseContains($this->serializer->encode($expected_data, 'json'));

    // After logging in with the right permissions, the entity reference loads.
    $user = $this->drupalCreateUser(['access user profiles']);
    $this->drupalLogin($user);
    $expected_data[0]['uid'] = +$account->id();

    $this->drupalGet('/rest-views-test', ['query' => ['_format' => 'json']]);
    $this->assertSession()->responseHeaderEquals('Content-type', 'application/json');
    $this->assertSession()->responseContains($this->serializer->encode($expected_data, 'json'));
  }

}
