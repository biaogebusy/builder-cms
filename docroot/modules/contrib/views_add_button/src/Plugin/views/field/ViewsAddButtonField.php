<?php

namespace Drupal\views_add_button\Plugin\views\field;

use Drupal\Component\Utility\Xss;
use Drupal\views\Plugin\views\field\FieldPluginBase;
use Drupal\views\ResultRow;
use Drupal\views_add_button\Plugin\views\ViewsAddButtonTrait;
use Drupal\views_add_button\Plugin\views_add_button\ViewsAddButtonDefault;
use Drupal\views_add_button\ViewsAddButtonUtilities;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;

/**
 * Defines a views field plugin.
 *
 * @ingroup views_field_handlers
 *
 * @ViewsField("views_add_button_field")
 */
class ViewsAddButtonField extends FieldPluginBase {

  use ViewsAddButtonTrait;

  /**
   * {@inheritdoc}
   */
  public function query() {
    // Leave empty to avoid a query on this field.
  }

  /**
   * Define the available options.
   *
   * @return array
   *   Array of available options for views_add_button form.
   */
  protected function defineOptions() {
    $options = parent::defineOptions();
    $options['type'] = ['default' => 'node'];
    $options['render_plugin'] = ['default' => ''];
    $options['access_plugin'] = ['default' => ''];
    $options['context'] = ['default' => ''];
    $options['button_text'] = ['default' => ''];
    $options['button_classes'] = ['default' => ''];
    $options['button_attributes'] = ['default' => ''];
    $options['button_access_denied'] = ['default' => ['format' => NULL, 'value' => '']];
    $options['button_prefix'] = ['default' => ['format' => NULL, 'value' => '']];
    $options['button_suffix'] = ['default' => ['format' => NULL, 'value' => '']];
    $options['query_string'] = ['default' => ''];
    $options['destination'] = ['default' => TRUE];
    $options['tokenize'] = ['default' => FALSE, 'bool' => TRUE];
    $options['preserve_tags'] = ['default' => ''];
    return $options;
  }

