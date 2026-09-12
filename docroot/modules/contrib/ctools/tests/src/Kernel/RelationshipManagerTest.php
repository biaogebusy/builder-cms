<?php

namespace Drupal\Tests\ctools\Kernel;

use Drupal\Core\Plugin\Context\Context;
use Drupal\Core\Plugin\Context\EntityContextDefinition;

/**
 * @coversDefaultClass \Drupal\ctools\Plugin\RelationshipManager
 * @group CTools
 */
class RelationshipManagerTest extends RelationshipsTestBase {

  /**
   * @covers ::getDefinitions
   */
  public function testRelationshipConstraints() {
    $definitions = $this->relationshipManager->getDefinitions();
    // The Bundle constraint must be stored in whichever format the running
    // core reads back: Drupal 11.4+ expects ['bundle' => [...]] while Drupal 10
    // expects the bare list. Assert via getBundles() rather than against a
    // literal constraint array so this holds on both.
    $context_definition = $definitions['typed_data_relationship:entity:node:body']['context_definitions']['base'];
    $this->assertSame(['Bundle'], array_keys($context_definition->getConstraints()));
    $bundles = $context_definition->getDataDefinition()->getBundles();
    $this->assertSame(['page', 'foo'], array_values($bundles));

    // Check that typed data primitive labels are formatted properly.
    $this->assertSame('Body from Page and Foo', (string) $definitions['typed_data_relationship:entity:node:body']['label']);

    // Check that entity relationship labels are formatted properly.
    $this->assertSame('Authored by Entity from Content', (string) $definitions['typed_data_entity_relationship:entity:node:uid']['label']);

    // Check that language relationship labels are formatted properly.
    $this->assertSame('Language Language from Content', (string) $definitions['typed_data_language_relationship:entity:node:langcode']['label']);
  }

  /**
   * @covers ::getDefinitionsForContexts
   */
  public function testRelationshipPluginAvailability() {
    $context_definition = new EntityContextDefinition('entity:node');
    $contexts = [
      'node' => new Context($context_definition, $this->entities['node1']),
    ];
    $definitions = $this->relationshipManager->getDefinitionsForContexts($contexts);

    $context_definition = new EntityContextDefinition('entity:node');
    $contexts = [
      'node' => new Context($context_definition, $this->entities['node2']),
    ];
    $definitions = $this->relationshipManager->getDefinitionsForContexts($contexts);
    $this->assertArrayNotHasKey('typed_data_relationship:entity:node:body', $definitions);

    $context_definition = new EntityContextDefinition('entity:node');
    $contexts = [
      'node' => new Context($context_definition, $this->entities['node3']),
    ];
    $definitions = $this->relationshipManager->getDefinitionsForContexts($contexts);

  }

}
