<?php

namespace Drupal\Tests\replication\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\multiversion\Entity\Workspace;
use Drupal\replication\AllDocs\AllDocsInterface;

/**
 * Tests the AllDocsFactory
 *
 * @group replication
 */
class AllDocsFactoryTest extends KernelTestBase {

  public static $modules = [
    'node',
    'serialization',
    'system',
    'user',
    'key_value',
    'multiversion',
    'replication',
  ];

  /** @var  Workspace */
  protected $workspace;

  protected function setUp() {
    parent::setUp();
    $this->installEntitySchema('user');
    $this->installEntitySchema('node');
    $this->installEntitySchema('replication_log');
    $this->installEntitySchema('workspace');
    $this->installSchema('key_value', ['key_value_sorted']);
    $this->installConfig(['multiversion']);
    \Drupal::service('multiversion.manager')->enableEntityTypes();

    $this->workspace = Workspace::create(['machine_name' => 'default', 'type' => 'basic']);
    $this->workspace->save();
  }

  public function testAllDocsFactory() {
    $all_docs = \Drupal::service('replication.alldocs_factory')->get($this->workspace);
    $this->assertTrue(($all_docs instanceof AllDocsInterface));
  }

}
