<?php

namespace Drupal\paragraphs_library\Hook;

use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\views\ViewExecutable;
use Drupal\paragraphs\Plugin\Field\FieldWidget\ParagraphsWidget;
use Drupal\Core\Entity\Entity\EntityViewDisplay;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\StringTranslation\StringTranslationTrait;

/**
 * Hook implementations for paragraphs_library.
 */
class ParagraphsLibraryHooks {
  use StringTranslationTrait;

  /**
   * Implements hook_modules_installed().
   */
  #[Hook('modules_installed')]
  public static function modulesInstalled($modules) {
    if (\Drupal::isConfigSyncing()) {
      return;
    }
    if (!empty(array_intersect([
      'content_moderation',
      'paragraphs_library',
    ], $modules))) {
      // If Content Moderation is being installed (or was installed when this
      // module is enabled), make sure the extra field content moderation adds is
      // not visible inside our EB widget (i.e. in the "summary" viewmode), to
      // prevent the core bug in 2940513/2945351/2930139.
      // @todo Remove this when 2948254 lands.
      if (\Drupal::moduleHandler()->moduleExists('content_moderation')) {
        EntityViewDisplay::load('paragraphs_library_item.paragraphs_library_item.summary')->removeComponent('content_moderation_control')->save();
      }
    }
  }

  /**
   * Implements hook_paragraphs_widget_actions_alter().
   */
  #[Hook('paragraphs_widget_actions_alter')]
  public function paragraphsWidgetActionsAlter(&$widget_actions, &$context) {
    // Do not register conversions if structure changes are disallowed.
    if (!$context['allow_reference_changes']) {
      return;
    }
    $field_definition = $context['items']->getFieldDefinition();
    $parents = $context['element']['#field_parents'];
    $field_name = $field_definition->getName();
    $widget_state = $context['widget'];
    $delta = $context['delta'];
    $id_prefix = implode('_', array_merge($parents, [
      $field_name,
      $delta,
    ]));
    /** @var \Drupal\Paragraphs\ParagraphInterface $paragraphs_entity */
    $paragraphs_entity = $context['paragraphs_entity'];
    // Don't allow conversion of items not marked as allowed for conversion.
    $allow_library_conversion = $paragraphs_entity->getParagraphType()->getThirdPartySetting('paragraphs_library', 'allow_library_conversion', FALSE);
    if ($paragraphs_entity->bundle() == 'from_library') {
      $widget_actions['dropdown_actions']['library_to_paragraph'] = [
        '#type' => 'submit',
        '#value' => $this->t('Unlink from library'),
        '#name' => strtr($id_prefix, '-', '_') . '_unlink_from_library',
        '#weight' => 503,
        '#submit' => [
          'paragraphs_library_library_item_unlink_submit',
        ],
        '#limit_validation_errors' => [
          array_merge($parents, [
            $field_name,
            'add_more',
          ]),
        ],
        '#delta' => $delta,
        '#ajax' => [
          'callback' => [
            ParagraphsWidget::class,
            'itemAjax',
          ],
          'wrapper' => $widget_state['ajax_wrapper_id'],
          'effect' => 'fade',
        ],
        '#attributes' => [
          'class' => [
            'button--small',
          ],
        ],
      ];
    }
    elseif ($allow_library_conversion) {
      // Convert non-library items only.
      $widget_actions['dropdown_actions']['promote_to_library'] = [
        '#value' => $this->t('Promote to library'),
        '#name' => $id_prefix . '_promote_to_library',
        '#weight' => 503,
        '#submit' => [
          'paragraphs_library_make_library_item_submit',
        ],
        '#limit_validation_errors' => [
          array_merge($parents, [
            $field_name,
            'promote_to_library',
          ]),
        ],
        '#delta' => $delta,
        '#ajax' => [
          'callback' => [
            ParagraphsWidget::class,
            'itemAjax',
          ],
          'wrapper' => $widget_state['ajax_wrapper_id'],
        ],
        '#access' => \Drupal::entityTypeManager()->getAccessControlHandler('paragraphs_library_item')->createAccess(),
        '#attributes' => [
          'class' => [
            'button--small',
          ],
        ],
      ];
    }
  }

