<?php

namespace Drupal\ai_automators\Plugin\AiAutomatorType;

use Drupal\ai\Utility\Textarea;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\ai\OperationType\Chat\ChatInput;
use Drupal\ai\OperationType\Chat\ChatMessage;
use Drupal\ai_automators\Attribute\AiAutomatorType;
use Drupal\ai_automators\PluginBaseClasses\VideoToText;
use Drupal\ai_automators\PluginInterfaces\AiAutomatorTypeInterface;
use Drupal\file\Entity\File;

/**
 * The rules for a text_long field.
 */
#[AiAutomatorType(
  id: 'llm_video_to_video',
  label: new TranslatableMarkup('LLM: Video To Video (Experimental)'),
  field_rule: 'file',
  target: 'file',
)]
class LlmVideoToVideo extends VideoToText implements AiAutomatorTypeInterface {

  /**
   * {@inheritDoc}
   */
  public $title = 'LLM: Video To Video (Experimental)';

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
  public function extraFormFields(ContentEntityInterface $entity, FieldDefinitionInterface $fieldDefinition, FormStateInterface $form_state, array $defaultValues = []) {
    $form['automator_cutting_prompt'] = [
      '#type' => 'textarea',
      '#title' => 'Cutting Prompt',
      '#description' => $this->t('Any commands that you need to give to cut out the video(s). Specify if you want the video(s) to be mixed together in one video if you only want one video out. Can use Tokens if Token module is installed.'),
      '#attributes' => [
        'placeholder' => $this->t('Cut out all the videos where they are saying "Hello". Mix together in one video.'),
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
          $prompt = "The following images shows rasters of scenes from a video together with a timestamp when it happens in the video. The audio is transcribed below. Please follow the instructions below with the video as context, using images and transcripts and try to figure out what sections the person wants to cut out. Unless the persons specifices that they want the video mixed together in one video, give back multiple timestamps if needed. If the don't want it mixed, give back multiple values with just one start time and end time.\n\n";
          $prompt .= "Instructions:\n----------------------------\n" . $cutPrompt . "\n----------------------------\n\n";
          $prompt .= "Transcription:\n----------------------------\n" . $this->transcription . "\n----------------------------\n\n";
          $prompt .= "\n\nDo not include any explanations, only provide a RFC8259 compliant JSON response following this format without deviation.\n[{\"value\": [{\"start_time\": \"The start time of the cut in format h:i:s.ms\", \"end_time\": \"The end time of the cut in format h:i:s.ms\"}]].";
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
    // Should have start and end time.
    if (!is_array($value) && !isset($value[0]['start_time']) && !isset($value[0]['end_time'])) {
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
    $baseField = $automatorConfig['base_field'];
    $realPath = $this->fileSystem->realpath($entity->get($baseField)->entity->getFileUri());
    // Get the actual file name and replace it with _cut.
    $fileName = pathinfo($realPath, PATHINFO_FILENAME);
    $newFile = str_replace($fileName, $fileName . '_cut', $entity
      ->get($baseField)->entity->getFileUri());

    foreach ($values as $keys) {
      $tmpNames = [];
      foreach ($keys as $key) {
        // Generate double files, but we only need the last one.
        $tmpName = $this->fileSystem->tempnam($this->tmpDir, 'video') . '.mp4';
        $tmpNames[] = $tmpName;

        $tokens = [
          'startTime' => $this->cleanTimestamp($key['start_time']),
          'endTime' => $this->cleanTimestamp($key['end_time']),
          'realPath' => $realPath,
          'tmpName' => $tmpName,
        ];
        $command = "-y -nostdin -i {realPath} -ss {startTime} -to {endTime} -c:v libx264 -c:a aac -strict -2 {tmpName}";
        $this->runFfmpegCommand($command, $tokens, 'Could not cut out the video.');
      }

      // If we only have one video, we can just rename it.
      if (count($tmpNames) == 1) {
        $endFile = $tmpNames[0];
      }
      else {
        // If we have more than one video, we need to mix them together.
        $endFile = $this->fileSystem->tempnam($this->tmpDir, 'video') . '.mp4';
        // Generate list file.
        $text = '';
        foreach ($tmpNames as $tmpName) {
          $text .= "file $tmpName\n";
        }
        file_put_contents($this->tmpDir . 'list.txt', $text);
        $tokens = [
          'listFile' => $this->tmpDir . 'list.txt',
          'endFile' => $endFile,
        ];
        $command = "-y -nostdin -f concat -safe 0 -i {listFile} -c:v libx264 -c:a aac -strict -2 {endFile}";
        $this->runFfmpegCommand($command, $tokens, 'Could not mix the videos together.');
      }
      // Move the file to the correct place.
      $fixedFile = $this->fileSystem->move($endFile, $newFile);

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
