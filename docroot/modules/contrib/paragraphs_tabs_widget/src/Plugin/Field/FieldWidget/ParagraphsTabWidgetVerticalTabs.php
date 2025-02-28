<?php

namespace Drupal\paragraphs_tabs_widget\Plugin\Field\FieldWidget;

use Drupal\Component\Utility\Html;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Element;
use Drupal\paragraphs\Plugin\Field\FieldWidget\InlineParagraphsWidget;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Paragraphs vertical tab widget.
 *
 * @FieldWidget(
 *   id = "paragraphs_tabs_widget_vertical_tabs",
 *   label = @Translation("Vertical tabs"),
 *   description = @Translation("Displays each paragraph's widget in a vertical tab."),
 *   field_types = {
 *     "entity_reference_revisions"
 *   }
 * )
 */
class ParagraphsTabWidgetVerticalTabs extends InlineParagraphsWidget {

  /**
   * The currently-active user.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $currentUser;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->currentUser = $container->get('current_user');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public static function addMoreAjax(array $form, FormStateInterface $form_state) {
    $element = parent::addMoreAjax($form, $form_state);

    // By default, the form element adds a div.ajax-new-content around the
    // newest item to be added - but this breaks vertical tabs because the
    // details elements need to be children (i.e.: not descendants) of the
    // vertical tab element. Remove the wrapper and set a default vertical tab
    // instead.
    $newestDelta = $element['#max_delta'];
    unset($element[$newestDelta]['#prefix']);
    unset($element[$newestDelta]['#suffix']);
    $element['#default_tab'] = $element[$newestDelta]['#id'] ?? '';

    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings() {
    return [
      // Widget settings defined by this module.
      'summary_selector' => '',

      // Collapsing a paragraph inside a vertical tab hides useful controls
      // behind extra clicks, so set defaults under the assumption that a user
      // won't want to collapse paragraphs.
      'edit_mode' => 'open',
    ] + parent::defaultSettings();
  }

  /**
   * {@inheritdoc}
   */
  public function extractFormValues(FieldItemListInterface $items, array $form, FormStateInterface $form_state) {
    // Delete the "Active tab" form value that the vertical tab widget adds
    // before trying to extract the rest of the form values. This prevents the
    // widget from trying to extract paragraph sub-form data from the tab name
    // (which doesn't work).
    $field_name = $this->fieldDefinition->getName();
    $path = array_merge($form['#parents'], [$field_name]);
    $path[] = $field_name . '__active_tab';
    $form_state->unsetValue($path);

    return parent::extractFormValues($items, $form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function formMultipleElements(FieldItemListInterface $items, array &$form, FormStateInterface $form_state) {
    // Ask the base widget to render all the elements of the form in the way it
    // normally does get the field name, which will eventually become the
    // #group for all tabs in the widget.
    $widget = parent::formMultipleElements($items, $form, $form_state);
    $group = $widget['#field_name'];

    // Attach our library and the data it needs to complete its operation.
    $widget['#attached']['library'][] = 'paragraphs_tabs_widget/vertical_tabs';
    $widget['#attached']['drupalSettings']['paragraphs_tabs_widget_vertical_tabs'][$group]['summarySelector']
      = $this->getSetting('summary_selector');
    $widget['#attributes']['data-paragraphs-tabs-widget-group'] = $group;
    $widgetId = Html::getUniqueId($group);
    $widget['#prefix'] = '<div id="' . $widgetId . '" data-paragraphs-tabs-widget-group-wrapper="' . Html::escape($group) . '">';
    $widget['#suffix'] = '</div>';

    // Replace the default way of rendering the widget with a vertical tabs
    // widget.
    unset($widget['#theme']);
    $widget['#type'] = 'vertical_tabs';

    // Loop through each single-element form.
    foreach (Element::children($widget) as $delta) {
      if (is_numeric($delta)) {
        // Transform the single-element form into a details element, with a
        // title and a group so it can be placed into the correct vertical tabs
        // element.
        $widget[$delta]['#type'] = 'details';
        $widget[$delta]['#title'] = $this->getSetting('title') ?: $this->t('Paragraph');
        $widget[$delta]['#group'] = $group;

        // Add a data attribute so the paragraphs_tabs_widget/vertical_tabs
        // library can find the tab.
        $widget[$delta]['#attributes']['data-paragraph-tabs-widget-tab-group'] = $group;

        // The default prefix and suffix define an HTML tag wrapper, which
        // interferes with the core/drupal.vertical-tabs library JavaScript,
        // which requires tabs to be children (i.e.: not descendants) of the
        // vertical tabs widget.
        unset($widget[$delta]['#prefix']);
        unset($widget[$delta]['#suffix']);

        // Hide the paragraph title, since that's already in the tab.
        if (isset($widget[$delta]['top']['paragraph_type_title'])) {
          $widget[$delta]['top']['paragraph_type_title']['#printed'] = TRUE;
        }

        // Temporarily hide the weight element, as it doesn't display properly.
        if (isset($widget[$delta]['_weight'])) {
          $widget[$delta]['_weight']['#printed'] = TRUE;
        }

        // Modify the "Remove" button (and its follow-up "Confirm removal" and
        // "Restore" buttons) for the tab: tell AJAX to replace the whole field
        // wrapper - otherwise its wrapper selector doesn't refer to anything.
        $removeButtonNames = [
          'remove_button',
          'confirm_remove_button',
          'restore_button',
        ];
        foreach ($removeButtonNames as $removeButtonName) {
          if (isset($widget[$delta]['top']['links'][$removeButtonName]['#ajax']['wrapper'])) {
            $widget[$delta]['top']['links'][$removeButtonName]['#ajax']['wrapper'] = $widgetId;
          }
        }
      }
    }

    // Update the "add more" button for better compatibility with vertical tabs:
    // remove the default theme wrappers, remove the default "to field" suffix
    // (which ends up outside the wrapper and therefore at the top of every
    // tab), add an attribute to the add_more button so JavaScript can move it
    // into the vertical tabs menu, and loop through each "add more" button and
    // tell AJAX to replace the whole field wrapper - otherwise its wrapper
    // selector doesn't refer to anything.
    unset($widget['add_more']['#theme_wrappers']);
    unset($widget['add_more']['#suffix']);
    $widget['add_more']['#attributes']['data-paragraphs-tabs-widget-addmore-group'] = $group;
    foreach (Element::children($widget['add_more']) as $addMoreButtonKey) {
      if (isset($widget['add_more'][$addMoreButtonKey]['#ajax']['wrapper'])) {
        $widget['add_more'][$addMoreButtonKey]['#ajax']['wrapper'] = $widgetId;
      }
    }

    return $widget;
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state) {
    $elements = parent::settingsForm($form, $form_state);

    // Collapsing a paragraph inside a vertical tab hides useful controls behind
    // extra clicks, so hide controls under the assumption that a user won't
    // want to collapse paragraphs.
    $elements['edit_mode']['#access'] = FALSE;

    // A jQuery selector for an HTML element inside the tab whose value will be
    // used as the tab summary.
    // Warning: This selector will be evaluated by jQuery in the client's
    // browser and therefore has security implications.
    $elements['summary_selector'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Tab summary selector'),
      '#description' => $this->t("A <a href='@jquery_api_selectors'>jQuery selector</a> for an HTML element inside the tab whose value will be used as the tab summary. Warning: This selector will be evaluated by jQuery in the client's browser and therefore has security implications.", [
        '@jquery_api_selectors' => 'https://api.jquery.com/category/selectors/',
      ]),
      '#default_value' => $this->getSetting('summary_selector'),
      '#access' => $this->currentUser->hasPermission('change paragraphs_tabs_widget summary_selector'),
    ];

    return $elements;
  }

  /**
   * {@inheritdoc}
   */
  public function settingsSummary() {
    $summary = [];

    // Widget settings inherited from field.widget.settings.paragraphs.
    $summary[] = $this->t('Title: @title', ['@title' => $this->getSetting('title')]);
    $summary[] = $this->t('Plural title: @title_plural', [
      '@title_plural' => $this->getSetting('title_plural'),
    ]);
    switch ($this->getSetting('add_mode')) {
      case 'select':
      default:
        $add_mode = $this->t('Select list');
        break;

      case 'button':
        $add_mode = $this->t('Buttons');
        break;

      case 'dropdown':
        $add_mode = $this->t('Dropdown button');
        break;
    }
    $summary[] = $this->t('Add mode: @add_mode', ['@add_mode' => $add_mode]);
    $summary[] = $this->t('Form display mode: @form_display_mode', [
      '@form_display_mode' => $this->getSetting('form_display_mode'),
    ]);
    if ($this->getDefaultParagraphTypeLabelName() !== NULL) {
      $summary[] = $this->t('Default paragraph type: @default_paragraph_type', [
        '@default_paragraph_type' => $this->getDefaultParagraphTypeLabelName(),
      ]);
    }

    // Widget settings defined by this module.
    if (!empty($this->getSetting('summary_selector'))) {
      $summary[] = $this->t('Tab summary selector: @summary_selector', [
        '@summary_selector' => $this->getSetting('summary_selector'),
      ]);
    }

    return $summary;
  }

}
