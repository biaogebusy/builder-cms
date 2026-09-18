<?php

namespace Drupal\image_captcha\Controller;

use Drupal\Core\Config\Config;
use Drupal\Core\Database\Connection;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\PageCache\ResponsePolicy\KillSwitch;
use Drupal\image_captcha\Constants\ImageCaptchaConstants;
use Drupal\image_captcha\Service\ImageCaptchaRenderService;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Controller which generates the image from defined settings.
 */
class CaptchaImageGeneratorController implements ContainerInjectionInterface {

  /**
   * Connection container.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $connection;

  /**
   * Image Captcha config storage.
   *
   * @var \Drupal\Core\Config\Config
   */
  protected $config;

  /**
   * File System Service.
   *
   * @var \Drupal\Core\File\FileSystemInterface
   */
  protected $fileSystem;

  /**
   * Watchdog logger channel for captcha.
   *
   * @var \Psr\Log\LoggerInterface
   */
  protected $logger;

  /**
   * Kill Switch for page caching.
   *
   * @var \Drupal\Core\PageCache\ResponsePolicy\KillSwitch
   */
  protected $killSwitch;

  /**
   * Resource with generated image.
   *
   * @var \Drupal\image_captcha\Service\ImageCaptchaRenderService
   */
  protected $imageRender;

  /**
   * {@inheritdoc}
   */
  public function __construct(Config $config, LoggerInterface $logger, KillSwitch $kill_switch, Connection $connection, FileSystemInterface $file_system, ImageCaptchaRenderService $image_capcha_render) {
    $this->config = $config;
    $this->logger = $logger;
    $this->killSwitch = $kill_switch;
    $this->connection = $connection;
    $this->fileSystem = $file_system;
    $this->imageRender = $image_capcha_render;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.factory')->get('image_captcha.settings'),
      $container->get('logger.factory')->get('captcha'),
      $container->get('page_cache_kill_switch'),
      $container->get('database'),
      $container->get('file_system'),
      $container->get('image_captcha.render_service')
    );
  }

  /**
   * Main method that throw ImageResponse object to generate image.
   *
   * @return \Symfony\Component\HttpFoundation\StreamedResponse
   *   Make a StreamedResponse with the correct configuration and return it.
   */
  public function image(Request $request) {
    $this->killSwitch->trigger();

    // Process the response headers for the image.
    if ($this->config->get('image_captcha_file_format') == ImageCaptchaConstants::IMAGE_CAPTCHA_FILE_FORMAT_JPG) {
      $response_headers = ['content-type' => 'image/jpeg'];
    }
    else {
      $response_headers = ['content-type' => 'image/png'];
    }
    $response_headers['cache-control'] = 'no-store, must-revalidate';

    // Check for existing session IDs.
    $session_id = $request->get('session_id');
    $code = $this->connection
      ->select('captcha_sessions', 'cs')
      ->fields('cs', ['solution'])
      ->condition('csid', $session_id)
      ->execute()
      ->fetchField();

    // If there is an existing session, process the image.
    $image = NULL;
    if ($code !== FALSE) {
      $image = $this->imageRender->generateImage($code);

      if (!$image) {
        $this->logger->log('error', 'Generation of image CAPTCHA failed. Check your image CAPTCHA configuration and especially the used font.', []);
      }
    }
    return new StreamedResponse(function () use ($image) {
      if (!$image) {
        return $this;
      }

      // Begin capturing the byte stream.
      ob_start();

      // Get the file format of the image.
      $file_format = $this->config->get('image_captcha_file_format');
      if ($file_format == ImageCaptchaConstants::IMAGE_CAPTCHA_FILE_FORMAT_JPG) {
        imagejpeg($image);
      }
      else {
        imagepng($image);

      }
        // Release image memory.
      imagedestroy($image);
      unset($image);
      return $this;

    }, 200, $response_headers);
  }

}
