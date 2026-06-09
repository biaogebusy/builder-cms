<?php

namespace Drupal\ai_automators\PluginBaseClasses;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityTypeBundleInfo;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\Utility\Token;
use Drupal\ai\AiProviderPluginManager;
use Drupal\ai\Guardrail\AiGuardrailHelper;
use Drupal\ai\OperationType\Chat\ChatInput;
use Drupal\ai\OperationType\Chat\ChatMessage;
use Drupal\ai\OperationType\GenericType\AudioFile;
use Drupal\ai\OperationType\GenericType\ImageFile;
use Drupal\ai\OperationType\SpeechToText\SpeechToTextInput;
use Drupal\ai\Service\AiProviderFormHelper;
use Drupal\ai\Service\PromptJsonDecoder\PromptJsonDecoderInterface;
use Drupal\ai_automators\Exceptions\AiAutomatorRequestErrorException;
use Drupal\ai_automators\Exceptions\AiAutomatorResponseErrorException;
use Drupal\file\Entity\File;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * The base for video to text.
 */
class VideoToText extends RuleBase implements ContainerFactoryPluginInterface {

  /**
   * The LLM Type.
   */
  public string $llmType = 'chat';

  /**
   * The entity type manager.
   */
  public EntityTypeManagerInterface $entityManager;

  /**
   * The File System interface.
   */
  public FileSystemInterface $fileSystem;

  /**
   * The token system to replace and generate paths.
   */
  public Token $token;

  /**
   * The temporary directory.
   */
  public string $tmpDir;

  /**
   * The images.
   */
  public array $images;

  /**
   * The tmp video.
   */
  public string $video = "";

  /**
   * The transcription.
   */
  public string $transcription;

  /**
   * The current user.
   */
  public AccountProxyInterface $currentUser;

  /**
   * The module handler.
   */
  public ModuleHandlerInterface $moduleHandler;

  /**
   * The field manager.
   */
  protected EntityFieldManagerInterface $fieldManager;

  /**
   * The entity type bundle info.
   */
  protected EntityTypeBundleInfo $entityTypeBundleInfo;

  /**
   * The prompt json decoder.
   */
  protected PromptJsonDecoderInterface $promptJsonDecoder;

  /**
   * Construct a video to text field.
   *
   * @param \Drupal\ai\AiProviderPluginManager $pluginManager
   *   The AI provider plugin manager.
   * @param \Drupal\ai\Service\AiProviderFormHelper $formHelper
   *   The AI provider form helper.
   * @param \Drupal\ai\Service\PromptJsonDecoder\PromptJsonDecoderInterface $promptJsonDecoder
   *   The prompt json decoder.
   * @param \Drupal\ai\Guardrail\AiGuardrailHelper $aiGuardrailHelper
   *   The AI guardrail helper.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityManager
   *   The entity type manager.
   * @param \Drupal\Core\File\FileSystemInterface $fileSystem
   *   The File system interface.
   * @param \Drupal\Core\Utility\Token $token
   *   The token system.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $moduleHandler
   *   The module handler.
   * @param \Drupal\Core\Session\AccountProxyInterface $currentUser
   *   The current user.
   * @param \Drupal\Core\Entity\EntityFieldManagerInterface $fieldManager
   *   Field manager.
   * @param \Drupal\Core\Entity\EntityTypeBundleInfo $entityTypeBundleInfo
   *   The entity type bundle info.
   */
  public function __construct(
    AiProviderPluginManager $pluginManager,
    AiProviderFormHelper $formHelper,
    PromptJsonDecoderInterface $promptJsonDecoder,
    AiGuardrailHelper $aiGuardrailHelper,
    EntityTypeManagerInterface $entityManager,
    FileSystemInterface $fileSystem,
    Token $token,
    ModuleHandlerInterface $moduleHandler,
    AccountProxyInterface $currentUser,
    EntityFieldManagerInterface $fieldManager,
    EntityTypeBundleInfo $entityTypeBundleInfo,
  ) {
    parent::__construct($pluginManager, $formHelper, $promptJsonDecoder, $aiGuardrailHelper);
    $this->entityManager = $entityManager;
    $this->fileSystem = $fileSystem;
    $this->token = $token;
    $this->currentUser = $currentUser;
    $this->moduleHandler = $moduleHandler;
    $this->fieldManager = $fieldManager;
    $this->entityTypeBundleInfo = $entityTypeBundleInfo;
  }

