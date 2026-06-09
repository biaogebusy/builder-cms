<?php

namespace Drupal\ai_automators\Plugin\AiAutomatorType;

use Drupal\ai\Utility\Textarea;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\File\FileExists;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\ai\OperationType\Chat\ChatInput;
use Drupal\ai\OperationType\Chat\ChatMessage;
use Drupal\ai_automators\Attribute\AiAutomatorType;
use Drupal\ai_automators\Exceptions\AiAutomatorResponseErrorException;
use Drupal\ai_automators\PluginBaseClasses\VideoToText;
use Drupal\ai_automators\PluginInterfaces\AiAutomatorTypeInterface;
use Drupal\file\Entity\File;

/**
 * The rules for a video to html field.
 */
#[AiAutomatorType(
  id: 'llm_video_to_html',
  label: new TranslatableMarkup('LLM: Video To Image (Experimental)'),
  field_rule: 'image',
  target: 'file',
)]
class LlmVideoToImage extends VideoToText implements AiAutomatorTypeInterface {

  /**
   * {@inheritDoc}
   */
  public $title = 'LLM: Video To Image (Experimental)';

  /**
   * {@inheritDoc}
   */
  public function needsPrompt() {
    return FALSE;
  }

  /**
   * {@inheritDoc}
   */
  public function advancedMode() {
    return FALSE;
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
  public function ruleIsAllowed(ContentEntityInterface $entity, FieldDefinitionInterface $fieldDefinition) {
    // Checks system for ffmpeg, otherwise this rule does not exist.
    $command = (PHP_OS == 'WINNT') ? 'where ffmpeg' : 'which ffmpeg';
    $result = shell_exec($command);
    return $result ? TRUE : FALSE;
  }

  /**
   * {@inheritDoc}
   */
  public function tokens(ContentEntityInterface $entity) {
    return [];
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
  public function extraFormFields(ContentEntityInterface $entity, FieldDefinitionInterface $fieldDefinition, FormStateInterface $formState, array $defaultValues = []) {
    $form['automator_cutting_prompt'] = [
      '#type' => 'textarea',
      '#title' => 'Cutting Prompt',
      '#description' => $this->t('Any commands that you need to give to cut out the image(s).Can use Tokens if Token module is installed.'),
      '#attributes' => [
        'placeholder' => $this->t('Cut out an image where they show two people holding hands.'),
      ],
      '#default_value' => $defaultValues['automator_cutting_prompt'] ?? '',
      '#weight' => 24,
      // This property will land into core soon, see
      // https://www.drupal.org/project/drupal/issues/3202631. It can stay
      // after this is added to Drupal core.
      '#normalize_newlines' => TRUE,
      // Until that the custom value callback is needed. Should be removed
      // after the issue mentioned above is merged into core and the minimum
      // supported Drupal version includes `#normalize_newlines` property.
      '#value_callback' => [Textarea::class, 'valueCallback'],
    ];

    if ($this->moduleHandler->moduleExists('token')) {
      // Because we have to invoke this only if the module is installed, no
      // dependency injection.
      // @codingStandardsIgnoreLine @phpstan-ignore-next-line
      $form['automator_cutting_prompt_token_help'] = \Drupal::service('token.tree_builder')->buildRenderable([
        $this->getEntityTokenType($entity->getEntityTypeId()),
        'current-user',
      ]);
      $form['automator_cutting_prompt_token_help']['#weight'] = 25;
    }
    return $form;
  }

  /**
   * {@inheritDoc}
   */
  public function generate(ContentEntityInterface $entity, FieldDefinitionInterface $fieldDefinition, array $automatorConfig) {
    // Tokenize prompt.
    $cutPrompt = $this->renderTokenPrompt($automatorConfig['cutting_prompt'], $entity);

    $total = [];
    foreach ($entity->get($automatorConfig['base_field']) as $entityWrapper) {
      if ($entityWrapper->entity) {
        $fileEntity = $entityWrapper->entity;
        if (in_array($fileEntity->getMimeType(), [
          'video/mp4',
        ])) {
          $this->prepareToExplain($automatorConfig, $entityWrapper->entity);
          $prompt = "The following images shows rasters of scenes from a video together with a timestamp when it happens in the video. The audio is transcribed below. Please follow the instructions below with the video as context, using images and transcripts and try to figure out what image or images the person wants to cut out. Give back multiple timestamps if multiple images are wanted.\n\n";
          $prompt .= "Instructions:\n----------------------------\n" . $cutPrompt . "\n----------------------------\n\n";
          $prompt .= "Transcription:\n----------------------------\n" . $this->transcription . "\n----------------------------\n\n";
          $prompt .= "\n\nDo not include any explanations, only provide a RFC8259 compliant JSON response following this format without deviation.\n[{\"value\": [{\"timestamp\": \"The timestamp to take an image in format h:i:s.ms\"}]].";
          $instance = $this->prepareLlmInstance('chat', $automatorConfig);
          $input = new ChatInput([
            new ChatMessage('user', $prompt, $this->images),
          ]);
          $input = $this->applyGuardrailsToInput($input, $automatorConfig);
          $response = $instance->chat($input, $automatorConfig['ai_model'])->getNormalized();
          $this->assertNotStoppedByGuardrail($input);
          $json = json_decode(str_replace("\n", "", trim(str_replace(['```json', '```'], '', $response->getText()))), TRUE);
          $values = $this->decodeValueArray($json);
          // Run a second time to get the exact shot.
          if (!isset($values[0][0]['timestamp'])) {
            throw new AiAutomatorResponseErrorException('Could not find any timestamp..');
          }
          $this->createVideoRasterImages($automatorConfig, $entityWrapper->entity, $values[0][0]['timestamp']);
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
    // Should have start and end time.
    if (!isset($value[0]['timestamp'])) {
      return FALSE;
    }
    // Otherwise it is ok.
    return TRUE;
  }

  /**
   * {@inheritDoc}
   */
  public function storeValues(ContentEntityInterface $entity, array $values, FieldDefinitionInterface $fieldDefinition, array $automatorConfig) {
    $files = [];
    // Create a tmp directory.
    $this->createTempDirectory();

    // First cut out the videos.
    $baseField = $automatorConfig['base_field'] ?? '';
    $realPath = $this->fileSystem->realpath($entity->get($baseField)->entity
      ->getFileUri());

    // Get the actual file name and replace it with _cut.
    $fileName = pathinfo($realPath, PATHINFO_FILENAME);
    $newFile = str_replace($fileName, $fileName . '_cut', $entity
      ->get($baseField)->entity->getFileUri()) . '.jpg';

    $tmpName = "";
    foreach ($values as $keys) {
      $tmpNames = [];
      foreach ($keys as $key) {
        // Generate double files, but we only need the last one.
        $tmpName = $this->fileSystem->tempnam($this->tmpDir, 'video') . '.jpg';
        $tmpNames[] = $tmpName;

        $inputVideo = $this->video ?? $realPath;

        // Clean the values.
        $timeStamp = $this->cleanTimestamp($key['timestamp']);
        $tokens = [
          'inputFile' => $inputVideo,
          'timeStamp' => $timeStamp,
          'outputFile' => $tmpName,
        ];
        $command = '-y -nostdin -i {inputFile} -ss {timeStamp} -frames:v 1 {outputFile}';
        $this->runFfmpegCommand($command, $tokens, 'Could not generate new images.');
      }

      // Move the file to the correct place.
      $fixedFile = $this->fileSystem->copy($tmpName, $newFile, FileExists::Rename);

      // Generate the new file entity.
      $file = File::create([
        'uri' => $fixedFile,
        'status' => 1,
        'uid' => $this->currentUser->id(),
      ]);
      $file->save();

      $files[] = ['target_id' => $file->id()];
    }

    // Then set the value.
    $entity->set($fieldDefinition->getName(), $files);
    return TRUE;
  }

}
