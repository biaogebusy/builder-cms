<?php

namespace Drupal\Tests\core_context\Functional;

use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\layout_builder\Plugin\SectionStorage\OverridesSectionStorage;
use Drupal\layout_builder\Section;
use Drupal\layout_builder\SectionComponent;
use Drupal\Tests\BrowserTestBase;

/**
 * @group core_context
 */
class LayoutBuilderIntegrationTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'core_context',
    'core_context_test',
    'field_ui',
    'layout_builder',
    'node',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->drupalCreateContentType(['type' => 'page']);
    $this->drupalPlaceBlock('local_tasks_block');

    $storage = FieldStorageConfig::create([
      'entity_type' => 'node',
      'field_name' => 'context',
      'type' => 'context',
      'cardinality' => FieldStorageDefinitionInterface::CARDINALITY_UNLIMITED,
    ]);
    $storage->save();

    FieldConfig::create([
      'field_storage' => $storage,
      'bundle' => 'page',
    ])->save();
  }

  /**
   * Data provider for ::test().
   *
   * @return array
   *   The sets of arguments to pass to ::test().
   */
  public function provider() {
    $scenarios = [
      'context values in third-party entity display settings' => [
        'block_configuration' => [
          'id' => 'context_block',
          'context_mapping' => [
            'value' => '@core_context:value',
            'letter' => '@core_context:letter',
          ],
        ],
        'layout_overridable' => FALSE,
        'third_party_contexts' => [
          'value' => [
            'type' => 'integer',
            'label' => 'Holy computer number',
            'description' => 'A convenient power of two',
            'value' => 512,
          ],
          'letter' => [
            'type' => 'string',
            'label' => 'Sponsoring letter',
            'description' => 'A letter of the NATO phonetic alphabet',
            'value' => 'Charlie',
          ],
        ],
        'entity_values' => [],
      ],
      'context values in third-party entity display settings, without mapping' => [
        'block_configuration' => [
          'id' => 'context_block_optional',
        ],
        'layout_overridable' => FALSE,
        'third_party_contexts' => [
          'value' => [
            'type' => 'integer',
            'label' => 'Holy computer number',
            'description' => 'A convenient power of two',
            'value' => 512,
          ],
          'letter' => [
            'type' => 'string',
            'label' => 'Sponsoring letter',
            'description' => 'A letter of the NATO phonetic alphabet',
            'value' => 'Charlie',
          ],
        ],
        'entity_values' => [],
      ],
      'context values in entity field' => [
        'block_configuration' => [
          'id' => 'context_block',
          'context_mapping' => [
            'value' => '@core_context:value',
            'letter' => '@core_context:letter',
          ],
        ],
        'layout_overridable' => TRUE,
        'third_party_contexts' => [],
        'entity_values' => [
          'context' => [
            [
              'id' => 'value',
              'type' => 'integer',
              'label' => 'Holy computer number',
              'description' => 'A convenient power of two',
              'value' => 512,
            ],
            [
              'id' => 'letter',
              'type' => 'string',
              'label' => 'Sponsoring letter',
              'description' => 'A letter of the NATO phonetic alphabet',
              'value' => 'Charlie',
            ],
          ],
        ],
      ],
      'context values in entity field, without mapping' => [
        'block_configuration' => [
          'id' => 'context_block_optional',
        ],
        'layout_overridable' => TRUE,
        'third_party_contexts' => [],
        'entity_values' => [
          'context' => [
            [
              'id' => 'value',
              'type' => 'integer',
              'label' => 'Holy computer number',
              'description' => 'A convenient power of two',
              'value' => 512,
            ],
            [
              'id' => 'letter',
              'type' => 'string',
              'label' => 'Sponsoring letter',
              'description' => 'A letter of the NATO phonetic alphabet',
              'value' => 'Charlie',
            ],
          ],
        ],
      ],
      'context values in entity field and third-party entity display settings, without mapping' => [
        'block_configuration' => [
          'id' => 'context_block_optional',
        ],
        'layout_overridable' => TRUE,
        'third_party_contexts' => [
          'value' => [
            'type' => 'integer',
            'label' => 'Holy computer number',
            'description' => 'A convenient power of two',
            'value' => 512,
          ],
        ],
        'entity_values' => [
          'context' => [
            [
              'id' => 'letter',
              'type' => 'string',
              'label' => 'Sponsoring letter',
              'description' => 'A letter of the NATO phonetic alphabet',
              'value' => 'Charlie',
            ],
          ],
        ],
      ],
    ];
    // If \Drupal\Component\Plugin\ContextAwarePluginBase exists, test passing
    // context values in plugin configuration. Support was this was dropped in
    // Drupal 10, so we can remove this entire block when Drupal 9 is no longer
    // supported.
    // @see https://www.drupal.org/node/3120980
    if (class_exists('\Drupal\Component\Plugin\ContextAwarePluginBase')) {
      $scenarios['context values in block plugin configuration'] = [
        'block_configuration' => [
          'id' => 'context_block',
          'context' => [
            'value' => 512,
            'letter' => 'Charlie',
          ],
        ],
        'layout_overridable' => FALSE,
        'third_party_contexts' => [],
        'entity_values' => [],
      ];
    }
    return $scenarios;
  }

  /**
   * Tests that context values are displayed by Layout Builder.
   *
   * @param array $block_configuration
   *   Configuration for the context block (section component).
   * @param bool $layout_overridable
   *   (optional) Whether the layout will be overridable per entity. Defaults to
   *   FALSE.
   * @param array $third_party_contexts
   *   (optional) Any contexts to store in third-party settings of the entity
   *   view display.
   * @param array $entity_values
   *   (optional) Any field values to set on the entity.
   *
   * @dataProvider provider
   */
  public function test(array $block_configuration, $layout_overridable = FALSE, $third_party_contexts = [], array $entity_values = []) {
    $page = $this->getSession()->getPage();

    $component = SectionComponent::fromArray([
      'uuid' => $this->container->get('uuid')->generate(),
      'region' => 'content',
      'configuration' => $block_configuration,
      'additional' => [],
      'weight' => 0,
    ]);

    $section = new Section('layout_onecol');
    $section->appendComponent($component);

    /** @var \Drupal\layout_builder\Entity\LayoutEntityDisplayInterface $display */
    $display = $this->container->get('entity_display.repository')
      ->getViewDisplay('node', 'page', 'full');
    $display->enableLayoutBuilder()
      ->setOverridable($layout_overridable)
      ->appendSection($section)
      ->setThirdPartySetting('core_context', 'contexts', $third_party_contexts)
      ->save();

    $account = $this->drupalCreateUser([
      'administer node display',
      'configure any layout',
      'edit own page content',
    ]);
    $this->drupalLogin($account);

    $entity_values += [
      'type' => 'page',
    ];
    $node = $this->drupalCreateNode($entity_values);

    if ($layout_overridable) {
      /** @var \Drupal\layout_builder\Field\LayoutSectionItemList $section_list */
      $section_list = $node->get(OverridesSectionStorage::FIELD_NAME);
      $section_list->appendSection($section);
      $node->save();
    }
    $this->drupalGet($node->toUrl());

    $assert_session = $this->assertSession();
    $assert_session->statusCodeEquals(200);
    $assert_session->pageTextContains('The context value is 512, brought to you by the letter Charlie.');

    // If the layout is customizable per entity, ensure we can visit the Layout
    // page without errors.
    if ($layout_overridable) {
      $page->clickLink('Layout');
      $assert_session->statusCodeEquals(200);
    }

    // Ensure that we can edit the default layout without errors, but only if
    // there are contexts stored in the entity display.
    if ($third_party_contexts || $block_configuration['id'] === 'context_block_optional') {
      $this->drupalGet('/admin/structure/types/manage/page/display/full');
      $page->clickLink('Manage layout');
      $assert_session->statusCodeEquals(200);
    }
  }

  /**
   * Tests integration with Layout Builder for non-bundleable entity types.
   */
  public function testNonBundleableEntityType() {
    $this->container->get('entity_display.repository')
      ->getViewDisplay('user', 'user')
      ->enableLayoutBuilder()
      ->save();

    $account = $this->drupalCreateUser([
      'administer user display',
      'configure any layout',
    ]);
    $this->drupalLogin($account);

    $this->drupalGet('/admin/config/people/accounts/display/default/layout');
    $this->assertSession()->statusCodeEquals(200);
  }

}
