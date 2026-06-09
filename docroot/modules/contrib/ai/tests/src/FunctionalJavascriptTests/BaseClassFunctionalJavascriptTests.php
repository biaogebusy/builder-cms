<?php

declare(strict_types=1);

namespace Drupal\Tests\ai\FunctionalJavascriptTests;

use Drupal\FunctionalJavascriptTests\WebDriverTestBase;

/**
 * This is a base class for functional JavaScript tests in the AI module.
 */
abstract class BaseClassFunctionalJavascriptTests extends WebDriverTestBase {

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'claro';

  /**
   * We need to fix the automators schema.
   *
   * @var bool
   */
  // phpcs:ignore
  protected $strictConfigSchema = FALSE;

  /**
   * Path to save screenshots.
   *
   * @var string
   */
  protected string $screenshotPath = 'sites/default/files/simpletest/screenshots/';

  /**
   * Screenshot category.
   *
   * @var string
   */
  protected string $screenshotCategory = '';

  /**
   * The module name for screenshots.
   *
   * @var string
   */
  protected string $screenshotModuleName = 'ai';

  /**
   * If counter is used for screenshot filenames.
   *
   * @var int
   */
  protected int $screenshotCounter = 1;

  /**
   * Whether to enable video recording for tests in this class.
   *
   * When set to TRUE, Chrome will run non-headless (rendering on the X
   * display) and video recording will automatically start/stop for each test
   * method. The MINK_DRIVER_ARGS_WEBDRIVER environment variable must point to
   * a Selenium container with an accessible X display.
   *
   * @var bool
   */
  protected bool $videoRecording = FALSE;

  /**
   * The X display to capture for video recording.
   *
   * Defaults to using the DRUPAL_TEST_WEBDRIVER_HOSTNAME environment variable
   * with display :99.0, falling back to 'selenium-chrome:99.0'.
   *
   * @var string
   */
  protected string $videoDisplay = '';

  /**
   * Video frame rate (fps).
   *
   * @var int
   */
  protected int $videoFrameRate = 25;

  /**
   * Bitrate for the video.
   *
   * @var string
   */
  protected string $videoBitrate = '750k';

  /**
   * Video width in pixels.
   *
   * @var int
   */
  protected int $videoWidth = 1920;

  /**
   * Video height in pixels.
   *
   * @var int
   */
  protected int $videoHeight = 1080;

  /**
   * Path to save video recordings.
   *
   * @var string
   */
  protected string $videoPath = 'sites/default/files/simpletest/videos/';

  /**
   * The ffmpeg process resource.
   *
   * @var resource|null
   */
  private $ffmpegProcess = NULL;

  /**
   * The pipes for the ffmpeg process.
   *
   * @var array
   */
  private array $ffmpegPipes = [];

  /**
   * The current video file path being recorded.
   *
   * @var string
   */
  private string $currentVideoFile = '';

  /**
   * {@inheritdoc}
   */
  protected function getMinkDriverArgs(): bool|string {
    $json = parent::getMinkDriverArgs();
    if ($this->videoRecording && $json) {
      $args = json_decode($json, TRUE, 512, JSON_THROW_ON_ERROR);
      if (isset($args[1]['goog:chromeOptions']['args'])) {
        // Remove --headless flag so Chrome renders on the X display.
        $args[1]['goog:chromeOptions']['args'] = array_values(array_filter(
          $args[1]['goog:chromeOptions']['args'],
          static fn($arg) => !str_starts_with($arg, '--headless')
        ));
        // Ensure the window size matches the video resolution.
        $args[1]['goog:chromeOptions']['args'] = array_values(array_filter(
          $args[1]['goog:chromeOptions']['args'],
          static fn($arg) => !str_starts_with($arg, '--window-size')
        ));
        $args[1]['goog:chromeOptions']['args'][] = '--window-size=' . $this->videoWidth . ',' . $this->videoHeight;
      }
      $json = json_encode($args, JSON_THROW_ON_ERROR);
    }
    return $json;
  }

