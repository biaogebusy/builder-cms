<?php

namespace Drupal\viewsreference\Plugin\Field\FieldFormatter;

use Drupal\Core\Entity\RevisionableInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\FormatterBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Security\TrustedCallbackInterface;
use Drupal\views\ContextualLinksHelper;
use Drupal\views\ViewExecutable;
use Drupal\views\Views;

/**
 * Field formatter for Viewsreference Field.
 *
 * @FieldFormatter(
 *   id = "viewsreference_lazy_formatter",
 *   label = @Translation("Views reference (lazy builder)"),
 *   field_types = {"viewsreference"}
 * )
 */
class ViewsReferenceLazyFieldFormatter extends FormatterBase implements TrustedCallbackInterface {

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings() {
    $options = parent::defaultSettings();
    $options['plugin_types'] = ['block'];
    return $options;
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state) {
    $form = parent::settingsForm($form, $form_state);
    $types = Views::pluginList();
    $options = [];
    foreach ($types as $key => $type) {
      if ('display' === $type['type']) {
        $options[str_replace('display:', '', $key)] = $type['title']->render();
      }
    }
    $form['plugin_types'] = [
      '#type' => 'checkboxes',
      '#options' => $options,
      '#title' => $this->t('View display plugins to allow'),
      '#default_value' => $this->getSetting('plugin_types'),
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function settingsSummary() {
    $summary = [];
    $allowed = [];
    $settings = $this->getSettings();
    foreach ($settings['plugin_types'] as $type) {
      if ($type) {
        $allowed[] = $type;
      }
    }
    $summary[] = $this->t('Allowed plugins: @view', ['@view' => implode(', ', $allowed)]);
    return $summary;
  }

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode) {
    $elements = [];

    // Get the information for the parent entity and field to allow
    // customizing the view / view results.
    $parent_entity_type = $items->getEntity()->getEntityTypeId();
    $parent_entity_id = $items->getEntity()->id();
    $parent_field_name = $items->getFieldDefinition()->getName();
    $parent_revision_id = NULL;
    if ($items->getEntity() instanceof RevisionableInterface) {
      $parent_revision_id = $items->getEntity()->getRevisionId();
    }

    foreach ($items as $delta => $item) {
      $view_name = $item->getValue()['target_id'];
      $display_id = $item->getValue()['display_id'] ?? '';
      // When creating a node with JavaScript disabled, for example in layout
      // builder context, the form is multistep so display ID may not have been
      // selected.
      if (!$display_id) {
        continue;
      }
      $enabled_settings = array_filter($this->getFieldSetting('enabled_settings') ?? []);
      $elements[$delta] = [
        '#lazy_builder' => [
          static::class . '::lazyBuilder',
          [
            $view_name,
            $display_id,
            $item->getValue()['data'] ?? '',
            serialize($enabled_settings),
            !empty(array_filter($this->getSetting('plugin_types'))),
            $parent_entity_type,
            (string) $parent_entity_id,
            $parent_field_name,
            $parent_revision_id,
            $delta,
            $langcode,
          ],
        ],
        '#create_placeholder' => TRUE,
        '#lazy_builder_preview' => [
          '#theme' => 'viewsreference__lazy_builder_loading',
        ],
      ];
    }
    return $elements;
  }

  /**
   * {@inheritdoc}
   */
  public static function trustedCallbacks() {
    return ['lazyBuilder'];
  }

  /**
   * Lazy builder callback.
   *
   * @param string $view_name
   *   The view name.
   * @param string $display_id
   *   The display ID.
   * @param string $data
   *   A serialized string containing the settings data.
   * @param string $enabled_settings
   *   A serialized string containing the enabled settings.
   * @param bool $plugin_types
   *   Whether plugin types were enabled.
   * @param string|null $parent_entity_type
   *   The parent entity type.
   * @param string|null $parent_entity_id
   *   The parent entity ID.
   * @param string|null $parent_field_name
   *   The parent field name.
   * @param string|null $parent_revision_id
   *   The parent revision ID.
   * @param int|null $delta
   *   The field item delta.
   * @param string|null $parent_entity_langcode
   *   The langcode the parent entity was rendered in.
   */
  public static function lazyBuilder(string $view_name, string $display_id, string $data, string $enabled_settings, bool $plugin_types, ?string $parent_entity_type, ?string $parent_entity_id, ?string $parent_field_name, ?string $parent_revision_id, ?int $delta, ?string $parent_entity_langcode = NULL): array {
    // Double-check that a display ID has been selected.
    if (!$display_id) {
      return [];
    }

    $unserialized_data = !empty($data) ? unserialize($data, ['allowed_classes' => FALSE]) : [];
    $unserialized_enabled_settings = !empty($enabled_settings) ? unserialize($enabled_settings, ['allowed_classes' => FALSE]) : [];
    $unserialized_enabled_settings = array_filter($unserialized_enabled_settings);
    $view = Views::getView($view_name);

    // Add an extra check because the view could have been deleted.
    if (!$view instanceof ViewExecutable) {
      return [];
    }

    $view->setDisplay($display_id);
    // Add properties to the view so our hook_views_pre_build() implementation
    // can alter the view. This is pretty hacky, but we need this to fix ajax
    // behaviour in views. The hook_views_pre_build() needs to know if the
    // view was part of a viewsreference field or not.
    $view->element['#viewsreference'] = [
      'data' => $unserialized_data,
      'enabled_settings' => $unserialized_enabled_settings,
      'parent_entity_type' => $parent_entity_type,
      'parent_entity_id' => $parent_entity_id,
      'parent_entity_langcode' => $parent_entity_langcode,
      'parent_field_name' => $parent_field_name,
      'parent_revision_id' => $parent_revision_id,
      'field_item_delta' => $delta,
    ];

    $view->preExecute();
    $view->execute($display_id);

    $render_array = $view->buildRenderable($display_id, $view->args, FALSE);
    if (!empty($view->result) || !empty($view->empty)) {
      if ($plugin_types) {
        // Add a custom template if the title is available.
        $title = $view->getTitle();
        if (!empty($title) && !empty($unserialized_enabled_settings['title'])) {
          // If the title contains tokens, we need to render the view to
          // populate the rowTokens.
          if (mb_strpos($title, '{{') !== FALSE) {
            $view->render();
            $title = $view->getTitle();
          }
          $render_array['title'] = [
            '#theme' => 'viewsreference__view_title',
            '#title' => $title,
          ];
        }
      }

      // ContextualLinksHelper::addLinks() needs the following information
      // in the render array in order to attach the contextual links to
      // the view.
      $render_array['#view_id'] = $view->storage->id();
      $render_array['#view_display_show_admin_links'] = $view->getShowAdminLinks();
      $render_array['#view_display_plugin_id'] = $view->getDisplay()->getPluginId();
      $plugin_id = $render_array['#view_display_plugin_id'];
      $location = in_array($plugin_id, ['block', 'page', 'view']) ? $plugin_id : 'view';
      if (\Drupal::hasService(ContextualLinksHelper::class)) {
        \Drupal::service(ContextualLinksHelper::class)->addLinks($render_array, $location, $display_id);
      }
    }

    // #lazy_builder can't return elements with #type, so we need to add a
    // wrapper.
    return [$render_array];
  }

}