  /**
   * {@inheritDoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    // @phpstan-ignore-next-line
    return new static(
      $container->get('ai.provider'),
      $container->get('ai.form_helper'),
      $container->get('ai.prompt_json_decode'),
      $container->get('ai.guardrail_helper'),
      $container->get('entity_type.manager'),
      $container->get('file_system'),
      $container->get('token'),
      $container->get('module_handler'),
      $container->get('current_user'),
      $container->get('entity_field.manager'),
      $container->get('entity_type.bundle.info')
    );
  }

  /**
   * Delete files.
   */
  public function __destruct() {
    if (!empty($this->tmpDir) && file_exists($this->tmpDir)) {
      $this->deleteFilesFromTmpDir('', TRUE);
    }
  }

  /**
   * {@inheritDoc}
   */
  public function placeholderText() {
    return "";
  }

  /**
   * {@inheritDoc}
   */
  public function allowedInputs() {
    return [
      'file',
    ];
  }

  /**
   * {@inheritDoc}
   */
  public function generate(ContentEntityInterface $entity, FieldDefinitionInterface $fieldDefinition, array $automatorConfig) {
    // Generate the real prompt if needed.
    $prompts = parent::generate($entity, $fieldDefinition, $automatorConfig);

    // Build up the prompt.
    $configs = [];
    foreach ($automatorConfig as $key => $value) {
      if (strpos($key, 'entity_field_enable_') !== FALSE && $value) {
        $field = str_replace('entity_field_enable_', '', $key);
        $promptPart = $automatorConfig['entity_field_generate_' . $field];
        $configs[] = '"' . $field . '": "' . $promptPart . '"';
      }
    }

    $total = [];
    foreach ($entity->get($automatorConfig['base_field']) as $entityWrapper) {
      if ($entityWrapper->entity) {
        $fileEntity = $entityWrapper->entity;
        if (in_array($fileEntity->getMimeType(), [
          'video/mp4',
        ])) {
          $this->prepareToExplain($automatorConfig, $entityWrapper->entity);
          $prompt = "The following images shows rasters of scenes from a video together with a timestamp when it happens in the video. The audio is transcribed below. Please follow the instructions below with the video as context, using images and transcripts.\n\n";
          $prompt .= "Instructions:\n----------------------------\n" . $prompts[0] . "\n----------------------------\n\n";
          $prompt .= "Transcription:\n----------------------------\n" . $this->transcription . "\n----------------------------\n\n";
          $prompt .= "\n\nDo not include any explanations, only provide a RFC8259 compliant JSON response following this format without deviation.\n[{\"value\": \"requested value\"}].";
          $instance = $this->prepareLlmInstance('chat', $automatorConfig);
          $input = new ChatInput([
            new ChatMessage('user', $prompt, $this->images),
          ]);
          $input = $this->applyGuardrailsToInput($input, $automatorConfig);
          $response = $instance->chat($input, $automatorConfig['ai_model'])->getNormalized();
          $this->assertNotStoppedByGuardrail($input);
          $json = json_decode(str_replace("\n", "", trim(str_replace(['```json', '```'], '', $response->getText()))), TRUE);
          $values = $this->decodeValueArray($json);
          $total = array_merge_recursive($total, $values);
        }
      }
    }
    return $total;
  }

  /**
   * {@inheritDoc}
   */
  public function verifyValue(ContentEntityInterface $entity, $value, FieldDefinitionInterface $fieldDefinition, array $automatorConfig) {
    // Should be a string.
    if (!is_string($value)) {
      return FALSE;
    }
    // Otherwise it is ok.
    return TRUE;
  }

  /**
   * {@inheritDoc}
   */
  public function storeValues(ContentEntityInterface $entity, array $values, FieldDefinitionInterface $fieldDefinition, array $automatorConfig) {
    // Then set the value.
    $entity->set($fieldDefinition->getName(), $values);
    return TRUE;
  }

