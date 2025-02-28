<?php

namespace Drupal\Tests\panelizer\Kernel;

use Drupal\Core\Entity\Query\QueryInterface;
use Drupal\Core\Entity\Query\Sql\QueryFactory;
use Drupal\KernelTests\KernelTestBase;
use Drupal\layout_builder\Entity\LayoutEntityDisplayInterface;
use Drupal\layout_builder\Section;
use Drupal\panelizer\LayoutBuilderMigration;
use Drupal\panels\Plugin\DisplayVariant\PanelsDisplayVariant;
use Drupal\Tests\node\Traits\ContentTypeCreationTrait;

/**
 * @coversDefaultClass \Drupal\panelizer\LayoutBuilderMigration
 *
 * @group panelizer
 */
class LayoutBuilderMigrationTest extends KernelTestBase {

  use ContentTypeCreationTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'core_context_test',
    'ctools',
    'ctools_block',
    'field',
    'field_ui',
    'layout_builder',
    'layout_discovery',
    'node',
    'panelizer',
    'panels',
    'system',
    'text',
    'user',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installConfig('node');
    $this->createContentType(['type' => 'page']);

    /** @var \Drupal\panelizer\PanelizerInterface $panelizer */
    $panelizer = $this->container->get('panelizer');

    $panelizer->setPanelizerSettings('node', 'page', 'default', [
      'enable' => TRUE,
      'allow' => TRUE,
      'custom' => TRUE,
      'default' => 'default',
    ]);
    $panelizer->setDisplayStaticContexts('default', 'node', 'page', 'default', [
      'value' => [
        'type' => 'integer',
        'label' => 'Lucky number',
        'description' => "Today's winning lottery number",
        'value' => 42,
      ],
      'letter' => [
        'type' => 'string',
        'label' => 'Word of the day',
        'description' => 'The word of the day, from the NATO phonetic alphabet',
        'value' => 'Foxtrot',
      ],
    ]);

    $default = $panelizer->getDefaultPanelsDisplay('default', 'node', 'page', 'default');
    $default->addBlock([
      'id' => 'context_block_optional',
      'region' => 'content',
      'weight' => 0,
    ]);
    $panelizer->setDefaultPanelsDisplay('default', 'node', 'page', 'default', $default);
  }

  /**
   * {@inheritdoc}
   */
  public function testBuildBatch() {
    $display = $this->prophesize(LayoutEntityDisplayInterface::class);

    $display->id()->willReturn('test');
    $display->getTargetEntityTypeId()->willReturn('node');
    $display->getTargetBundle()->willReturn('page');

    $entity_type = $this->container->get('entity_type.manager')
      ->getDefinition('node');

    // Mock a query that will return particular results.
    $results = [
      1 => 1,
      2 => 1,
      3 => 2,
      4 => 2,
      5 => 2,
    ];
    $query = $this->prophesize(QueryInterface::class);
    $the_query = $query->reveal();
    $query->exists('panelizer')->willReturn($the_query);
    $query->accessCheck(TRUE)->willReturn($the_query);
    $query->condition('panelizer.view_mode', 'full')->willReturn($the_query);
    $query->condition('type', 'page')->shouldBeCalled();
    $query->allRevisions()->shouldBeCalled();
    $query->execute()->willReturn($results);

    $query_factory = $this->prophesize()->willExtend(QueryFactory::class);
    $query_factory->get($entity_type, 'AND')->willReturn($query->reveal());
    $this->container->set('entity.query.sql', $query_factory->reveal());

    $batch = LayoutBuilderMigration::fromDisplay($display->reveal())->toArray();
    $operations = array_values(array_slice($batch['operations'], 1));
    $this->assertCount(5, $operations);

    foreach (array_keys($results) as $i => $revision_id) {
      $arguments = $operations[$i][1];
      $this->assertSame($revision_id, $arguments[1]);
    }
  }

  /**
   * @covers ::toSection
   */
  public function testToSection() {
    /** @var \Drupal\panels\Plugin\DisplayVariant\PanelsDisplayVariant $display */
    $display = $this->container->get('panelizer')
      ->getDefaultPanelsDisplay('default', 'node', 'page', 'default');

    $this->assertInstanceOf(PanelsDisplayVariant::class, $display);

    $configuration = $display->getConfiguration();
    $this->assertNotEmpty($configuration['blocks']);
    // Set the layout plugin ID to an outdated one to ensure it is updated.
    $configuration['layout'] = 'twocol';

    // Normally this would not be kosher, but in this case the method really
    // does deserve private static visibility and should be tested by using
    // reflection to pry it open. It is too important to the migration to NOT
    // have a dedicated test.
    $migration = LayoutBuilderMigration::create($this->container);
    $method = new \ReflectionMethod($migration, 'toSection');
    $method->setAccessible(TRUE);

    /** @var \Drupal\layout_builder\Section $section */
    $section = $method->invokeArgs($migration, [&$configuration, 'node', 'page']);
    $this->assertInstanceOf(Section::class, $section);
    $this->assertSame('layout_twocol', $section->getLayoutId());
    $this->assertSame('layout_twocol', $configuration['layout']);
    $this->assertSame($display->getLayout()->getConfiguration(), $section->getLayoutSettings());

    foreach ($configuration['blocks'] as $uuid => $block) {
      $component = $section->getComponent($uuid);
      $this->assertSame($uuid, $component->getUuid());
      $this->assertSame($block['region'], $component->getRegion());
      $this->assertSame($block['weight'], $component->getWeight());

      if (strpos($block['id'], 'entity_field:') === 0) {
        list(, , $field_name) = explode(':', $block['id']);
        $this->assertSame("field_block:node:page:$field_name", $component->getPluginId());

        // If the 'entity' context is mapped to Panelizer's entity context,
        // assert that mapping has been deleted, since it's not necessary (and
        // in fact causes errors) with Layout Builder.
        if (isset($block['context_mapping']['entity']) && $block['context_mapping']['entity'] === '@panelizer.entity_context:entity') {
          $component_configuration = $component->get('configuration');
          $this->assertSame('layout_builder.entity', $component_configuration['context_mapping']['entity']);
        }
      }
      else {
        $this->assertSame($block['id'], $component->getPluginId());
      }

      if ($block['id'] === 'context_block') {
        $plugin = $component->getPlugin();
        $this->assertSame('42', $plugin->getContextValue('value'));
        $this->assertSame('Foxtrot', $plugin->getContextValue('letter'));
      }
    }
  }

}
