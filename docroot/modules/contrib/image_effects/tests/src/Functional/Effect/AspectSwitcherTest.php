<?php

declare(strict_types=1);

namespace Drupal\Tests\image_effects\Functional\Effect;

use Drupal\Core\Config\ConfigValueException;
use Drupal\Core\File\FileExists;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Tests\image_effects\Functional\ImageEffectsTestBase;
use Drupal\image\Entity\ImageStyle;

/**
 * AspectSwitcher effect test.
 *
 * @group image_effects
 */
class AspectSwitcherTest extends ImageEffectsTestBase {

  /**
   * Effects.
   *
   * Define 2 distinguishable effects that will be used to assert that the
   * correct image style (and therefore image effects) is being applied to
   * the image.
   */
  protected array $effects = [
    'landscape' => [
      'id' => 'image_resize',
      'data' => [
        'width' => 5,
        'height' => 5,
      ],
    ],
    'portrait' => [
      'id' => 'image_resize',
      'data' => [
        'width' => 10,
        'height' => 10,
      ],
    ],
  ];

  /**
   * {@inheritdoc}
   */
  public function setUp(): void {
    parent::setUp();

    // Create 2 test image styles, one for landscape and one for portrait and
    // add the specific effect to each.
    foreach (['landscape', 'portrait'] as $orientation) {
      $style_name = $orientation . '_image_style_test';
      $style_label = ucfirst($orientation) . ' Image Style Test';
      $style = ImageStyle::create(['name' => $style_name, 'label' => $style_label]);
      $style->addImageEffect($this->effects[$orientation]);
      $this->assertEquals(SAVED_NEW, $style->save());
    }

    $test_directory = 'public://styles/' . $this->testImageStyleName;
    $this->fileSystem->prepareDirectory($test_directory, FileSystemInterface::CREATE_DIRECTORY);
  }

