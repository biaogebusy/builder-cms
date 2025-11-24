<?php

declare(strict_types=1);

namespace Drupal\Tests\image_effects\Functional\Effect;

use Drupal\Core\File\FileExists;
use Drupal\Tests\image_effects\Functional\ImageEffectsTestBase;

/**
 * Text overlay effect test.
 *
 * @group image_effects
 */
class TextOverlayTest extends ImageEffectsTestBase {

  /**
   * {@inheritdoc}
   */
  public function setUp(): void {
    static::$modules = array_merge(static::$modules, [
      'file_mdm',
      'file_mdm_font',
      'file_test',
      'vendor_stream_wrapper',
    ]);
    parent::setUp();
  }

  /**
   * {@inheritdoc}
   */
  public static function providerToolkits(): array {
    $toolkits = parent::providerToolkits();
    // @todo This effect does not work on GraphicsMagick.
    unset($toolkits['ImageMagick-graphicsmagick']);
    return $toolkits;
  }

  /**
   * Text overlay effect test.
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
  public function testTextOverlayEffect(string $toolkit_id, string $toolkit_config, array $toolkit_settings): void {
    $this->changeToolkit($toolkit_id, $toolkit_config, $toolkit_settings);

    // Copy the font file to the test path.
    $this->fileSystem->copy('vendor://fileeye/linuxlibertine-fonts/LinLibertine_Rah.ttf', 'dummy-remote://', FileExists::Replace);

    // Add Text overlay effects to the test image style.
    // Different ways to access the same font file, via URI (local and remote),
    // and local path.
    $effect_config = [
      'id' => 'image_effects_text_overlay',
      'data' => [
        'text_default][text_string' => 'the quick brown fox jumps over the lazy dog',
        'font][uri' => $this->fileSystem->realpath('vendor://fileeye/linuxlibertine-fonts/LinLibertine_Rah.ttf'),
        'font][size' => 40,
        'layout][position][extended_color][container][transparent' => FALSE,
        'layout][position][extended_color][container][hex' => '#FF00FF',
        'layout][position][extended_color][container][opacity' => 100,
      ],
    ];
    $this->addEffectToTestStyle($effect_config);
    $effect_config = [
      'id' => 'image_effects_text_overlay',
      'data' => [
        'text_default][text_string' => 'the quick brown fox jumps over the lazy dog',
        'font][uri' => 'vendor://fileeye/linuxlibertine-fonts/LinLibertine_Rah.ttf',
        'font][size' => 10,
        'layout][position][extended_color][container][transparent' => FALSE,
        'layout][position][extended_color][container][hex' => '#FF00FF',
        'layout][position][extended_color][container][opacity' => 100,
      ],
    ];
    $this->addEffectToTestStyle($effect_config);
    $effect_config = [
      'id' => 'image_effects_text_overlay',
      'data' => [
        'text_default][text_string' => 'the quick brown fox jumps over the lazy dog',
        'font][uri' => 'dummy-remote://LinLibertine_Rah.ttf',
        'font][size' => 10,
        'layout][position][extended_color][container][transparent' => FALSE,
        'layout][position][extended_color][container][hex' => '#FF00FF',
        'layout][position][extended_color][container][opacity' => 100,
      ],
    ];
    $this->addEffectToTestStyle($effect_config);

    // Check that no temporary files are left in Imagemagick.
    if ($toolkit_id === 'imagemagick') {
      $directory_scan = $this->fileSystem->scanDirectory('temporary://', '/ifx.*/');
      $this->assertCount(0, $directory_scan);
    }

    $test_data = [
      [
        'test_file' => $this->getTestImageCopyUri('core/tests/fixtures/files/image-test.png'),
        'derivative_width' => 984,
        'derivative_height' => 61,
      ],
    ];

    foreach ($test_data as $data) {
      // Get expected URIs.
      $original_uri = $data['test_file'];
      $derivative_uri = $this->testImageStyle->buildUri($original_uri);

      // Source image.
      $image = $this->imageFactory->get($original_uri);

      // Load Image Style and get expected derivative URL.
      $derivative_url = $this->fileUrlGenerator->transformRelative($this->testImageStyle->buildUrl($original_uri));

      // Check that ::applyEffect generates image with expected dimensions
      // and colors at corners.
      $this->testImageStyle->createDerivative($original_uri, $derivative_uri);
      $derivative_image = $this->imageFactory->get($derivative_uri, 'gd');
      $this->assertTextOverlay($derivative_image, $data['derivative_width'], $data['derivative_height']);
      $this->assertColorsAreEqual($this->fuchsia, $this->getPixelColor($derivative_image, 0, 0));
      $this->assertColorsAreEqual($this->fuchsia, $this->getPixelColor($derivative_image, $derivative_image->getWidth() - 1, 0));
      $this->assertColorsAreEqual($this->fuchsia, $this->getPixelColor($derivative_image, 0, $derivative_image->getHeight() - 1));
      $this->assertColorsAreEqual($this->fuchsia, $this->getPixelColor($derivative_image, $derivative_image->getWidth() - 1, $derivative_image->getHeight() - 1));

      // Check that ::transformDimensions returns expected dimensions.
      $variables = [
        '#theme' => 'image_style',
        '#style_name' => 'image_effects_test',
        '#uri' => $original_uri,
        '#width' => $image->getWidth(),
        '#height' => $image->getHeight(),
      ];
      $this->assertMatchesRegularExpression("/\<img src=\"" . preg_quote($derivative_url, '/') . "\" width=\"{$derivative_image->getWidth()}\" height=\"{$derivative_image->getHeight()}\" alt=\"\" .*\/\>/", $this->getImageTag($variables));
    }
  }

  /**
   * Text alteration test.
   */
  public function testTextAlter(): void {
    // Add Text overlay effect to the test image style.
    $effect_config = [
      'id' => 'image_effects_text_overlay',
      'data' => [
        'text_default][text_string' => 'the quick brown fox jumps over the lazy dog',
        'font][uri' => 'vendor://fileeye/linuxlibertine-fonts/LinLibertine_Rah.ttf',
        'font][size' => 40,
        'layout][position][extended_color][container][transparent' => FALSE,
        'layout][position][extended_color][container][hex' => '#FF00FF',
        'layout][position][extended_color][container][opacity' => 100,
      ],
    ];
    $uuid = $this->addEffectToTestStyle($effect_config);

    // Test text and HTML tags and entities.
    /** @var \Drupal\image_effects\Plugin\ImageEffect\TextOverlayImageEffect $effect */
    $effect = $this->testImageStyle->getEffect($uuid);
    $this->assertEquals('the quick brown fox jumps over the lazy dog', $effect->getAlteredText($effect->getConfiguration()['data']['text_string']));
    $this->assertEquals('Para1 Para2', $effect->getAlteredText('<p>Para1</p><!-- Comment --> Para2'));
    $this->assertEquals('"Title" One …', $effect->getAlteredText('&quot;Title&quot; One &hellip;'));
    $this->removeEffectFromTestStyle($uuid);
    $effect_config['data'] += [
      'text_default][strip_tags' => FALSE,
      'text_default][decode_entities' => FALSE,
    ];
    $uuid = $this->addEffectToTestStyle($effect_config);
    /** @var \Drupal\image_effects\Plugin\ImageEffect\TextOverlayImageEffect $effect */
    $effect = $this->testImageStyle->getEffect($uuid);
    $this->assertEquals('<p>Para1</p><!-- Comment --> Para2', $effect->getAlteredText('<p>Para1</p><!-- Comment --> Para2'));
    $this->assertEquals('&quot;Title&quot; One &hellip;', $effect->getAlteredText('&quot;Title&quot; One &hellip;'));

    // Test converting to uppercase and trimming text.
    $this->removeEffectFromTestStyle($uuid);
    $effect_config['data'] += [
      'text][maximum_chars' => 9,
      'text][case_format' => 'upper',
    ];
    $uuid = $this->addEffectToTestStyle($effect_config);
    /** @var \Drupal\image_effects\Plugin\ImageEffect\TextOverlayImageEffect $effect */
    $effect = $this->testImageStyle->getEffect($uuid);
    $this->assertEquals('THE QUICK…', $effect->getAlteredText($effect->getConfiguration()['data']['text_string']));

    // Test converting to each word starting uppercase.
    $this->removeEffectFromTestStyle($uuid);
    $effect_config['data']['text][case_format'] = 'ucwords';
    $effect_config['data']['text][maximum_chars'] = '';
    $uuid = $this->addEffectToTestStyle($effect_config);
    /** @var \Drupal\image_effects\Plugin\ImageEffect\TextOverlayImageEffect $effect */
    $effect = $this->testImageStyle->getEffect($uuid);
    $this->assertEquals('The Quick Brown Fox Jumps Over The Lazy Dog', $effect->getAlteredText($effect->getConfiguration()['data']['text_string']));

    // Test converting to starting uppercase.
    $this->removeEffectFromTestStyle($uuid);
    $effect_config['data']['text][case_format'] = 'ucfirst';
    $uuid = $this->addEffectToTestStyle($effect_config);
    /** @var \Drupal\image_effects\Plugin\ImageEffect\TextOverlayImageEffect $effect */
    $effect = $this->testImageStyle->getEffect($uuid);
    $this->assertEquals('The quick brown fox jumps over the lazy dog', $effect->getAlteredText($effect->getConfiguration()['data']['text_string']));
  }

}
