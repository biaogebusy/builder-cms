<?php

namespace Drupal\Tests\ai\Kernel;

use Drupal\ai\Entity\AiFile;
use Drupal\ai\Entity\AiFileInterface;
use Drupal\KernelTests\KernelTestBase;
use Drupal\user\Entity\User;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the AiFile content entity basics.
 *
 * @group ai
 */
#[RunTestsInSeparateProcesses]
class AiFileEntityTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'field',
    'text',
    'file',
    'ai',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    // Install required entity schemas in dependency order.
    $this->installEntitySchema('user');
    $this->installEntitySchema('file');
    // file_usage is a regular schema table, not an entity schema.
    $this->installSchema('file', ['file_usage']);
    $this->installEntitySchema('ai_file');
  }

  /**
   * Test creation and metadata handling.
   */
  public function testAiFileEntityLifecycle(): void {
    // Create a user.
    $account = User::create([
      'name' => 'tester',
      'status' => 1,
    ]);
    $account->save();

    /** @var \Drupal\ai\Entity\AiFileInterface $file */
    $file = AiFile::create([
      'provider' => 'test_provider',
      'filename' => 'example.txt',
      'mime_type' => 'text/plain',
      'size' => 123,
      'uid' => $account->id(),
    ]);
    $file->setMetadata(['foo' => 'bar']);
    $file->save();

    $loaded = AiFile::load($file->id());
    $this->assertNotNull($loaded, 'Entity loaded.');
    $this->assertEquals('example.txt', $loaded->getFilename());
    $this->assertEquals('test_provider', $loaded->getProvider());
    $this->assertEquals(['foo' => 'bar'], $loaded->getMetadata());
    $this->assertEquals(AiFileInterface::PURPOSE_USER_DATA, $loaded->getPurpose(), 'Default purpose should be user_data');

    // Merge metadata.
    $loaded->mergeMetadata(['baz' => 'qux']);
    $this->assertEquals(['foo' => 'bar', 'baz' => 'qux'], $loaded->getMetadata());

    // Change purpose.
    $loaded->setPurpose(AiFileInterface::PURPOSE_FINE_TUNE)->save();
    $this->assertEquals(AiFileInterface::PURPOSE_FINE_TUNE, AiFile::load($file->id())->getPurpose());
  }

  /**
   * Test loading by purpose via service helper.
   */
  public function testLoadByPurpose(): void {
    $account = User::create([
      'name' => 'purpose-user',
      'status' => 1,
    ]);
    $account->save();

    // Create two files with different purposes.
    $file_user = AiFile::create([
      'provider' => 'test_provider',
      'filename' => 'user_data.txt',
      'mime_type' => 'text/plain',
      'size' => 50,
      'uid' => $account->id(),
      'purpose' => AiFileInterface::PURPOSE_USER_DATA,
    ]);
    $file_user->save();

    $file_ft = AiFile::create([
      'provider' => 'test_provider',
      'filename' => 'finetune.jsonl',
      'mime_type' => 'application/jsonl',
      'size' => 150,
      'uid' => $account->id(),
      'purpose' => AiFileInterface::PURPOSE_FINE_TUNE,
    ]);
    $file_ft->save();

    /** @var \Drupal\ai\Service\AiFileManager $manager */
    $manager = $this->container->get('ai.file_manager');

    $userData = $manager->loadByPurpose(AiFileInterface::PURPOSE_USER_DATA, (int) $account->id());
    $this->assertCount(1, $userData, 'One user_data file returned');
    $this->assertEquals('user_data.txt', reset($userData)->getFilename());

    $fineTune = $manager->loadByPurpose(AiFileInterface::PURPOSE_FINE_TUNE, (int) $account->id());
    $this->assertCount(1, $fineTune, 'One fine-tune file returned');
    $this->assertEquals('finetune.jsonl', reset($fineTune)->getFilename());

    $allFineTune = $manager->loadByPurpose(AiFileInterface::PURPOSE_FINE_TUNE);
    $this->assertGreaterThanOrEqual(1, count($allFineTune), 'Fine-tune file visible without owner filter');
  }

}