  /**
   * Provide the options form.
   */
  public function buildOptionsForm(&$form, FormStateInterface $form_state) {
    parent::buildOptionsForm($form, $form_state);

    $form['type'] = [
      '#type' => 'select',
      '#title' => t('Entity Type'),
      '#options' => ViewsAddButtonUtilities::createEntityBundleList(),
      '#empty_option' => '- Select -',
      '#default_value' => $this->options['type'],
      '#weight' => -10,
      '#required' => TRUE,
    ];
    $form['render_plugin'] = [
      '#type' => 'select',
      '#title' => t('Custom Rendering Plugin'),
      '#description' => t('If you would like to specify a plugin to use for rendering, set it here. 
        Leave unset to use the entity default plugin (recommended).'),
      '#options' => ViewsAddButtonUtilities::createPluginList(),
      '#empty_option' => '- Select -',
      '#default_value' => $this->options['render_plugin'],
      '#weight' => -10,
    ];
    $form['access_plugin'] = [
      '#type' => 'select',
      '#title' => t('Custom Access Plugin'),
      '#description' => t('If you would like to specify an access plugin to use, set it here. 
        Leave unset to use the entity default plugin (recommended).'),
      '#options' => ViewsAddButtonUtilities::createPluginList(),
      '#empty_option' => '- Select -',
      '#default_value' => $this->options['access_plugin'],
      '#weight' => -10,
    ];
    $form['context'] = [
      '#type' => 'textfield',
      '#title' => t('Entity Context'),
      '#description' => t('Certain entities require a special context parameter. Set the context (or relevant 
      token) here. Check the help for the relevant Views Add Button module for further questions.'),
      '#default_value' => $this->options['context'],
      '#weight' => -9,
    ];
    $form['button_text'] = [
      '#type' => 'textfield',
      '#title' => t('Button Text for the add button'),
      '#description' => t('Leave empty for the default: "Add [entity_bundle]"'),
      '#default_value' => $this->options['button_text'],
      '#weight' => -7,
    ];
    $form['query_string'] = [
      '#type' => 'textfield',
      '#title' => t('Query string to append to the add link'),
      '#description' => t('Add the query string, without the "?" .'),
      '#default_value' => $this->options['query_string'],
      '#weight' => -6,
    ];
    $form['button_classes'] = [
      '#type' => 'textfield',
      '#title' => t('Button classes for the add link - usually "button" or "btn," with additional styling classes.'),
      '#default_value' => $this->options['button_classes'],
      '#weight' => -5,
    ];
    $form['button_attributes'] = [
      '#type' => 'textarea',
      '#title' => t('Additional Button Attributes'),
      '#description' => t('Add one attribute string per line, without quotes (i.e. name=views_add_button).'),
      '#default_value' => $this->options['button_attributes'],
      '#cols' => 60,
      '#rows' => 2,
      '#weight' => -4,
    ];
    $form['button_access_denied'] = [
      '#type' => 'text_format',
      '#title' => t('Access Denied HTML'),
      '#description' => t('HTML to inject if access is denied.'),
      '#cols' => 60,
      '#rows' => 2,
      '#weight' => -3,
      '#default_value' => $this->options['button_access_denied']['value'],
    ];
    $form['button_prefix'] = [
      '#type' => 'text_format',
      '#title' => t('Prefix HTML'),
      '#description' => t('HTML to inject before the button.'),
      '#cols' => 60,
      '#rows' => 2,
      '#weight' => -3,
      '#default_value' => $this->options['button_prefix']['value'],
    ];
    $form['button_suffix'] = [
      '#type' => 'text_format',
      '#title' => t('Suffix HTML'),
      '#description' => t('HTML to inject after the button.'),
      '#cols' => 60,
      '#rows' => 2,
      '#weight' => -2,
      '#default_value' => $this->options['button_suffix']['value'],
    ];
    $form['destination'] = [
      '#type' => 'checkbox',
      '#title' => t('Include destination parameter?'),
      '#description' => t('Set a URL parameter to return a user to the current page after adding an item.'),
      '#maxlength' => 1024,
      '#default_value' => $this->options['destination'],
      '#weight' => -1,
    ];
    $form['tokenize'] = $form['alter']['alter_text'];
    $form['tokenize']['#title'] = $this->t('Use tokens');
    $form['tokenize']['#description'] = $this->t('Use tokens from the current row for button/property values. See the "Replacement Patterns" below for options.');
    $form['tokenize']['#default_value'] = $this->options['tokenize'];
    $form['preserve_tags'] = ['#type' => 'textfield'];
    $form['preserve_tags']['#title'] = $this->t('Preserve Tags');
    $form['preserve_tags']['#description'] = $this->t('Preserve these HTML tags during tokenization. Separate with spaces, i.e "h1 h2 p"');
    $form['preserve_tags']['#default_value'] = $this->options['preserve_tags'];
    $form['tokens'] = $form['alter']['help'];
    $form['tokens']['#states'] = NULL;
    $form['style_settings']['#attributes']['style'] = 'display:none;';
    $form['element_type_enable']['#attributes']['style'] = 'display:none;';
    $form['element_type']['#attributes']['style'] = 'display:none;';
    $form['element_class_enable']['#attributes']['style'] = 'display:none;';
    $form['element_class']['#attributes']['style'] = 'display:none;';
    $form['element_label_type_enable']['#attributes']['style'] = 'display:none;';
    $form['element_label_type']['#attributes']['style'] = 'display:none;';
    $form['element_label_class_enable']['#attributes']['style'] = 'display:none;';
    $form['element_label_class']['#attributes']['style'] = 'display:none;';
    $form['element_wrapper_type_enable']['#attributes']['style'] = 'display:none;';
    $form['element_wrapper_type']['#attributes']['style'] = 'display:none;';
    $form['element_wrapper_class_enable']['#attributes']['style'] = 'display:none;';
    $form['element_wrapper_class']['#attributes']['style'] = 'display:none;';
    $form['element_default_classes']['#attributes']['style'] = 'display:none;';
    $form['alter']['#attributes']['style'] = 'display:none;';
    $form['empty_field_behavior']['#attributes']['style'] = 'display:none;';
    $form['empty']['#attributes']['style'] = 'display:none;';
    $form['empty_zero']['#attributes']['style'] = 'display:none;';
    $form['hide_empty']['#attributes']['style'] = 'display:none;';
    $form['hide_alter_empty']['#attributes']['style'] = 'display:none;';
  }

  public function checkButtonAccess($plugin_definitions, $default_plugin, $entity_type, $bundle) {
    $access = FALSE;
    $plugin_class = $default_plugin;
    if (isset($this->options['access_plugin']) && isset($plugin_definitions[$this->options['access_plugin']]['class'])) {
      $plugin_class = $plugin_definitions[$this->options['access_plugin']]['class'];
    }
    if (method_exists($plugin_class, 'checkAccess')) {
      $context = $this->options['tokenize'] ? $this->tokenizeValue($this->options['context']) : $this->options['context'];
      $access = $plugin_class::checkAccess($entity_type, $bundle, $context);
    }
    else {
      $entity_manager = \Drupal::entityTypeManager();
      $access_handler = $entity_manager->getAccessControlHandler($entity_type);
      if ($bundle) {
        $access = $access_handler->createAccess($bundle);
      }
      else {
        $access = $access_handler->createAccess();
      }
    }
    return $access;
  }

  /**
   * {@inheritdoc}
   */
  public function render(ResultRow $values) {
    // Get the entity/bundle type.
    $type = explode('+', $this->options['type'], 2);
    // If we do not have a '+', then assume we have a no-bundle entity type.
    $entity_type = $type[0];
    $bundle = isset($type[1]) ? $type[1] : $type[0];

    // Load ViewsAddButton plugin definitions, and find the right one.
    $plugin_manager = \Drupal::service('plugin.manager.views_add_button');
    $plugin_definitions = $plugin_manager->getDefinitions();

    $plugin_class = $plugin_definitions['views_add_button_default']['class'];
    if (isset($this->options['render_plugin']) && !empty($this->options['render_plugin'])) {
      $plugin_class = $plugin_definitions[$this->options['render_plugin']]['class'];
    }
    else {
      $set_for_bundle = FALSE;
      foreach ($plugin_definitions as $pd) {
        // Exclude 'manual selection' special-use plugins.
        if (empty($pd['manual_select']) || !$pd['manual_select']) {
          if (!empty($pd['target_entity']) && $pd['target_entity'] === $entity_type) {
            if (!empty($pd['target_bundle'])) {
              $b = $bundle;
              /*
               * In certain cases, like the Group module,
               * we need to extract the true bundle name from a
               * hashed bundle string.
               */
              if (method_exists($pd['class'], 'get_bundle')) {
                $b = $pd['class']::get_bundle($bundle);
              }
              if ($pd['target_bundle'] === $b) {
                $plugin_class = $pd['class'];
                $set_for_bundle = TRUE;
              }
            }
            elseif (!$set_for_bundle) {
              $plugin_class = $pd['class'];
            }
          }
        }
      }
    }

    if ($this->checkButtonAccess($plugin_definitions, $plugin_class, $entity_type, $bundle)) {
      // Build URL Options.
      $opts = [];

      if ($this->options['destination']) {
        $dest = Url::fromRoute('<current>');
        $opts['query']['destination'] = $dest->toString();
      }
      $opts['attributes']['class'] = $this->options['tokenize'] ? $this->tokenizeValue($this->options['button_classes'],$values->index) : $this->options['button_classes'];

      // Build custom attributes.
      if ($this->options['button_attributes']) {
        $attrs = $this->options['button_attributes'] ? $this->tokenizeValue($this->options['button_attributes'],$values->index) : $this->options['button_attributes'];
        $attr_lines = preg_split('/$\R?^/m', $attrs);
        foreach ($attr_lines as $line) {
          $attr = explode('=', $line);
          if (count($attr) === 2) {
            $opts['attributes'][$attr[0]] = $attr[1];
          }
        }
      }
      // Build query string.
      if ($this->options['query_string']) {
        $opts['query'] = $this->getQueryString($values);
      }

      // Get the url from the plugin and build the link.
      if ($this->options['context']) {
        $context = $this->options['tokenize'] ? $this->tokenizeValue($this->options['context'],$values->index) : $this->options['context'];
        $url = $plugin_class::generateUrl($entity_type, $bundle, $opts, $context);
      }
      else {
        $url = $plugin_class::generateUrl($entity_type, $bundle, $opts);
      }
      $text = $this->options['button_text'] ? $this->options['button_text'] : 'Add ' . $bundle;
      $text = $this->options['tokenize'] ? $this->tokenizeValue($text,$values->index) : $text;

      // Generate the link.
      $l = NULL;
      if (method_exists($plugin_class, 'generateLink')) {
        $l = $plugin_class::generateLink($text, $url, $this->options);
      }
      else {
        $l = ViewsAddButtonDefault::generateLink($text, $url, $this->options);
      }
      $l = $l->toRenderable();

      // Add the prefix and suffix.
      if (isset($this->options['button_prefix']) || isset($this->options['button_suffix'])) {
        if (!empty($this->options['button_prefix']['value'])) {
          $prefix = check_markup($this->options['button_prefix']['value'], $this->options['button_prefix']['format']);
          $prefix = $this->options['tokenize'] ? $this->tokenizeValue($prefix,$values->index) : $prefix;
          $l['#prefix'] = $prefix;
        }
        if (!empty($this->options['button_suffix']['value'])) {
          $suffix = check_markup($this->options['button_suffix']['value'], $this->options['button_suffix']['format']);
          $suffix = $this->options['tokenize'] ? $this->tokenizeValue($suffix,$values->index) : $suffix;
          $l['#suffix'] = $suffix;
        }
        return $l;
      }

      return $l;
    }
    else {
      if (isset($this->options['button_access_denied']['value']) && !empty($this->options['button_access_denied']['value'])) {
        $markup = check_markup($this->options['button_access_denied']['value'], $this->options['button_access_denied']['format']);
        $markup = $this->options['tokenize'] ? $this->tokenizeValue($markup) : $markup;

        return ['#markup' => $markup];
      }
      else {
        return ['#markup' => ''];
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function tokenizeValue($value, $row_index = NULL) {
    if (strpos($value, '{{') !== FALSE) {
      $fake_item = [
        'alter_text' => TRUE,
        'text' => $value,
      ];

      // Use isset() because empty() will trigger on 0 and 0 is
      // the first row.
      if (isset($row_index) && isset($this->view->style_plugin->render_tokens[$row_index])) {
        $tokens = $this->view->style_plugin->render_tokens[$row_index];
      }
      elseif (!empty($tokens = $this->getRenderTokens($value))) {
        // We defined $tokens in the if statement.
      }
      else {
        // Get tokens from the last field.
        $last_field = end($this->view->field);
        if (isset($last_field->last_tokens)) {
          $tokens = $last_field->last_tokens;
        }
        else {
          $tokens = $last_field->getRenderTokens($fake_item);
        }
      }

      if (empty($this->options['preserve_tags'])) {
        $value = strip_tags($this->renderAltered($fake_item, $tokens));
      }
      else {
        $ts = explode(' ', $this->options['preserve_tags']);
        $tags = [];
        foreach ($ts as $t) {
          $tags[] = trim($t);
        }
        $value = Xss::filter($this->renderAltered($fake_item, $tokens), $tags);
      }

      if (!empty($this->options['alter']['trim_whitespace'])) {
        $value = trim($value);
      }
    }

    return $value;
  }

}
