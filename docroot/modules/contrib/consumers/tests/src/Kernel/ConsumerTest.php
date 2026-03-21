<?php

namespace Drupal\Tests\consumers\Kernel;

use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\consumers\Entity\Consumer;
use Drupal\Core\Access\AccessException;
use Drupal\Core\Logger\LoggerChannelFactory;
use Drupal\KernelTests\KernelTestBase;
use Drupal\Tests\user\Traits\UserCreationTrait;
use Symfony\Component\ErrorHandler\BufferingLogger;

/**
 * Tests the exception handling in the Consumer entity.
 *
 * @group consumers
 */
class ConsumerTest extends KernelTestBase {

  use UserCreationTrait;

  /**
   * The consumer entity storage.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  protected EntityStorageInterface $consumerStorage;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'image',
    'file',
    'consumers',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    // Install the required entity schema.
    $this->installEntitySchema('consumer');
    $this->installEntitySchema('user');
    $this->installEntitySchema('file');

    // Get the consumer entity storage.
    $this->consumerStorage = $this->container->get('entity_type.manager')->getStorage('consumer');
  }

  /**
   * Tests exception handling in preSave method.
   *
   * @covers \Drupal\consumers\Entity\Consumer::preSave
   */
  public function testPreSaveExceptionHandling() {
    $admin_user = $this->setUpCurrentUser([], [], TRUE);
    $restricted_user = $this->createUser();

    // Create a Consumer entity.
    $consumer = Consumer::create([
      'label' => 'Test Consumer',
      'client_id' => 'test_client_id',
      'is_default' => TRUE,
    ]);
    $consumer->setOwner($admin_user);
    $consumer->save();

    // Create a Consumer entity.
    $consumer2 = Consumer::create([
      'label' => 'Test Consumer',
      'client_id' => 'test_client_id',
      'is_default' => FALSE,
    ]);
    $consumer->setOwner($admin_user);
    $consumer2->save();

    // Mock the logger service.
    $logger = new BufferingLogger();
    $logger_factory = $this->createMock(LoggerChannelFactory::class);
    $logger_factory->expects($this->once())
      ->method('get')
      ->with('consumers')
      ->willReturn($logger);
    $this->container->set('logger.factory', $logger_factory);

    // As a non-authorized user, update the is_default flag to FALSE and try to
    // save the consumer again. This should trigger an error in the
    // removeDefaultConsumerFlags() method, and revert the is_default flag to
    // FALSE.
    $this->setCurrentUser($restricted_user);
    $consumer2->set('is_default', TRUE);
    $this->assertEquals(1, $consumer2->get('is_default')->value);
    $consumer2->save();

    // Expect the logger to log the exception.
    $log_message = $logger->cleanLogs()[0];
    $this->assertInstanceOf(AccessException::class, $log_message[2]['exception']);
    $this->assertStringContainsString('Unable to change the current default consumer. Permission denied.', $log_message[2]['@message']);

    // Reload the updated consumer.
    $saved_consumer = $this->consumerStorage->load($consumer2->id());

    // Verify that is_default is set to FALSE.
    $this->assertEquals(0, $saved_consumer->get('is_default')->value);

    $this->setCurrentUser($admin_user);
    $saved_consumer->set('is_default', TRUE);
    $saved_consumer->save();

    // Reload the updated consumer.
    $saved_consumer = $this->consumerStorage->load($saved_consumer->id());

    // Verify that is_default is set to TRUE.
    $this->assertEquals(1, $saved_consumer->get('is_default')->value);
  }

}
