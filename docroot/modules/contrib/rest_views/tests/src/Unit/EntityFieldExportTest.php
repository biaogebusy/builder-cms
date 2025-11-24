<?php

namespace Drupal\Tests\rest_views\Unit;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityRepositoryInterface;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\Field\FieldTypePluginManagerInterface;
use Drupal\Core\Field\FormatterPluginManager;
use Drupal\Core\Form\FormState;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Render\RendererInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\StringTranslation\TranslationInterface;
use Drupal\rest_views\Normalizer\DataNormalizer;
use Drupal\rest_views\Normalizer\RenderNormalizer;
use Drupal\rest_views\Plugin\views\field\EntityFieldExport;
use Drupal\rest_views\SerializedData;
use Drupal\serialization\Encoder\JsonEncoder;
use Drupal\Tests\UnitTestCase;
use Drupal\views\Plugin\views\display\DisplayPluginBase;
use Drupal\views\ViewExecutable;
use Symfony\Component\Serializer\Serializer;
use Symfony\Component\Serializer\SerializerInterface;

/**
 * Test the EntityFieldExport views field plugin.
 *
 * @group rest_views
 */
class EntityFieldExportTest extends UnitTestCase {

  /**
   * The EntityFieldExport plugin.
   *
   * @var \Drupal\rest_views\Plugin\views\field\EntityFieldExport
   */
  private EntityFieldExport $handler;

  /**
   * The mocked serializer service.
   *
   * @var \Symfony\Component\Serializer\SerializerInterface
   */
  private SerializerInterface $serializer;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    // Create our field handler, mocking the required services.
    $entityTypeManager = $this->createMock(EntityTypeManagerInterface::class);
    $entityFieldManager = $this->createMock(EntityFieldManagerInterface::class);
    $entityRepository = $this->createMock(EntityRepositoryInterface::class);
    $formatterPluginManager = $this->createMock(FormatterPluginManager::class);
    $fieldTypePluginManager = $this->createMock(FieldTypePluginManagerInterface::class);
    $languageManager = $this->createMock(LanguageManagerInterface::class);
    $entityTypeBundleInfo = $this->createMock(EntityTypeBundleInfoInterface::class);
    /** @var \Drupal\Core\Render\RendererInterface $renderer */
    $renderer = $this->createMock(RendererInterface::class);
    // For the t() function to work, mock the translation service.
    $container = new ContainerBuilder();
    $translation = $this->createMock(TranslationInterface::class);
    $translation
      ->method('translateString')
      ->willReturnCallback(static function (TranslatableMarkup $string) {
        return $string->getUntranslatedString();
      });
    $container->set('string_translation', $translation);
    \Drupal::setContainer($container);

    /** @var \Drupal\rest_views\Plugin\views\field\EntityFieldExport|\PHPUnit\Framework\MockObject\MockObject $handler */
    $handler = $this->getMockBuilder(EntityFieldExport::class)
      ->setConstructorArgs([
        [],
        NULL,
        [
          'entity_type' => 'node',
          'field_name'  => 'title',
        ],
        $entityTypeManager,
        $formatterPluginManager,
        $fieldTypePluginManager,
        $languageManager,
        $renderer,
        $entityRepository,
        $entityFieldManager,
        $entityTypeBundleInfo,
      ])
      ->onlyMethods(['getFieldDefinition'])
      ->getMock();
    $this->handler = $handler;

    // Mock the field definition.
    $fieldDefinition = $this->createMock(BaseFieldDefinition::class);
    $fieldDefinition
      ->method('getFieldStorageDefinition')
      ->willReturn($fieldDefinition);
    $fieldDefinition
      ->method('getColumns')
      ->willReturn([]);

    // The handler accesses it through itself, and through the entity manager.
    $this->handler
      ->method('getFieldDefinition')
      ->willReturn($fieldDefinition);
    $entityFieldManager
      ->method('getFieldStorageDefinitions')
      ->with('node')
      ->willReturn(['title' => $fieldDefinition]);

    // Initialize the handler, using a mocked view and display plugin.
    /** @var \Drupal\views\ViewExecutable $view */
    $view = $this->createMock(ViewExecutable::class);
    $view->display_handler = $this->createMock(DisplayPluginBase::class);

    $this->handler->init($view, $view->display_handler);

    $this->serializer = new Serializer([
      new DataNormalizer(),
      new RenderNormalizer($renderer),
    ], [
      new JsonEncoder(),
    ]);
  }

  /**
   * Check that the field does not use multi-type and separator options.
   */
  public function testSettings(): void {
    $options = $this->handler->defineOptions();
    $this->assertArrayNotHasKey('multi_type', $options);
    $this->assertArrayNotHasKey('separator', $options);
    $form = [];
    $this->handler->multiple_options_form($form, new FormState());
    $this->assertArrayNotHasKey('multi_type', $form);
    $this->assertArrayNotHasKey('separator', $form);
  }

  /**
   * Check that the handler correctly preserves serializable items.
   *
   * @param array $items
   *   Item data to be rendered.
   * @param array $expected
   *   Expected output.
   *
   * @dataProvider providerItems
   *
   * @throws \Exception
   */
  public function testRenderItems(array $items, array $expected): void {
    $this->handler->multiple = FALSE;
    $result = $this->handler->renderItems($items);
    $json = $this->serializer->serialize($result, 'json');
    $expected_json = $this->serializer->serialize($expected[0], 'json');
    $this->assertEquals($expected_json, $json);
    $this->handler->multiple = TRUE;
    $result = $this->handler->renderItems($items);
    $json = $this->serializer->serialize($result, 'json');
    $expected_json = $this->serializer->serialize($expected, 'json');
    $this->assertEquals($expected_json, $json);
  }

  /**
   * Data provider for ::testRenderItems().
   *
   * @return array
   *   Test case data.
   */
  public static function providerItems(): array {
    $data[] = [
      'items' => ['Lorem', 'ipsum', 'dolor', 'sit', 'amet'],
      'expected' => ['Lorem', 'ipsum', 'dolor', 'sit', 'amet'],
    ];
    $data[] = [
      'items' => [
        new SerializedData(['lorem' => 'ipsum']),
        new SerializedData(['dolor' => TRUE]),
        new SerializedData(['amet' => 42]),
      ],
      'expected' => [
        ['lorem' => 'ipsum'],
        ['dolor' => TRUE],
        ['amet' => 42],
      ],
    ];
    return $data;
  }

}
