<?php

namespace Drupal\entity_browser\Hook;

use Drupal\Core\Entity\Display\EntityViewDisplayInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Url;
use Drupal\Core\Render\Element;
use Drupal\Core\Entity\EntityForm;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Hook implementations for entity_browser.
 */
class EntityBrowserHooks {
  use StringTranslationTrait;

  /**
   * Constructs a new EntityBrowserHooks object.
   */
  public function __construct(
    protected RouteMatchInterface $routeMatch,
    protected RequestStack $requestStack,
  ) {}

  /**
   * Implements hook_help().
   */
  #[Hook('help')]
  public function help($route_name, RouteMatchInterface $arg) {
    switch ($route_name) {
      case 'help.page.entity_browser':
        $output = '';
        $output .= '<h3>' . $this->t('About') . '</h3>';
        $output .= '<p>' . $this->t('The Entity Browser module provides a generic entity browser/picker/selector. It can be used in any context where one needs to select a few entities and do something with them. For more information, see the online documentation for <a href=":entity_browser-documentation">Entity Browser</a>.', [
          ':entity_browser-documentation' => 'https://www.drupal.org/docs/8/modules/entity-browser',
        ]) . '</p>';
        $output .= '<h3>' . $this->t('Uses') . '</h3>';
        $output .= '<dl>';
        $output .= '<dt>' . $this->t('General') . '</dt>';
        $output .= '<dd>' . $this->t('Entity browser comes with an example module that can be used as a starting point.') . '</dd>';
        $output .= '<dt>' . $this->t('Example use cases') . '</dt>';
        $output .= '<dd>' . $this->t('Powerfull entity reference widget') . '</dd>';
        $output .= '<dd>' . $this->t('Embedding entities into wysiwyg') . '</dd>';
        $output .= '</dl>';
        return $output;
    }
  }

  /**
   * Implements hook_form_alter().
   */
  #[Hook('form_alter')]
  public function formAlter(&$form, FormStateInterface &$form_state) {
    $entity_browser_dialog_edit = $this->routeMatch->getRouteName();
    if ($entity_browser_dialog_edit == 'entity_browser.edit_form' && $form_state->getFormObject() instanceof EntityForm) {
      // Let's allow the save button only.
      foreach (Element::children($form['actions']) as $key) {
        $form['actions'][$key]['#access'] = $key == 'submit';
      }
      // Use Ajax.
      $form['actions']['submit']['#ajax'] = [
        'url' => Url::fromRoute('entity_browser.edit_form', [
          'entity_type' => $form_state->getFormObject()->getEntity()->getEntityTypeId(),
          'entity' => $form_state->getFormObject()->getEntity()->id(),
        ]),
        'options' => [
          'query' => [
            'details_id' => $this->requestStack->getCurrentRequest()->query->get('details_id'),
          ],
        ],
        'disable-refocus' => TRUE,
      ];
    }
    if ($form['#id'] === 'entity-browser-display-config-form') {
      // Put next and previous after other form elements.
      $form['actions']['#weight'] = 51;
    }
  }

  /**
   * Implements hook_form_BASE_FORM_ID_alter().
   */
  #[Hook('form_entity_embed_dialog_alter')]
  public function formEntityEmbedDialogAlter(&$form, FormStateInterface $form_state) {
    // Add contextual information to entity browser's widget context array.
    if (!empty($form['entity_browser']['#entity_browser'])) {
      $embed_button = $form_state->get('embed_button');
      $type_settings = $embed_button->getTypeSettings();
      // Cardinality is always 1 with entity embed.
      $context = [
        'embed_button_id' => $embed_button->id(),
        'cardinality' => 1,
      ];
      if (!empty($type_settings['entity_type'])) {
        $context['target_entity_type'] = $type_settings['entity_type'];
      }
      if (!empty($type_settings['bundles'])) {
        $context['target_bundles'] = $type_settings['bundles'];
      }
      $form['entity_browser']['#widget_context'] = $context;
    }
  }

  /**
   * Implements hook_form_FORM_ID_alter().
   */
  #[Hook('form_views_ui_add_handler_form_alter')]
  public function formViewsUiAddHandlerFormAlter(&$form, FormStateInterface $form_state) {
    // Hide 'entity_browser_bundle' views filter plugin for displays other than
    // entity_browser.
    $display_id = $form_state->get('display_id');
    $display_plugin = $form_state->get('view')->get('storage')->get('display')[$display_id]['display_plugin'];
    if ($display_plugin != 'entity_browser') {
      foreach ($form['options']['name']['#options'] as $key => $value) {
        if (strpos($key, 'entity_browser_bundle') !== FALSE) {
          unset($form['options']['name']['#options'][$key]);
        }
      }
    }
  }

  /**
   * Implements hook_entity_view_alter().
   */
  #[Hook('entity_view_alter')]
  public function entityViewAlter(array &$build, EntityInterface $entity, EntityViewDisplayInterface $display) {
    if (isset($build['#entity_browser_suppress_contextual'])) {
      unset($build['#contextual_links']);
    }
  }

}
