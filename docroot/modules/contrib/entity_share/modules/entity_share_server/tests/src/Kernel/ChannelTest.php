<?php

declare(strict_types=1);

namespace Drupal\Tests\entity_share_server\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\Tests\user\Traits\UserCreationTrait;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Tests channels.
 *
 * @group entity_share_server
 */
class ChannelTest extends KernelTestBase {

  use UserCreationTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'field',
    'text',
    'filter',
    'serialization',
    'file',
    'jsonapi',
    'entity_share',
    'entity_share_server',
    'entity_test',
  ];

  /**
   * The stacked http kernel service.
   *
   * @var \Symfony\Component\HttpKernel\HttpKernelInterface
   */
  protected $httpKernel;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->httpKernel = $this->container->get('http_kernel');
    $this->entityTypeManager = $this->container->get('entity_type.manager');

    $this->installEntitySchema('user');
    $this->installEntitySchema('entity_test');
    entity_test_create_bundle('entity_test');
  }

  /**
   * Tests the JSONAPI preview of a channel.
   */
  public function testChannelPreview(): void {
    $channel_storage = $this->entityTypeManager->getStorage('channel');
    $channel_storage->create([
      'id' => 'test_jsonapi',
      'label' => $this->randomString(),
      'channel_maxsize' => 50,
      'channel_entity_type' => 'entity_test',
      'channel_bundle' => 'entity_test',
      'channel_langcode' => 'en',
      'access_by_permission' => TRUE,
      'authorized_roles' => [],
      'authorized_users' => [],
    ])->save();

    $user = $this->createUser([
      'entity_share_server_access_channels',
    ]);
    $this->container->get('current_user')->setAccount($user);

    $request = Request::create('/admin/config/services/entity_share/channel');

    $response = $this->httpKernel->handle($request);
    $this->assertEquals(Response::HTTP_OK, $response->getStatusCode());

    $body = $response->getContent();
    // Do naive string searches.
    // @todo Update this when
    // https://www.drupal.org/project/drupal/issues/3390193 gets into core.
    $this->assertStringContainsString('Show JSON:API', $body);
    $this->assertStringContainsString('http://localhost/jsonapi/entity_test/entity_test?filter%5Blangcode-filter%5D%5Bcondition%5D%5Bpath%5D=langcode&amp;filter%5Blangcode-filter%5D%5Bcondition%5D%5Boperator%5D=%3D&amp;filter%5Blangcode-filter%5D%5Bcondition%5D%5Bvalue%5D=en&amp;page%5Blimit%5D=50', $body);
  }

}
