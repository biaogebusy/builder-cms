<?php

namespace Drupal\image_captcha\Controller;

use Drupal\Core\Config\ImmutableConfig;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\PageCache\ResponsePolicy\KillSwitch;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Controller which generates the image from defined settings.
 */
class CaptchaFontPreviewController implements ContainerInjectionInterface {

  /**
   * Image Captcha config storage.
   *
   * @var \Drupal\Core\Config\ImmutableConfig
   */
  protected $config;

  /**
   * Kill Switch for page caching.
   *
   * @var \Drupal\Core\PageCache\ResponsePolicy\KillSwitch
   */
  protected $killSwitch;

  /**
   * {@inheritdoc}
   */
  public function __construct(ImmutableConfig $config, KillSwitch $kill_switch) {
    $this->config = $config;
    $this->killSwitch = $kill_switch;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.factory')->get('image_captcha.settings'),
      $container->get('page_cache_kill_switch')
    );
  }

  /**
   * Main method that throw ImageResponse object to generate image.
   *
   * @return \Symfony\Component\HttpFoundation\StreamedResponse
   *   Make a StreamedResponse with the correct configuration and return it.
   */
  public function getFont($token) {
    $this->killSwitch->trigger();
    return new StreamedResponse(function () use ($token) {
      // Get the font from the given font token.
      if ($token == 'BUILTIN') {
        $font = 'BUILTIN';
      }
      else {
        // Get the mapping of font tokens to font file objects.
        $fonts = $this->config->get('image_captcha_fonts_preview_map_cache');
        if (!isset($fonts[$token])) {
          throw new \LogicException('Given font token does not exist.');
        }
        // Get the font path.
        $font = $fonts[$token]['uri'];
        // Some sanity checks if the given font is valid.
        if (!is_file($font) || !is_readable($font)) {
          throw new \LogicException('Font could not be loaded.');
        }
      }
      // Settings of the font preview.
      $width = 120;
      $text = 'AaBbCc123';
      $font_size = 14;
      $height = 2 * $font_size;

      // Allocate image resource.
      $image = imagecreatetruecolor($width, $height);
      if (!$image) {
        return NULL;
      }
      // White background and black foreground.
      $background_color = imagecolorallocate($image, 255, 255, 255);
      $color = imagecolorallocate($image, 0, 0, 0);
      imagefilledrectangle($image, 0, 0, $width, $height, $background_color);

      // Draw preview text.
      if ($font == 'BUILTIN') {
        imagestring($image, 5, 1, .5 * $height - 10, $text, $color);
      }
      else {
        imagettftext($image, $font_size, 0, 1, 1.5 * $font_size, $color, realpath($font), $text);
      }
      // Dump image data to client.
      imagepng($image);
        // Release image memory.
      imagedestroy($image);
      unset($image);
    }, 200, ['Content-Type' => 'image/png']);
  }

}