  /**
   * AspectSwitcher effect test.
   *
   * @param string $toolkit_id
   *   The id of the toolkit to set up.
   * @param string $toolkit_config
   *   The config object of the toolkit to set up.
   * @param array $toolkit_settings
   *   The settings of the toolkit to set up.
   *
   * @dataProvider providerToolkits
   */
  public function testAspectSwitcherEffect(string $toolkit_id, string $toolkit_config, array $toolkit_settings): void {
    $this->changeToolkit($toolkit_id, $toolkit_config, $toolkit_settings);

    $image_factory = $this->container->get('image.factory');

    $test_landscape_file = 'core/tests/fixtures/files/image-test.png';
    $original_landscape_uri = $this->fileSystem->copy($test_landscape_file, 'public://', FileExists::Rename);

    $img_portrait = imagerotate(imagecreatefrompng($original_landscape_uri), 90, 0);
    $generated_uri = \Drupal::service('file_system')->realpath('public://image-test-portrait.png');
    imagepng($img_portrait, $generated_uri);
    $test_portrait_file = $generated_uri;
    $original_portrait_uri = $this->fileSystem->copy($test_portrait_file, 'public://', FileExists::Rename);

    // Add aspect switcher effect.
    $effect = [
      'id' => 'image_effects_aspect_switcher',
      'data' => [
        'landscape_image_style' => 'L (landscape_image_style_test)',
        'portrait_image_style' => 'L (portrait_image_style_test)',
        'ratio_adjustment' => 0.99,
      ],
    ];
    $uuid = $this->addEffectToTestStyle($effect);

    // Load Image Style.
    $image_style = ImageStyle::load($this->testImageStyleName);

    // Check that effect's configuration is as expected.
    $aspect_switcher_effect_configuration = $image_style->getEffect($uuid)->getConfiguration()['data'];
    $this->assertEquals('landscape_image_style_test', $aspect_switcher_effect_configuration['landscape_image_style']);
    $this->assertEquals('portrait_image_style_test', $aspect_switcher_effect_configuration['portrait_image_style']);
    $this->assertEquals(0.99, $aspect_switcher_effect_configuration['ratio_adjustment']);

    // Check that dependent image style have been added to configuration
    // dependencies.
    $expected_config_dependencies = [
      'image.style.landscape_image_style_test',
      'image.style.portrait_image_style_test',
    ];
    $this->assertEquals($expected_config_dependencies, $image_style->getDependencies()['config']);

    // Check that landscape image style is applied when source image is
    // landscape.
    // Check that ::transformDimensions returns expected dimensions.
    $original_landscape_image = $image_factory->get($original_landscape_uri);
    $derivative_landscape_url = $this->fileUrlGenerator->transformRelative($this->testImageStyle->buildUrl($original_landscape_uri));
    $variables = [
      '#theme' => 'image_style',
      '#style_name' => $this->testImageStyleName,
      '#uri' => $original_landscape_uri,
      '#width' => $original_landscape_image->getWidth(),
      '#height' => $original_landscape_image->getHeight(),
    ];
    $this->assertMatchesRegularExpression("/\<img src=\"" . preg_quote($derivative_landscape_url, '/') . "\" width=\"{$this->effects['landscape']['data']['width']}\" height=\"{$this->effects['landscape']['data']['height']}\" alt=\"\" .*\/\>/", $this->getImageTag($variables));
    // Check that ::applyEffect returns expected dimensions.
    $dest_uri = $image_style->buildUri($original_landscape_uri);
    $this->assertTrue($image_style->createDerivative($original_landscape_uri, $dest_uri));
    $image = $image_factory->get($dest_uri);
    $this->assertEquals($this->effects['landscape']['data']['width'], $image->getWidth());
    $this->assertEquals($this->effects['landscape']['data']['height'], $image->getHeight());

    // Check that portrait image style is applied when source image is
    // portrait.
    // Check that ::transformDimensions returns expected dimensions.
    $original_portrait_image = $image_factory->get($original_portrait_uri);
    $derivative_portrait_url = $this->fileUrlGenerator->transformRelative($this->testImageStyle->buildUrl($original_portrait_uri));
    $variables = [
      '#theme' => 'image_style',
      '#style_name' => $this->testImageStyleName,
      '#uri' => $original_portrait_uri,
      '#width' => $original_portrait_image->getWidth(),
      '#height' => $original_portrait_image->getHeight(),
    ];
    $this->assertMatchesRegularExpression("/\<img src=\"" . preg_quote($derivative_portrait_url, '/') . "\" width=\"{$this->effects['portrait']['data']['width']}\" height=\"{$this->effects['portrait']['data']['height']}\" alt=\"\" .*\/\>/", $this->getImageTag($variables));
    // Check that ::applyEffect returns expected dimensions.
    $dest_uri = $image_style->buildUri($original_portrait_uri);
    $image_style->createDerivative($original_portrait_uri, $dest_uri);
    $image = $image_factory->get($dest_uri);
    $this->assertEquals($this->effects['portrait']['data']['width'], $image->getWidth());
    $this->assertEquals($this->effects['portrait']['data']['height'], $image->getHeight());

    // Check that flushing a dependent style, the parent one gets flushed too,
    // and the invalidation of the parent image style cache tag is changed.
    $pre_flush_invalidations_parent = $this->getImageStyleCacheTagInvalidations($this->testImageStyleName);
    $pre_flush_invalidations_child = $this->getImageStyleCacheTagInvalidations('portrait_image_style_test');
    $this->assertNotEquals(0, count($this->fileSystem->scanDirectory('public://styles/' . $this->testImageStyleName, '/.*/')));
    $portrait_image_style = ImageStyle::load('portrait_image_style_test');
    $portrait_image_style->flush();
    $this->assertDirectoryDoesNotExist('public://styles/' . $this->testImageStyleName);
    $this->assertNotEquals($this->getImageStyleCacheTagInvalidations($this->testImageStyleName), $pre_flush_invalidations_parent);
    $this->assertNotEquals($this->getImageStyleCacheTagInvalidations('portrait_image_style_test'), $pre_flush_invalidations_child);

    // Test an aspect switcher effect with no portrait sub-style specified.
    $this->removeEffectFromTestStyle($uuid);
    // Add aspect switcher effect.
    $effect = [
      'id' => 'image_effects_aspect_switcher',
      'data' => [
        'landscape_image_style' => 'L (landscape_image_style_test)',
        'ratio_adjustment' => 1,
      ],
    ];
    $uuid = $this->addEffectToTestStyle($effect);

    // Load Image Style.
    $image_style = ImageStyle::load($this->testImageStyleName);

    // Check that dependent image style have been added to configuration
    // dependencies.
    $expected_config_dependencies = [
      'image.style.landscape_image_style_test',
    ];
    $this->assertEquals($expected_config_dependencies, $image_style->getDependencies()['config']);

    // Check that no changes are made when source image is portrait.
    // Check that ::transformDimensions returns expected dimensions.
    $original_portrait_image = $image_factory->get($original_portrait_uri);
    $derivative_portrait_url = $this->fileUrlGenerator->transformRelative($this->testImageStyle->buildUrl($original_portrait_uri));
    $variables = [
      '#theme' => 'image_style',
      '#style_name' => $this->testImageStyleName,
      '#uri' => $original_portrait_uri,
      '#width' => $original_portrait_image->getWidth(),
      '#height' => $original_portrait_image->getHeight(),
    ];
    $this->assertMatchesRegularExpression("/\<img src=\"" . preg_quote($derivative_portrait_url, '/') . "\" width=\"20\" height=\"40\" alt=\"\" .*\/\>/", $this->getImageTag($variables));
    // Check that ::applyEffect returns expected dimensions.
    $dest_uri = $image_style->buildUri($original_portrait_uri);
    $image_style->createDerivative($original_portrait_uri, $dest_uri);
    $image = $image_factory->get($dest_uri);
    $this->assertEquals(20, $image->getWidth());
    $this->assertEquals(40, $image->getHeight());
  }

  /**
   * Image style save should fail if AspectSwitcher effect has circular ref.
   */
  public function testAspectSwitcherFailureOnLandscapeCircularReference(): void {
    $effect = [
      'id' => 'image_effects_aspect_switcher',
      'data' => [
        'landscape_image_style' => $this->testImageStyleName,
        'portrait_image_style' => 'portrait_image_style_test',
        'ratio_adjustment' => 1,
      ],
    ];
    $this->testImageStyle->addImageEffect($effect);
    $this->expectException(ConfigValueException::class);
    $this->expectExceptionMessage("You can not select the Image Effects Test image style itself for the landscape style");
    $this->testImageStyle->save();
  }

  /**
   * Image style save should fail if AspectSwitcher effect has circular ref.
   */
  public function testAspectSwitcherFailureOnPortraitCircularReference(): void {
    $effect = [
      'id' => 'image_effects_aspect_switcher',
      'data' => [
        'landscape_image_style' => 'landscape_image_style_test',
        'portrait_image_style' => $this->testImageStyleName,
        'ratio_adjustment' => 1,
      ],
    ];
    $this->testImageStyle->addImageEffect($effect);
    $this->expectException(ConfigValueException::class);
    $this->expectExceptionMessage("You can not select the Image Effects Test image style itself for the portrait style");
    $this->testImageStyle->save();
  }

}
