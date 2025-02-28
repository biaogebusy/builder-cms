<?php

namespace Drupal\Tests\replication\Kernel;

use Drupal\Core\Entity\EntityStorageException;
use Drupal\KernelTests\KernelTestBase;
use Drupal\replication\Entity\ReplicationLog;

/**
 * Test the creation and operation of the Replication Log entity.
 *
 * @group replication
 */
class ReplicationLogTest extends KernelTestBase {

  public static $modules = ['user', 'serialization', 'key_value', 'multiversion', 'replication'];

  public function testOperations() {
    $this->installEntitySchema('replication_log');
    $entityTypeManager = $this->container->get('entity_type.manager');
    $entity = $entityTypeManager->getStorage('replication_log')->create();
    $this->assertTrue($entity instanceof ReplicationLog, 'Replication Log entity was created.');

    // Set required fields.
    /** @var ReplicationLog $entity */
    $entity = $entityTypeManager->getStorage('replication_log')->create();
    $seq_id = \Drupal::service('multiversion.manager')->newSequenceId();
    $entity->source_last_seq->value = $seq_id;
    $entity->history->recorded_seq = $seq_id;
    $entity->history->session_id = \Drupal::service('uuid')->generate();
    $entity->save();

    $entity_id = $entity->id();
    $this->assertTrue(!empty($entity_id), 'Entity was saved.');

    $max_int = 2147483647;
    $max_bigint = 9223372036854775807;
    $entity = $entityTypeManager->getStorage('replication_log')->create();
    $entity->history->start_last_seq = $max_int;
    $entity->history->missing_found = $max_int;
    $entity->history->docs_read = $max_int;
    $entity->history->end_last_seq = $max_int;
    $entity->history->missing_checked = $max_int;
    $entity->history->docs_written = $max_int;
    $entity->history->doc_write_failures = $max_int;
    $entity->history->recorded_seq = $max_bigint;
    $entity->history->start_last_seq = $max_int;
    $entity->history->end_time = date(DATE_RFC2822);
    $entity->history->start_time = date(DATE_RFC2822);
    $entity->history->session_id = \Drupal::service('uuid')->generate();

    try {
      $saved = (bool) $entity->save();
      $this->assertTrue($saved, 'Entity was saved.');
    }
    catch(EntityStorageException $e) {
      $this->fail('Fail, trying to save entity with incorrect data format or length for history fields.');
    }
  }

}