  /**
   * Generate a video from screenshots.
   *
   * @param \Drupal\file\Entity\File $video
   *   The video.
   * @param string $timeStamp
   *   The timestamp.
   * @param array $cropData
   *   The crop data in x, y, width, height format.
   *
   * @return \Drupal\file\Entity\File
   *   The screenshot image.
   */
  public function screenshotFromTimestamp(File $video, $timeStamp, array $cropData = []) {
    $path = $video->getFileUri();
    // Clean values before using them.
    $command = "-y -nostdin -ss {timeStamp} -i {realPath} -vframes 1 {screenshotFile}";
    $tokens = [
      'screenshotFile' => $this->tmpDir . "screenshot.jpeg",
      'timeStamp' => $this->cleanTimestamp($timeStamp),
      'realPath' => $this->fileSystem->realpath($path),
    ];
    // If we need to crop also.
    if (count($cropData)) {
      $realCropData = $this->normalizeCropData($video, $cropData);
      $tokens['crop'] = 'crop=' . $realCropData[2] . ':' . $realCropData[3] . ':' . $realCropData[0] . ':' . $realCropData[1];
      $command = "-y -nostdin  -ss {timeStamp} -i {realPath} -vf {crop} -vframes 1 {screenshotFile}";
    }
    // Run the command.
    $this->runFfmpegCommand($command, $tokens, 'Could not generate screenshot from video.');
    $newFile = str_replace($video->getFilename(), $video->getFilename() . '_cut', $path) . '.jpg';
    $newFile = preg_replace('/\.(avi|mp4|mov|wmv|flv|mkv)$/', '.jpg', $newFile);
    $fixedFile = $this->fileSystem->move("{$this->tmpDir}screenshot.jpeg", $newFile);
    $file = File::create([
      'uri' => $fixedFile,
      'status' => 1,
      'uid' => $this->currentUser->id(),
    ]);
    return $file;
  }

  /**
   * Get the correct crop data with the base being 640.
   *
   * @param \Drupal\file\Entity\File $video
   *   The video.
   * @param array $cropData
   *   The crop data.
   *
   * @return array
   *   The corrected crop data.
   */
  public function normalizeCropData(File $video, $cropData) {
    $originalWidth = 640;
    // Get the width and height of the video with FFmpeg.
    $realPathEscaped = escapeshellarg($this->fileSystem->realpath($video->getFileUri()));
    $command = "ffprobe -v error -select_streams v:0 -show_entries stream=width,height -of csv=s=x:p=0 $realPathEscaped";
    $result = shell_exec($command);
    [$width] = explode('x', $result);
    $ratio = $width / $originalWidth;
    $newCropData = [];
    foreach ($cropData as $key => $value) {
      $newCropData[$key] = round($value * $ratio);
    }
    // There should only be 4 numeric values.
    if (count($newCropData) != 4) {
      throw new AiAutomatorRequestErrorException('The crop data is not in the correct format.');
    }
    return $newCropData;
  }

  /**
   * {@inheritDoc}
   */
  public function ruleIsAllowed(ContentEntityInterface $entity, FieldDefinitionInterface $fieldDefinition) {
    // Checks system for ffmpeg, otherwise this rule does not exist.
    $command = (PHP_OS == 'WINNT') ? 'where ffmpeg' : 'which ffmpeg';
    $result = shell_exec($command);
    return $result ? TRUE : FALSE;
  }

  /**
   * {@inheritDoc}
   */
  public function extraAdvancedFormFields(ContentEntityInterface $entity, FieldDefinitionInterface $fieldDefinition, FormStateInterface $formState, array $defaultValues = []) {
    $form = parent::extraAdvancedFormFields($entity, $fieldDefinition, $formState, $defaultValues);
    $this->extraProviderForm($form, $formState, 'speech_to_text', 'audio', $this->t('Speech To Text Provider'), $defaultValues);
    return $form;
  }

