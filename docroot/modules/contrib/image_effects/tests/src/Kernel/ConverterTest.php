<?php

declare(strict_types=1);

namespace Drupal\Tests\image_effects\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\image\Entity\ImageStyle;

/**
 * Converter test.
 *
 * @group image_effects
 */
class ConverterTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'image',
    'image_effects',
  ];

  /**
   * Test image style.
   */
  protected ImageStyle $testImageStyle;

  /**
   * Test image style name.
   */
  protected string $testImageStyleName = 'image_effects_test';

  /**
   * Test image style label.
   */
  protected string $testImageStyleLabel = 'Image Effects Test';

  /**
   * Tests conversion of core 'rotate' image effects.
   */
  public function testConvertRotate(): void {
    $this->installConfig(['image', 'image_effects']);

    // Create a test ImageStyle.
    $this->testImageStyle = ImageStyle::create([
      'name' => $this->testImageStyleName,
      'label' => $this->testImageStyleLabel,
    ]);

    // Add a core rotate effect.
    $this->testImageStyle->addImageEffect([
      'id' => 'image_rotate',
      'weight' => 10,
      'data' => [
        'degrees' => 15,
        'bgcolor' => '#FF00FF',
        'random' => FALSE,
      ],
    ]);
    $this->testImageStyle->save();

    // Convert the core rotate effect to Image Effects.
    $this->assertTrue(\Drupal::service('image_effects.converter')->coreRotate2ie($this->testImageStyle));

    // Check the original core effect is no longer in the style.
    $query = \Drupal::service('entity_type.manager')->getStorage('image_style')->getQuery();
    $query->condition('name', $this->testImageStyleName);
    $query->condition('effects.*.id', 'image_rotate');
    $this->assertSame(0, $query->count()->execute());

    // Check the Image Effects effect is in the style.
    $query = \Drupal::service('entity_type.manager')->getStorage('image_style')->getQuery();
    $query->condition('name', $this->testImageStyleName);
    $query->condition('effects.*.id', 'image_effects_rotate');
    $this->assertSame(1, $query->count()->execute());

    // Check the data in the converted effect.
    $effect_ids = array_values($this->testImageStyle->getEffects()->getInstanceIds());
    $effect = $this->testImageStyle->getEffect($effect_ids[0]);
    $this->assertEquals([
      'degrees' => 15,
      'background_color' => '#FF00FFFF',
      'fallback_transparency_color' => '#FFFFFF',
      'method' => 'exact',
    ], $effect->getConfiguration()['data']);

    // Revert the conversion.
    $this->assertTrue(\Drupal::service('image_effects.converter')->ieRotate2core($this->testImageStyle));

    // Check the data in the reverted conversion.
    $effect_ids = array_values($this->testImageStyle->getEffects()->getInstanceIds());
    $effect = $this->testImageStyle->getEffect($effect_ids[0]);
    $this->assertEquals([
      'degrees' => 15,
      'bgcolor' => '#FF00FF',
      'random' => FALSE,
    ], $effect->getConfiguration()['data']);

    // Delete the effect.
    $this->testImageStyle->deleteImageEffect($effect);

    // Add a core rotate effect, with random rotation and transparent background
    // color.
    $this->testImageStyle->addImageEffect([
      'id' => 'image_rotate',
      'weight' => 10,
      'data' => [
        'degrees' => 20,
        'bgcolor' => NULL,
        'random' => TRUE,
      ],
    ]);
    $this->testImageStyle->save();

    // Convert the core rotate effect to Image Effects.
    $this->assertTrue(\Drupal::service('image_effects.converter')->coreRotate2ie($this->testImageStyle));

    // Check the data in the converted effect.
    $effect_ids = array_values($this->testImageStyle->getEffects()->getInstanceIds());
    $effect = $this->testImageStyle->getEffect($effect_ids[0]);
    $this->assertEquals([
      'degrees' => 20,
      'background_color' => NULL,
      'fallback_transparency_color' => '#FFFFFF',
      'method' => 'random',
    ], $effect->getConfiguration()['data']);

    // Revert the conversion.
    $this->assertTrue(\Drupal::service('image_effects.converter')->ieRotate2core($this->testImageStyle));

    // Check the data in the reverted conversion.
    $effect_ids = array_values($this->testImageStyle->getEffects()->getInstanceIds());
    $effect = $this->testImageStyle->getEffect($effect_ids[0]);
    $this->assertEquals([
      'degrees' => 20,
      'bgcolor' => NULL,
      'random' => TRUE,
    ], $effect->getConfiguration()['data']);

    // Check failure of a conversion when effects have been converted already.
    $this->assertFalse(\Drupal::service('image_effects.converter')->ieRotate2core($this->testImageStyle));
  }

}