  /**
   * Implements hook_preprocess_HOOK().
   */
  #[Hook('preprocess_paragraph')]
  public static function preprocessParagraph(&$variables) {
    if (isset($variables['paragraph']) && $variables['paragraph']->getType() == 'from_library' && $variables['paragraph']->hasField('field_reusable_paragraph') && $variables['paragraph']->field_reusable_paragraph->entity) {
      $library_item = $variables['paragraph']->field_reusable_paragraph->entity;
      // Only replace the content if access is allowed to the library. Access
      // cacheability metadata is already returned by the original widget in case
      // access is not allowed.
      if ($library_item->access('view') && isset($variables['elements']['field_reusable_paragraph'][0]['#view_mode'])) {
        $view_builder = \Drupal::entityTypeManager()->getViewBuilder('paragraphs_library_item');
        $library_item_render_array = $view_builder->view($library_item, $variables['elements']['field_reusable_paragraph'][0]['#view_mode']);
        // This will remove all fields other then field_reusable_paragraph.
        $build = $view_builder->build($library_item_render_array);
        if (!empty($build['paragraphs'])) {
          $variables['content'] = $build['paragraphs'];
        }
      }
    }
  }

  /**
   * Implements hook_views_pre_render().
   */
  #[Hook('views_pre_render')]
  public static function viewsPreRender(ViewExecutable $view) {
    if ($view->storage->id() == 'paragraphs_library' && isset($view->field['count']) && !\Drupal::currentUser()->hasPermission('access entity usage statistics')) {
      // Remove link to usage statistics if user doesn't have permission to view
      // it.
      $view->field['count']->options['alter']['make_link'] = FALSE;
    }
  }

  /**
   * Implements hook_form_FORM_ID_alter().
   */
  #[Hook('form_paragraphs_type_form_alter')]
  public function formParagraphsTypeFormAlter(&$form, FormStateInterface $form_state) {
    // Adds paragraph type grouping to the form.
    /** @var \Drupal\paragraphs\ParagraphsTypeInterface $paragraph_type */
    $paragraph_type = $form_state->getFormObject()->getEntity();
    if ($paragraph_type->id() != 'from_library') {
      $form['allow_library_conversion'] = [
        '#type' => 'checkbox',
        '#title' => $this->t('Allow promoting to library'),
        '#default_value' => $paragraph_type->getThirdPartySetting('paragraphs_library', 'allow_library_conversion', FALSE),
      ];
      $form['#entity_builders'][] = 'paragraphs_library_form_paragraphs_type_form_builder';
    }
  }

  /**
   * Implements hook_entity_bundle_field_info_alter().
   */
  #[Hook('entity_bundle_field_info_alter')]
  public static function entityBundleFieldInfoAlter(&$fields, EntityTypeInterface $entity_type, $bundle) {
    // Ensure that Paragraph fields that only allow certain Paragraphs types
    // cannot have this restriction bypassed by the use of library items.
    /** @var \Drupal\Core\Field\FieldDefinitionInterface[] $fields */
    foreach ($fields as $name => $definition) {
      if ($definition->getType() != 'entity_reference_revisions') {
        continue;
      }
      if ($definition->getSetting('target_type') != 'paragraph') {
        continue;
      }
      $fields[$name]->addConstraint('ParagraphsLibraryItemHasAllowedParagraphsType');
    }
  }

  /**
   * Implements hook_help().
   */
  #[Hook('help')]
  public function help($route_name, RouteMatchInterface $route_match) {
    if ($route_name === 'entity.paragraphs_library_item.version_history') {
      return '<p>' . $this->t('Revisions allow you to track differences between multiple versions of your content, and revert to older versions.') . '</p>';
    }
  }

}
