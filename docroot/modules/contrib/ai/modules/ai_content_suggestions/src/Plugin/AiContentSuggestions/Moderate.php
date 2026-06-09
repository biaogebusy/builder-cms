<?php

declare(strict_types=1);

namespace Drupal\ai_content_suggestions\Plugin\AiContentSuggestions;

use Drupal\Component\Utility\Unicode;
use Drupal\Core\Form\FormStateInterface;
use Drupal\ai_content_suggestions\AiContentSuggestionsPluginBase;

/**
 * Plugin implementation of the ai_content_suggestions.
 *
 * @AiContentSuggestions(
 *   id = "moderate",
 *   label = @Translation("Moderate text"),
 *   description = @Translation("Allow an LLM to provide moderation suggestions about the content."),
 *   operation_type = "moderation"
 * )
 */
final class Moderate extends AiContentSuggestionsPluginBase {

  /**
   * {@inheritdoc}
   */
  public function alterForm(array &$form, FormStateInterface $form_state, array $fields): void {
    $form[$this->getPluginId()] = $this->getAlterFormTemplate($fields);
    $form[$this->getPluginId()]['response']['response']['#context']['response']['description'] = [
      '#type' => 'html_tag',
      '#tag' => 'p',
      '#value' => $this->t('AI can analyze content and tell you what content policies it may violate for a provider. This is beneficial if your audience are certain demographics and sensitive to certain categories. Note that this is only a useful guide.'),
      '#weight' => 1,
    ];
    $form[$this->getPluginId()][$this->getPluginId() . '_submit']['#value'] = $this->t('Analyze');
  }

  /**
   * {@inheritdoc}
   */
  public function updateFormWithResponse(array &$form, FormStateInterface $form_state): void {
    if ($value = $this->getTargetFieldValue($form_state)) {
      $form[$this->getPluginId()]['response']['response']['#context']['response']['results'] = [
        '#theme' => 'item_list',
        '#title' => $this->t('The content violated the listed policies.'),
        '#list_type' => 'ul',
        '#items' => [],
        '#empty' => $this->t('The text does not violate any content policies noted by the LLM.'),
        '#weight' => 100,
      ];

      $provider_config = $this->getSetProvider($this->operationType(), $this->config->get('plugins')[$this->getPluginId()]);
      $ai_provider = $provider_config['provider_id'];

      /** @var \Drupal\ai\OperationType\Moderation\ModerationResponse $response */
      try {
        $response = $ai_provider->moderation($value, $provider_config['model_id'])->getNormalized();

        if ($response->isFlagged()) {
          foreach ($response->getInformation() as $category => $did_violate) {
            $form[$this->getPluginId()]['response']['response']['#context']['response']['results']['#items'][] = Unicode::ucfirst($category);
          }
        }
      }
      catch (\Exception $e) {
        $form[$this->getPluginId()]['response']['response']['#context']['response']['error'] = [
          '#type' => 'html_tag',
          '#tag' => 'p',
          '#value' => $this->t('There was an error obtaining a response from the LLM.'),
        ];
      }
    }
    else {
      $form[$this->getPluginId()]['response']['response']['#context']['response']['description']['#value'] = $this->t('The selected field has no text. Please supply content to the field.');
    }
  }

}