  /**
   * {@inheritdoc}
   */
  protected function initFrontPage(): void {
    parent::initFrontPage();
    if ($this->videoRecording) {
      $this->getSession()->resizeWindow($this->videoWidth, $this->videoHeight);
    }
  }

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    if ($this->videoRecording) {
      $this->startVideoRecording();
    }
  }

  /**
   * {@inheritdoc}
   */
  protected function tearDown(): void {
    if ($this->ffmpegProcess) {
      $this->stopVideoRecording();
    }
    parent::tearDown();
  }

  /**
   * Start recording video of the browser display using ffmpeg.
   *
   * Captures the Selenium container's X display via ffmpeg x11grab. Requires
   * ffmpeg to be installed in the test runner container and the Selenium
   * container's X display to be accessible over the network.
   *
   * @param string $testName
   *   Optional custom name for the video file. Defaults to the current test
   *   method name.
   * @param int $width
   *   Video width in pixels. Defaults to $this->videoWidth.
   * @param int $height
   *   Video height in pixels. Defaults to $this->videoHeight.
   * @param int $frameRate
   *   Video frame rate (fps). Defaults to $this->videoFrameRate.
   * @param string $bitrate
   *   Video bitrate (e.g., '750k'). Defaults to $this->videoBitrate.
   */
  protected function startVideoRecording(string $testName = '', int $width = 0, int $height = 0, int $frameRate = 0, string $bitrate = ''): void {
    if ($this->ffmpegProcess) {
      $this->stopVideoRecording();
    }

    $width = $width ?: $this->videoWidth;
    $height = $height ?: $this->videoHeight;
    $frameRate = $frameRate ?: $this->videoFrameRate;
    $bitrate = $bitrate ?: $this->videoBitrate;

    if (empty($testName)) {
      $testName = $this->name();
    }

    $category = $this->getVideoCategory();
    $directory = "{$this->videoPath}{$this->screenshotModuleName}/{$category}";
    if (!file_exists($directory)) {
      mkdir($directory, 0777, TRUE);
    }

    $filename = preg_replace('/[^a-zA-Z0-9_-]/', '_', $testName) . '.mp4';
    $this->currentVideoFile = $directory . '/' . $filename;

    $display = $this->getVideoDisplay();
    $videoSize = $width . 'x' . $height;

    // Securely escape shell arguments to prevent command injection. Frame rate
    // is cast to int to ensure it's a valid number.
    $command = sprintf(
      'exec ffmpeg -y -f x11grab -video_size %s -r %d -i %s -codec:v libx264 -b:v %s -preset ultrafast -pix_fmt yuv420p %s',
      escapeshellarg($videoSize),
      (int) $frameRate,
      escapeshellarg($display),
      escapeshellarg($bitrate),
      escapeshellarg($this->currentVideoFile)
    );

    $descriptors = [
      0 => ['pipe', 'r'],
      1 => ['pipe', 'w'],
      2 => ['pipe', 'w'],
    ];

    $this->ffmpegProcess = proc_open($command, $descriptors, $this->ffmpegPipes);

    if (!is_resource($this->ffmpegProcess)) {
      $this->ffmpegProcess = NULL;
      $this->ffmpegPipes = [];
      trigger_error('Failed to start ffmpeg video recording', E_USER_WARNING);
      return;
    }

    // Poll proc_get_status() to confirm ffmpeg has started.
    $maxAttempts = 20;
    for ($i = 0; $i < $maxAttempts; $i++) {
      $status = proc_get_status($this->ffmpegProcess);
      if ($status['running']) {
        break;
      }
      usleep(50000);
    }

    if (!$status['running']) {
      trigger_error('ffmpeg process did not start within the expected time', E_USER_WARNING);
    }

    $this->videoLog(sprintf(
      'Started: %s (%sx%s @ %dfps, %s bitrate, display %s)',
      $this->currentVideoFile,
      $width,
      $height,
      $frameRate,
      $bitrate,
      $display
    ));
  }

  /**
   * Stop the current video recording.
   */
  protected function stopVideoRecording(): void {
    if (!$this->ffmpegProcess) {
      return;
    }

    // Write 'q' to ffmpeg's stdin for a graceful quit that finalizes the
    // mp4 moov atom. Fall back to SIGTERM if stdin write fails.
    if (isset($this->ffmpegPipes[0]) && is_resource($this->ffmpegPipes[0])) {
      fwrite($this->ffmpegPipes[0], 'q');
      fclose($this->ffmpegPipes[0]);
    }
    else {
      proc_terminate($this->ffmpegProcess, 15);
    }

    // Wait for ffmpeg to finish writing the file (up to 10 seconds).
    $maxWait = 100;
    for ($i = 0; $i < $maxWait; $i++) {
      $status = proc_get_status($this->ffmpegProcess);
      if (!$status['running']) {
        break;
      }
      usleep(100000);
    }

    // Force terminate if still running.
    $status = proc_get_status($this->ffmpegProcess);
    if ($status['running']) {
      proc_terminate($this->ffmpegProcess, 9);
    }

    // Drain and close remaining pipes. Reading residual output prevents
    // ffmpeg from blocking on a full pipe buffer during shutdown.
    if (isset($this->ffmpegPipes[1]) && is_resource($this->ffmpegPipes[1])) {
      stream_get_contents($this->ffmpegPipes[1]);
      fclose($this->ffmpegPipes[1]);
    }
    if (isset($this->ffmpegPipes[2]) && is_resource($this->ffmpegPipes[2])) {
      stream_get_contents($this->ffmpegPipes[2]);
      fclose($this->ffmpegPipes[2]);
    }

    proc_close($this->ffmpegProcess);

    $this->videoLog(sprintf(
      'Stopped: %s',
      $this->currentVideoFile
    ));

    $this->ffmpegProcess = NULL;
    $this->ffmpegPipes = [];
  }

  /**
   * Log a video recording message to a log file and PHP error log.
   *
   * @param string $message
   *   The message to log.
   */
  protected function videoLog(string $message): void {
    $timestamp = date('Y-m-d H:i:s');
    $logMessage = sprintf('[%s] [Video Recording] %s', $timestamp, $message);

    // Write to a log file in the video directory (collected as CI artifact).
    $logDir = $this->videoPath . $this->screenshotModuleName;
    if (!file_exists($logDir)) {
      mkdir($logDir, 0777, TRUE);
    }
    file_put_contents($logDir . '/video_recording.log', $logMessage . "\n", FILE_APPEND);

    // Also write to the PHP error log for CI job log visibility.
    error_log($logMessage);
  }

  /**
   * Get the video category based on the test class name.
   *
   * @return string
   *   The category string for organizing video files.
   */
  private function getVideoCategory(): string {
    if (!empty($this->screenshotCategory)) {
      return $this->screenshotCategory;
    }
    $name = explode('\\', static::class);
    $last = array_pop($name);
    return $last ?: 'default';
  }

  /**
   * Get the X display string for ffmpeg to capture.
   *
   * @return string
   *   The display string (e.g., 'selenium-chrome:99.0').
   */
  private function getVideoDisplay(): string {
    if (!empty($this->videoDisplay)) {
      return $this->videoDisplay;
    }
    $host = getenv('DRUPAL_TEST_WEBDRIVER_HOSTNAME') ?: 'selenium-chrome';
    return $host . ':99.0';
  }

  /**
   * Add a screenshot method to capture the current state of the page.
   */
  protected function takeScreenshot($filename = ''): void {
    // Ensure that the screenshot category is set.
    if (empty($this->screenshotCategory)) {
      // Set the class name as the screenshot category.
      $name = explode('\\', static::class);
      // Use the last part of the class name as the category.
      if (!empty($name[0])) {
        $last = array_pop($name);
        $this->screenshotCategory = $last;
      }
      else {
        $this->screenshotCategory = 'default';
      }
    }
    // Create the directory if it does not exist.
    $directory = $this->screenshotPath . $this->screenshotModuleName . '/' . $this->screenshotCategory;
    if (!file_exists($directory)) {
      mkdir($directory, 0777, TRUE);
    }
    $screenshot = $this->getSession()->getDriver()->getScreenshot();
    // If no filename is provided, use a counter to generate a unique name.
    if (empty($filename)) {
      $filename = 'screenshot_' . $this->screenshotCounter . '.png';
      $this->screenshotCounter++;
    }
    elseif (!str_contains($filename, '.png')) {
      // Ensure the filename ends with .png.
      $filename .= '.png';
    }
    $file_path = $directory . '/' . $filename;
    file_put_contents($file_path, $screenshot);
  }

  /**
   * Set the default AI provider for a given operation type.
   *
   * @param string $operationType
   *   The operation type (e.g. 'chat', 'embeddings').
   * @param string $providerId
   *   The provider plugin ID.
   * @param string $modelId
   *   The model identifier.
   */
  protected function setDefaultProvider(string $operationType, string $providerId, string $modelId): void {
    \Drupal::service('config.factory')
      ->getEditable('ai.settings')
      ->set('default_providers.' . $operationType, [
        'provider_id' => $providerId,
        'model_id' => $modelId,
      ])
      ->save();
  }

}