  /**
   * Generate the images and audio for OpenAI.
   */
  protected function prepareToExplain(array $automatorConfig, File $file, $video = TRUE, $audio = TRUE) {
    $this->createTempDirectory();
    if ($video) {
      $this->createVideoRasterImages($automatorConfig, $file);
    }
    if ($audio) {
      $this->createAudioFile($automatorConfig, $file);
      $this->transcribeAudio($automatorConfig);
    }
  }

  /**
   * Helper function to get the image raster from the video.
   */
  protected function createAudioFile(array $automatorConfig, File $file) {
    // Get the video file.
    $video = $file->getFileUri();
    // Get the actual file path on the server.
    $realPath = $this->fileSystem->realpath($video);
    // Let FFMPEG do its magic.
    $command = "-y -nostdin  -i {realPath} -c:a mp3 -b:a 64k {file}";
    $tokens = [
      'file' => $this->tmpDir . "audio.mp3",
      'realPath' => $realPath,
    ];
    $this->runFfmpegCommand($command, $tokens, 'Could not generate audio from video.');
    return '';
  }

  /**
   * Transcribe the audio.
   */
  protected function transcribeAudio(array $automatorConfig) {
    // Use Whisper to transcribe and then get the segments.
    $input = [
      'model' => 'whisper-1',
      'file' => fopen($this->tmpDir . 'audio.mp3', 'r'),
      'response_format' => 'json',
    ];
    $instance = $this->aiPluginManager->createInstance($automatorConfig['ai_provider_audio']);

    $input = new SpeechToTextInput(new AudioFile(file_get_contents($this->tmpDir . 'audio.mp3'), 'audio/mpeg', 'audio.mp3'));
    $this->transcription = $instance->speechToText($input, $automatorConfig['ai_model_audio'])->getNormalized();
  }

  /**
   * Helper function to get the image raster images from the video.
   */
  protected function createVideoRasterImages($automatorConfig, File $file, $timeStamp = NULL) {
    $this->images = [];
    // Remove all the images.
    $this->deleteFilesFromTmpDir('jpeg');
    // Get the video file.
    $video = $file->getFileUri();
    // Let FFMPEG do its magic.
    $tokens = [
      'tmpDir' => $this->tmpDir,
      'realPath' => $this->fileSystem->realpath($video),
      'thumbFile' => $this->tmpDir . 'output_frame_%04d.jpeg',
      'rasterFile' => $this->tmpDir . 'raster-%04d.jpeg',
      'videoFile' => $this->tmpDir . 'tmpVideo.mp4',
    ];
    $command = "-y -nostdin  -i {realPath} -vf \"select='gt(scene,0.1)',scale=640:-1,drawtext=fontsize=45:fontcolor=yellow:box=1:boxcolor=black:x=(W-tw)/2:y=H-th-10:text='%{pts\:hms}'\" -vsync vfr {thumbFile}";
    // If its timestamp, just get 0.5 seconds before and after.
    $tokens['timeStamp'] = $this->cleanTimestamp($timeStamp);
    if ($tokens['timeStamp']) {
      $command = "-y -nostdin -ss {timeStamp} -i {realPath} -t 3 -vf \"scale=640:-1,drawtext=fontsize=45:fontcolor=yellow:box=1:boxcolor=black:x=(W-tw)/2:y=H-th-10:text='%{pts\:hms}'\" -vsync vfr {thumbFile}";
    }
    $this->runFfmpegCommand($command, $tokens, 'Could not generate images from video.');

    $rasterCommand = "-i {thumbFile} -filter_complex \"scale=640:-1,tile=3x3:margin=10:padding=4:color=white\" {rasterFile}";
    $this->runFfmpegCommand($rasterCommand, $tokens, 'Could not create video raster.');
    $images = glob($this->tmpDir . 'raster-*.jpeg');
    foreach ($images as $uri) {
      $image = new ImageFile();
      $image->setFileFromUri($uri);
      $this->images[] = $image;
    }
    // If timestamp also generate a temp video.
    if ($tokens['timeStamp']) {
      $command = "-y -nostdin -ss {timeStamp} -i {realPath} -t 3 -c:v libx264 -qscale 0 {videoFile}";
      $this->runFfmpegCommand($command, $tokens, 'Could not generate video from video.');
      $this->video = "{$this->tmpDir}tmpVideo.mp4";
    }
  }

