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

/**
 * The rules for a video to image field.
 */
#[AiAutomatorType(
  id: 'llm_video_to_image',
  label: new TranslatableMarkup('LLM: Video To HTML (Experimental)'),
  field_rule: 'text_long',
  target: '',
)]
class LlmVideoToHtml extends VideoToText implements AiAutomatorTypeInterface {

  /**
   * {@inheritDoc}
   */
  public $title = 'LLM: Video To HTML (Experimental)';

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
  public function extraFormFields(ContentEntityInterface $entity, FieldDefinitionInterface $fieldDefinition, FormStateInterface $formState, array $defaultValues = []) {
    $form['automator_generating_prompt'] = [
      '#type' => 'textarea',
      '#title' => 'Generating Prompt',
      '#description' => $this->t('Any commands that you need in how to generate the HTML. How many images you wantm what kind of language you want, which HTML tags to use and where etc.'),
      '#attributes' => [
        'placeholder' => $this->t('Cut out between 4-6 images. Write 4 sections starting with a h2 header and between 2-4 paragraphs per section. You can use the following other tags: strong, a, quote, pre and em.'),
      ],
      '#default_value' => $defaultValues['automator_generating_prompt'] ?? '',
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
      $form['automator_generating_prompt_help'] = \Drupal::service('token.tree_builder')->buildRenderable([
        $this->getEntityTokenType($entity->getEntityTypeId()),
        'current-user',
      ]);
      $form['automator_generating_prompt_help']['#weight'] = 25;
    }
    return $form;
  }

  /**
   * {@inheritDoc}
   */
  public function generate(ContentEntityInterface $entity, FieldDefinitionInterface $fieldDefinition, array $automatorConfig) {
    // Tokenize prompt.
    $cutPrompt = $this->renderTokenPrompt($automatorConfig['generating_prompt'], $entity);

    $total = [];
    foreach ($entity->get($automatorConfig['base_field']) as $entityWrapper) {
      if ($entityWrapper->entity) {
        $fileEntity = $entityWrapper->entity;
        if (in_array($fileEntity->getMimeType(), [
          'video/mp4',
        ])) {
          $this->prepareToExplain($automatorConfig, $entityWrapper->entity);
          $prompt = "The following images shows rasters of scenes from a video together with a timestamp when it happens in the video. The audio is transcribed below.\n";
          $prompt .= "Please follow the instructions below with the video as context, using images and transcripts and generate a HTML section for ckeditor, but only from the body and forward, so no html, body or head is needed. Also don't use footer or header tags.\n";
          $prompt .= "If some powerful quote can be extracted from transcript or visuals, put it in blockquote unless otherwise instructed.\n";
          $prompt .= "Unless otherwise prompted in the instructions, choose some screenshots to incorporate into the video, add the src as timestamp, you may add width and heigh attributes if you want to inline the image, then you can also use the data attribute 'data-align' with either left or right.";
          $prompt .= "If you align, do this before the start of a new full part/section/paragraph. If you use width and height, link to the timestamp. You may also add a caption to it if you want, but it is not necessary, you the use the data attribute data-caption.\n";
          $prompt .= "You may also crop/edit the image if only a specific part of it makes sense to show up in the image, for instance just getting the presentation when it a presentation an person on stage, you can use the data attribute data-crop for that and give back the x,y,height and width comma separated or leave it empty, when no cut is needed. Always make it the last attribute of the img tag.\n";
          $prompt .= "Always end the image tag with a space and forward slash, even if it is a full tag, so it is self-closing.\n";
          $prompt .= "So, add them in the following format '<img src=\"{{ timestamp in format h:i:s.ms }}\" alt=\"{{ some description based on the image }}\" data-crop=\"\" />', so an example for full width being '<img src=\"00:01:14.165\" alt=\"A blue bird\" data-crop=\"\" />' and aligned being '<a href=\"00:02:01.432\"><img src=\"00:02:01.432\" alt=\"People talking\" width=\"380\" data-align=\"left\" data-crop=\"\" /></a>'. ";
          $prompt .= "An example of a cropped image with a caption would be '<a href=\"00:02:01.432\"><img src=\"00:02:01.432\" alt=\"People talking\" data-caption=\"A few people talking to eachother.\"  data-crop=\"0,0,360,240\" /></a>'. ";
          $prompt .= "Do not link to any webpage, unless there is a written webpage in the visuals or they specifically talk about a full html page. If there is a link somewhere, try to incorporate the link in the outputted HTML, unless otherwise instructed.\n\n";
          $prompt .= "Instructions:\n----------------------------\n" . $cutPrompt . "\n----------------------------\n\n";
          $prompt .= "Transcription:\n----------------------------\n" . $this->transcription . "\n----------------------------\n\n";
          $prompt .= "\n\nDo not include any explanations, only provide a RFC8259 compliant JSON response following this format without deviation.\n[{\"value\": \"One full blob of the HTML\"}].";

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

    // Create a tmp directory.
    $this->createTempDirectory();
    // Get the text format.
    $config = $fieldDefinition->getConfig($entity->bundle());
    $format = $config->getSettings()['allowed_formats'][0] ?? '';

    $newValues = [];
    $baseField = $automatorConfig['base_field'] ?? '';
    // Get the texts.
    foreach ($values as $value) {
      preg_match_all('/<img src="([^"]+)"(.*)data-crop="(.*)" \/>/', $value, $matches);
      foreach ($matches[1] as $i => $match) {
        $cropData = [];
        if (!empty($matches[3][$i])) {
          $parts = explode(',', $matches[3][$i]);
          if (count($parts) == 4) {
            $cropData = $parts;
          }
        }
        $screenShot = $this->screenshotFromTimestamp($entity->get($baseField)->entity, $match, $cropData);
        $value = str_replace($match, $screenShot->createFileUrl(TRUE), $value);
        $value = preg_replace('/data-crop="(.*)"/', '', $value);
      }
      $newValues = [
        'value' => $value,
        'format' => $format,
      ];
    }
    // Then set the value.
    $entity->set($fieldDefinition->getName(), $newValues);
    return TRUE;
  }

}
