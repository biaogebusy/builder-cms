<?php

declare(strict_types=1);

namespace Drupal\Tests\crop\Functional;

use Drupal\Component\Serialization\Yaml;
use Drupal\Core\DefaultContent\Existing;
use Drupal\Core\DefaultContent\Finder;
use Drupal\Core\DefaultContent\Importer;
use Drupal\Core\DefaultContent\PreEntityImportEvent;
use Drupal\Core\DefaultContent\PreExportEvent;
use Drupal\crop\Entity\Crop;
use Drupal\crop\Entity\CropType;
use Drupal\crop\EventSubscriber\DefaultContentSubscriber;
use Drupal\file\Entity\File;
use Drupal\FunctionalTests\Core\Recipe\RecipeTestTrait;
use Drupal\Tests\BrowserTestBase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests exporting and importing crops as default content.
 */
#[Group('crop')]
#[CoversClass(DefaultContentSubscriber::class)]
#[RunTestsInSeparateProcesses]
final class ContentExportTest extends BrowserTestBase {

  use RecipeTestTrait;

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['crop', 'file'];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    // The event subscriber is only registered if the relevant events exist.
    if (!class_exists(PreExportEvent::class) || !class_exists(PreEntityImportEvent::class)) {
      $this->markTestSkipped('This test requires Drupal 11.3 or later.');
    }
  }

  /**
   * Tests exporting and importing an image file and its associated crop.
   */
  public function testExportAndImportImageCrop(): void {
    $image_uri = $this->getRandomGenerator()->image(
      uniqid('public://') . '.png',
      '100x100',
      '100x100',
    );
    $this->assertFileExists($image_uri);
    $file = File::create(['uri' => $image_uri]);
    $file->save();
    $file_id = $file->id();

    CropType::create(['id' => 'test', 'label' => 'Test'])->save();
    $crop = Crop::create([
      'type' => 'test',
      'entity_type' => 'file',
      'entity_id' => $file_id,
      'uri' => $file->getFileUri(),
      'height' => 10,
      'width' => 10,
      'x' => 0,
      'y' => 0,
    ]);
    $this->assertCount(0, $crop->validate());
    $crop->save();

    $process = $this->runDrupalCommand([
      'content:export',
      'crop',
      $crop->id(),
      '--with-dependencies',
      '--dir=public://content',
    ]);
    $this->assertSame(0, $process->getExitCode());

    $uri = 'public://content/crop/' . $crop->uuid() . '.yml';
    $this->assertFileExists($uri);
    $data = file_get_contents($uri);
    $data = Yaml::decode($data);
    $this->assertIsArray($data);

    $file_uuid = $file->uuid();
    // The file should have been marked as a dependency, and exported as well.
    $this->assertSame('file', $data['_meta']['depends'][$file_uuid]);
    $this->assertFileExists("public://content/file/$file_uuid.yml");
    // The file's ID should have been replaced with its UUID so that the
    // association can be restored upon import.
    $this->assertSame($file_uuid, $data['default']['entity_id'][0]['uuid']);
    $this->assertArrayNotHasKey('value', $data['default']['entity_id'][0]);

    // Delete the crop so we can re-import it and confirm that the file
    // association is correctly restored.
    $crop->delete();
    $finder = new Finder('public://content');
    $this->container->get(Importer::class)
      ->importContent($finder, Existing::Skip);
    $crop = Crop::load(2);
    $this->assertInstanceOf(Crop::class, $crop);
    $this->assertSame($file_id, $crop->entity_id->value);
  }

}
