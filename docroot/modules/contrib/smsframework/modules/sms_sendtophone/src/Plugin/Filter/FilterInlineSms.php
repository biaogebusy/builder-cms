<?php

declare(strict_types = 1);

namespace Drupal\sms_sendtophone\Plugin\Filter;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Markup;
use Drupal\Core\Url;
use Drupal\filter\FilterProcessResult;
use Drupal\filter\Plugin\FilterBase;

/**
 * Provides a filter to align elements.
 *
 * @Filter(
 *   id = \Drupal\sms_sendtophone\Plugin\Filter\FilterInlineSms::PLUGIN_ID,
 *   title = @Translation("Inline SMS"),
 *   description = @Translation("Highlights text between <code>[sms][/sms]</code> tags and appends a 'send to phone' button."),
 *   type = Drupal\filter\Plugin\FilterInterface::TYPE_MARKUP_LANGUAGE,
 *   settings = {
 *     "display" = "icon",
 *     "display_text" = @Translation("Send to phone"),
 *     "default_icon" = 1,
 *     "custom_icon_path" = ""
 *   }
 * )
 */
class FilterInlineSms extends FilterBase {

  public const PLUGIN_ID = 'filter_inline_sms';

  /**
   * {@inheritdoc}
   */
  public function process($text, $langcode) {
    $matches = [];
    preg_match_all('/\[sms\](.*?)\[\/sms\]/i', $text, $matches, PREG_SET_ORDER);

    $type = ($this->settings['display'] == 'icon') ? 'icon' : 'text';
    foreach ($matches as $match) {
      $text = str_replace($match[0], (string) $this->theme($match[1], $type), $text);
    }
    return new FilterProcessResult($text);
  }

  /**
   * {@inheritdoc}
   */
  public function tips($long = FALSE) {
    return $this->t('Text between [sms][/sms] tags will be highlighted and appended with a "send to phone" button.');
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state) {
    $elements['display'] = [
      '#type' => 'radios',
      '#title' => t('Show link as'),
      '#description' => t('How to display the the "send to phone" link.'),
      '#options' => [
        'text' => t('Text'),
        'icon' => t('Icon'),
      ],
      '#default_value' => $this->settings['display'],
    ];

    $elements['display_text'] = [
      '#type' => 'textfield',
      '#title' => t('Text for link'),
      '#description' => t('If "Text" is selected above, the following text will be appended as a link.'),
      '#size' => 32,
      '#maxlength' => 32,
      '#default_value' => $this->settings['display_text'],
    ];

    $elements['default_icon'] = [
      '#type' => 'checkbox',
      '#title' => t('Use default icon'),
      '#description' => t('If "Icon" is selected above and this option is enabled, the default icon that came with the module will be used.'),
      '#default_value' => $this->settings['default_icon'],
    ];

    $elements['custom_icon_path'] = [
      '#type' => 'textfield',
      '#title' => t('Path to custom icon'),
      '#description' => t('Provide a path to a custom icon. This icon will be used if "Icon" is selected above and the "Use default icon" option is disabled.'),
      '#size' => 40,
      '#maxlength' => 255,
      '#default_value' => $this->settings['custom_icon_path'],
      '#field_prefix' => Url::fromRoute('<none>', [], ['absolute' => TRUE]),
    ];

    return $elements;
  }

  /**
   * Themes the message using a text link.
   */
  protected function theme($text, $type = 'icon') {
    /** @var \Drupal\Core\Extension\ExtensionPathResolver $extensionPathResolver */
    $extensionPathResolver = \Drupal::service('extension.path.resolver');
    switch ($type) {
      case 'text':
        $markup = '(' . $this->settings['display_text'] . ')';
        break;

      case 'icon':
      default:
        if (!isset($this->settings["default_icon"]) || $this->settings["default_icon"] == 1) {
          $icon_path = $extensionPathResolver->getPath('module', 'sms_sendtophone') . '/sms-send.gif';
        }
        else {
          $icon_path = $this->settings["custom_icon_path"];
        }

        $title = $this->t('Send the highlighted text via SMS.');
        $icon_path = base_path() . $icon_path;
        // @todo Figure out a better way to render the icon.
        $markup = Markup::create("<img src='$icon_path' alt='{$this->settings["display_text"]}' title='$title'/>");
        break;

    }
    $options = [
      'attributes' => [
        'title' => t('Send the highlighted text via SMS.'),
        'class' => 'sms-sendtophone',
      ],
      'query' => [
        'text' => urlencode($text),
      ],
      'html' => TRUE,
    ];
    $link = [
      '#type' => 'link',
      '#prefix' => '<span class="sms-sendtophone-inline">' . $text . '</span> ',
      '#title' => $markup,
      '#url' => Url::fromRoute('sms_sendtophone.page', ['type' => 'inline'], $options),
    ];
    return $this->renderer()->renderPlain($link);
  }

  /**
   * Encapsulates the renderer service for unit testing purposes.
   *
   * @return \Drupal\Core\Render\RendererInterface
   *   Returns the renderer service.
   */
  protected function renderer() {
    return \Drupal::service('renderer');
  }

}
