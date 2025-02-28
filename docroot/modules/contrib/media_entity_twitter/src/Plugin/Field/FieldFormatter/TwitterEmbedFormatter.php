<?php

namespace Drupal\media_entity_twitter\Plugin\Field\FieldFormatter;

use Drupal\Core\Field\FieldItemInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\FormatterBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\media_entity_twitter\Plugin\media\Source\Twitter;

/**
 * Plugin implementation of the 'twitter_embed' formatter.
 *
 * @FieldFormatter(
 *   id = "twitter_embed",
 *   label = @Translation("Twitter embed"),
 *   field_types = {
 *     "link", "string", "string_long"
 *   }
 * )
 */
class TwitterEmbedFormatter extends FormatterBase {

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings() {
    $settings['conversation'] = FALSE;

    return $settings;
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state) {
    $form = parent::settingsForm($form, $form_state);

    $form['conversation'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Show previous tweet?'),
      '#description' => $this->t('If the requested tweet was a reply to another one, this option will display a summary of the previous tweet too. By default only the requested tweet will be displayed.'),
      '#default_value' => $this->getSetting('conversation'),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function settingsSummary() {
    $summary = [];

    if ($this->getSetting('conversation')) {
      $summary[] = $this->t('Show previous tweet, if applicable.');
    }
    else {
      $summary[] = $this->t('Do not show previous tweet, if applicable.');
    }

    return $summary;
  }

  /**
   * Extracts the embed code from a field item.
   *
   * @param \Drupal\Core\Field\FieldItemInterface $item
   *   The field item.
   *
   * @return string|null
   *   The embed code, or NULL if the field type is not supported.
   */
  protected function getEmbedCode(FieldItemInterface $item) {
    switch ($item->getFieldDefinition()->getType()) {
      case 'link':
        return $item->uri;

      case 'string':
      case 'string_long':
        return $item->value;

      default:
        break;
    }
  }

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode) {
    $element = [];
    foreach ($items as $delta => $item) {
      $matches = [];

      foreach (Twitter::$validationRegexp as $pattern => $key) {
        if (preg_match($pattern, $this->getEmbedCode($item), $item_matches)) {
          $matches[] = $item_matches;
        }
      }

      if (!empty($matches)) {
        $matches = reset($matches);
      }

      if (!empty($matches['user']) && !empty($matches['id'])) {
        $element[$delta] = [
          '#theme' => 'media_entity_twitter_tweet',
          '#path' => 'https://twitter.com/' . $matches['user'] . '/statuses/' . $matches['id'],
          '#attributes' => [
            'class' => ['twitter-tweet', 'element-hidden'],
            'lang' => 'en',
          ],
        ];

        // If the option was not selected to show the conversation, then pass
        // the API option to disable the conversation.
        // @see https://developer.twitter.com/en/docs/twitter-for-websites/embedded-tweets/overview
        if (!$this->getSetting('conversation')) {
          $element[$delta]['#attributes']['data-conversation'] = 'none';
        }
      }
    }

    if (!empty($element)) {
      $element['#attached'] = [
        'library' => [
          'media_entity_twitter/integration',
        ],
      ];
    }

    return $element;
  }

}
