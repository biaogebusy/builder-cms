<?php

namespace Drupal\ai_api_explorer\Hook;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Link;
use Drupal\Core\Render\Element;
use Drupal\Core\StringTranslation\StringTranslationTrait;

/**
 * Contains hooks for ai_api_explorer module.
 */
class AiApiExplorer {

  use StringTranslationTrait;

  /**
   * Implements hook_form_FORM_ID_alter().
   */
  #[Hook('form_ai_tools_library_select_form_alter')]
  public function aiToolsLibrarySelectFormAlter(&$form, FormStateInterface $form_state, $form_id) {
    if (!empty($form['tools'])) {
      foreach (Element::getVisibleChildren($form['tools']) as $id) {
        $tool = $form['tools'][$id];
        if (empty($tool['#return_value'])) {
          continue;
        }
        $plugin_id = $tool['#return_value'];
        $description = '[' . Link::createFromRoute($this->t('Test this tool'), 'ai_api_explorer.form.tools_explorer', [], [
          'query' => [
            'tool' => $plugin_id,
          ],
          'attributes' => [
            'target' => '_blank',
          ],
        ])->toString() . ']<br>';
        $form['tools'][$id]['#suffix'] = '<p class="option">' . $description . '</p></div>';
      }
    }
  }

}
