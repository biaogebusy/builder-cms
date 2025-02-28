<?php

namespace Drupal\Tests\replication\Functional;

use Drupal\file\FileInterface;
use Drupal\multiversion\Entity\Workspace;
use Drupal\Tests\BrowserTestBase;

/**
 * Test the use of the ProcessFileAttachment service.
 *
 * @group replication
 */
class ProcessFileAttachmentTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  public static $modules = ['user', 'serialization', 'key_value', 'multiversion', 'replication', 'file'];

  /**
   * Test ProcessFileAttachment.
   */
  public function testProcessFileAttachment() {
    $live = Workspace::load(1);
    $stage = Workspace::create(['type' => 'basic', 'machine_name' => 'stage', 'label' => 'Stage']);
    $stage->save();

    $data = [
      'data' => 'aGVsbG8gd29ybGQK',
      'uri' => 'public://file1.txt',
      'uuid' => '6f9e1f07-e713-4840-bf95-8326c8317800',
    ];
    /** @var FileInterface $file1 */
    $file1 = \Drupal::service('replication.process_file_attachment')->process($data, 'base64_stream');
    $file1->save();
    $this->assertEquals('6f9e1f07-e713-4840-bf95-8326c8317800', $file1->uuid(), 'The file has the expected UUID.');
    $this->assertEquals($live->id(), $file1->get('workspace')->entity->id(), 'Expected workspace');

    /** @var FileInterface $file2 */
    $file2 = \Drupal::service('replication.process_file_attachment')->process($data, 'base64_stream');
    $file2->save();
    $this->assertEquals('6f9e1f07-e713-4840-bf95-8326c8317800', $file2->uuid(), 'The file has the expected UUID.');
    $this->assertEquals($live->id(), $file2->get('workspace')->entity->id(), 'Expected workspace');

    $this->assertEquals($file1->id(), $file2->id(), 'The two files have the same id.');

    file_unmanaged_delete($file2->getFileUri());
    $this->assertFalse(is_file($file2->getFileUri()));
    $this->assertFalse(is_file($file1->getFileUri()));

    /** @var FileInterface $file3 */
    $file3 = \Drupal::service('replication.process_file_attachment')->process($data, 'base64_stream');
    $file3->save();
    $this->assertEquals('6f9e1f07-e713-4840-bf95-8326c8317800', $file3->uuid(), 'The file has the expected UUID.');
    $this->assertEquals($live->id(), $file3->get('workspace')->entity->id(), 'Expected workspace');

    $this->assertEquals($file1->id(), $file3->id(), 'The two files have the same id.');
    $this->assertEquals($file2->id(), $file3->id(), 'The two files have the same id.');
    $this->assertTrue(is_file($file3->getFileUri()));

    /** @var FileInterface $file4 */
    $file4 = \Drupal::service('replication.process_file_attachment')->process($data, 'base64_stream', $stage);
    $file4->save();
    $this->assertEquals('6f9e1f07-e713-4840-bf95-8326c8317800', $file4->uuid(), 'The file has the expected UUID.');
    $this->assertEquals($stage->id(), $file4->get('workspace')->entity->id(), 'Expected workspace');

    $this->assertNotEquals($file1->id(), $file4->id(), 'The files do not have the same id');
  }
}