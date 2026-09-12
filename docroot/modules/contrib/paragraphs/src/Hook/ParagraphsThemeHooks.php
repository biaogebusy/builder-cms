<?php

namespace Drupal\paragraphs\Hook;

use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Logger\LoggerChannelTrait;
use Drupal\Core\Render\Element;
use Drupal\paragraphs\Entity\ParagraphsType;

class ParagraphsThemeHooks {

  use LoggerChannelTrait;

  /**
   * Implements hook_theme().
   */
  #[Hook('theme')]
  public static function theme() {
    return [
      'paragraph' => [
        'render element' => 'elements',
        'initial preprocess' => static::class . ':preprocessParagraph',
      ],
      'paragraphs_dropbutton_wrapper' => [
        'variables' => [
          'children' => NULL,
        ],
      ],
      'paragraphs_info_icon' => [
        'variables' => [
          'message' => NULL,
          'icon' => NULL,
        ],
      ],
      'paragraphs_add_dialog' => [
        'render element' => 'element',
        'template' => 'paragraphs-add-dialog',
        'initial preprocess' => static::class . ':preprocessParagraphsAddDialog',
      ],
      'paragraphs_actions' => [
        'render element' => 'element',
        'template' => 'paragraphs-actions',
        'initial preprocess' => static::class . ':preprocessParagraphsActions',
      ],
      'paragraphs_summary' => [
        'render element' => 'element',
        'template' => 'paragraphs-summary',
        'initial preprocess' => static::class . ':preprocessParagraphsSummary',
      ],
    ];
  }

  /**
   * Prepares variables for paragraph templates.
   *
   * Default template: paragraph.html.twig.
   *
   * Most themes use their own copy of paragraph.html.twig. The default is located
   * inside "templates/paragraph.html.twig". Look in there for the
   * full list of variables.
   *
   * @param array $variables
   *   An associative array containing:
   *   - elements: An array of elements to display in view mode.
   *   - paragraph: The paragraph object.
   *   - view_mode: View mode; e.g., 'full', 'teaser'...
   */
  public function preprocessParagraph(array &$variables): void {
    $variables['view_mode'] = $variables['elements']['#view_mode'];
    $variables['paragraph'] = $variables['elements']['#paragraph'];

    // Helpful $content variable for templates.
    $variables += ['content' => []];
    foreach (Element::children($variables['elements']) as $key) {
      $variables['content'][$key] = $variables['elements'][$key];
    }

    $paragraph_type = $variables['elements']['#paragraph']->getParagraphType();
    if (!$paragraph_type) {
      $this->getLogger('paragraphs')
        ->critical(t('Unable to load paragraph type %type when displaying paragraph %id'), [
          '%type' => $variables['elements']['#paragraph']->bundle(),
          '%id' => $variables['elements']['#paragraph']->id(),
        ]);
      $paragraph_type = ParagraphsType::create(['id' => $variables['elements']['#paragraph']->bundle()]);
    }
    foreach ($paragraph_type->getEnabledBehaviorPlugins() as $plugin_value) {
      $plugin_value->preprocess($variables);
    }

  }

  /**
   * Prepares variables for modal form add widget template.
   *
   * Default template: paragraphs-add-dialog.html.twig.
   *
   * @param array $variables
   *   An associative array containing:
   *   - buttons: An array of buttons to display in the modal form.
   */
  public function preprocessParagraphsAddDialog(array &$variables): void {
    // Define variables for the template.
    $variables += ['buttons' => []];
    foreach (Element::children($variables['element']) as $key) {
      if ($key == 'add_modal_form_area') {
        // $add variable for the add button.
        $variables['add'] = $variables['element'][$key];
      }
      elseif ($key == 'add_more_delta') {
        // Add the delta to the add wrapper.
        $variables['add'][$key] = $variables['element'][$key];
      }
      else {
        // Buttons for the paragraph types in the modal form.
        $variables['buttons'][$key] = $variables['element'][$key];
      }
    }
  }

  /**
   * Prepares variables for paragraphs_actions component.
   *
   * Default template: paragraphs-actions.html.twig.
   *
   * @param array $variables
   *   An associative array containing:
   *   - actions: An array of default action buttons.
   *   - dropdown_actions: An array of buttons for dropdown.
   */
  public function preprocessParagraphsActions(array &$variables): void {
    // Define variables for the template.
    $variables += ['actions' => [], 'dropdown_actions' => []];

    $element = $variables['element'];

    if (!empty($element['actions'])) {
      $variables['actions'] = $element['actions'];
    }

    if (!empty($element['dropdown_actions'])) {
      $variables['dropdown_actions'] = $element['dropdown_actions'];
    }
  }

  /**
   * Prepares variables for.
   *
   * Default template: paragraphs-summary.html.twig.
   *
   * @param array $variables
   *   An associative array containing:
   *   - buttons: An array of buttons to display in the modal form.
   */
  public function preprocessParagraphsSummary(array &$variables): void {
    $variables['content'] = $variables['element']['#summary']['content'];
    $variables['behaviors'] = $variables['element']['#summary']['behaviors'];
    $variables['expanded'] = !empty($variables['element']['#expanded']);
  }

  /**
   * Implements hook_theme_suggestions_HOOK().
   */
  #[Hook('theme_suggestions_paragraph')]
  public static function themeSuggestionsParagraph(array $variables) {
    $suggestions = [];
    $paragraph = $variables['elements']['#paragraph'];
    $sanitized_view_mode = strtr($variables['elements']['#view_mode'], '.', '_');
    $suggestions[] = 'paragraph__' . $sanitized_view_mode;
    $suggestions[] = 'paragraph__' . $paragraph->bundle();
    $suggestions[] = 'paragraph__' . $paragraph->bundle() . '__' . $sanitized_view_mode;
    return $suggestions;
  }

  /**
   * Implements hook_theme_registry_alter().
   */
  #[Hook('theme_registry_alter')]
  public static function themeRegistryAlter(&$theme_registry) {
    // Force paragraphs_preprocess_field_multiple_value_form to run last.
    $key = array_search('paragraphs_preprocess_field_multiple_value_form', $theme_registry['field_multiple_value_form']['preprocess functions']);
    unset($theme_registry['field_multiple_value_form']['preprocess functions'][$key]);
    $theme_registry['field_multiple_value_form']['preprocess functions'][] = 'paragraphs_preprocess_field_multiple_value_form';
  }

}