  /**
   * Helper function to generate a temp directory.
   */
  protected function createTempDirectory() {
    $this->tmpDir = $this->fileSystem->getTempDirectory() . '/ai_automator/' . mt_rand(10000, 99999) . '/';
    if (!file_exists($this->tmpDir)) {
      $this->fileSystem->mkdir($this->tmpDir, NULL, TRUE);
    }
  }

  /**
   * Gets the entity token type.
   *
   * @param string $entityTypeId
   *   The entity type id.
   *
   * @return string
   *   The corrected type.
   */
  public function getEntityTokenType($entityTypeId) {
    switch ($entityTypeId) {
      case 'taxonomy_term':
        return 'term';
    }
    return $entityTypeId;
  }

  /**
   * Render a tokenized prompt.
   *
   * @var string $prompt
   *   The prompt.
   * @var \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The entity.
   *
   * @return string
   *   The rendered prompt.
   */
  public function renderTokenPrompt($prompt, ContentEntityInterface $entity) {
    // Get variables.
    return $this->token->replace($prompt, [
      $this->getEntityTokenType($entity->getEntityTypeId()) => $entity,
      'user' => $this->currentUser,
    ]);
  }

  /**
   * Calculate with ffmpeg data.
   */
  public function calculateFfmpegTimestamp($timestamp, $calculation) {
    $date = \DateTime::createFromFormat('H:i:s.u', $timestamp);

    $interval = new \DateInterval('PT0S');
    $interval->f = $calculation;
    $date->sub($interval);

    return substr($date->format('H:i:s.u'), 0, -3);
  }

  /**
   * Clean up timestamp.
   *
   * @param string|null $timeStamp
   *   The timestamp.
   *
   * @return string
   *   The cleaned up timestamp.
   */
  public function cleanTimestamp(?string $timeStamp = NULL) {
    // If its null, just return it.
    if (!$timeStamp) {
      return NULL;
    }
    // Make sure it follows the h:i:s.ms, since we can't escape this.
    if (!preg_match('/^(\d{1,2}:\d{2}:\d{2}\.\d{2,3})$/', $timeStamp)) {
      throw new AiAutomatorRequestErrorException('The timestamp is not in the correct format.');
    }
    return $timeStamp;
  }

  /**
   * Run FFMPEG command.
   *
   * @param string $command
   *   The command to run.
   * @param array $tokens
   *   The tokens to replace.
   * @param string $error_message
   *   Error message to throw if it fails.
   */
  public function runFfmpegCommand($command, array $tokens, $error_message) {
    $command = $this->prepareFfmpegCommand($command, $tokens);
    exec($command, $status);
    if ($status) {
      throw new AiAutomatorResponseErrorException($error_message);
    }
  }

  /**
   * Prepare the FFMPEG command.
   *
   * @param string $command
   *   The command to run.
   * @param array $tokens
   *   The tokens to replace.
   *
   * @return string
   *   The prepared command.
   */
  public function prepareFfmpegCommand($command, array $tokens) {
    foreach ($tokens as $token => $value) {
      // Only escape if it is not empty.
      if (empty($value)) {
        continue;
      }
      $escaped_value = escapeshellarg($value);
      $command = str_replace("{{$token}}", $escaped_value, $command);
    }
    // @todo Add full path to ffmpeg.
    return "ffmpeg $command";
  }

  /**
   * Delete files from the tmp dir.
   *
   * @param string $ext
   *   The extension to delete.
   * @param bool $remove_directory
   *   If the directory should be removed.
   */
  public function deleteFilesFromTmpDir($ext = '', $remove_directory = FALSE) {
    // Get the actual tmp directory, to make sure nothing was injected.
    $tmpDir = $this->fileSystem->getTempDirectory() . '/ai_automator';

    foreach (scandir($tmpDir) as $file) {
      if ($file == '.' || $file == '..') {
        continue;
      }
      if ($ext && pathinfo($file, PATHINFO_EXTENSION) != $ext) {
        continue;
      }
      unlink($tmpDir . $file);
    }

    if ($remove_directory) {
      rmdir($tmpDir);
    }
  }

}
