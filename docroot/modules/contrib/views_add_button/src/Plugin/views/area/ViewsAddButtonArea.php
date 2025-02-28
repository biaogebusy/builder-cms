<?php

namespace Drupal\views_add_button\Plugin\views\area;

use Drupal\Component\Utility\Html;
use Drupal\views\Plugin\views\area\TokenizeAreaPluginBase;
use Drupal\Core\Entity\ContentEntityType;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Link;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Url;
use Drupal\views_add_button\Plugin\views\ViewsAddButtonTrait;
use Drupal\views_add_button\Plugin\views_add_button\ViewsAddButtonDefault;

/**
 * Defines a views area plugin.
 *
 * @ingroup views_area_handlers
 *
 * @ViewsArea("views_add_button_area")
 */
class ViewsAddButtonArea extends TokenizeAreaPluginBase {

  use ViewsAddButtonTrait;

  /**
   * {@inheritdoc}
   */
  public function query() {
    // Leave empty to avoid a query on this field.
  }

  /**
   * Build Bundle Type List.
   */
  public function createPluginList() {
    $plugin_manager = \Drupal::service('plugin.manager.views_add_button');
    $plugin_definitions = $plugin_manager->getDefinitions();

    $options = ['Any Entity' => []];
    $entity_info = \Drupal::entityTypeManager()->getDefinitions();
    foreach ($plugin_definitions as $pd) {
      $label = $pd['label'];
      if ($pd['label'] instanceof TranslatableMarkup) {
        $label = $pd['label']->render();
      }

      $type_info = isset($pd['target_entity']) && isset($entity_info[$pd['target_entity']]) ? $entity_info[$pd['target_entity']] : 'default';
      $type_label = t('Any Entity');
      if ($type_info instanceof ContentEntityType) {
        $type_label = $type_info->getLabel();
      }
      if ($type_label instanceof TranslatableMarkup) {
        $type_label = $type_label->render();
      }
      $options[$type_label][$pd['id']] = $label;
    }
    return $options;
  }

  /**
   * Build Bundle Type List.
   */
  public function createEntityBundleList() {
    $ret = [];
    $entity_info = \Drupal::entityTypeManager()->getDefinitions();
    $bundle_info = \Drupal::service('entity_type.bundle.info');
    foreach ($entity_info as $type => $info) {
      // Is this a content/front-facing entity?
      if ($info instanceof ContentEntityType) {
        $label = $info->getLabel();
        if ($label instanceof TranslatableMarkup) {
          $label = $label->render();
        }
        $ret[$label] = [];
        $bundles = $bundle_info->getBundleInfo($type);
        foreach ($bundles as $key => $bundle) {
          if ($bundle['label'] instanceof TranslatableMarkup) {
            $ret[$label][$type . '+' . $key] = $bundle['label']->render();
          }
          else {
            $ret[$label][$type . '+' . $key] = $bundle['label'];
          }
        }
      }
    }
    return $ret;
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
      '#options' => $this->createEntityBundleList(),
      '#empty_option' => '- Select -',
      '#default_value' => $this->options['type'],
      '#weight' => -10,
      '#required' => TRUE,
    ];
    $form['render_plugin'] = [
      '#type' => 'select',
      '#title' => t('Custom Rendering Plugin'),
      '#description' => t('If you would like to specify a plugin to use for generating the URL and creating the 
        link, set it here. Leave unset to use the entity default plugin (recommended).'),
      '#options' => $this->createPluginList(),
      '#empty_option' => '- Select -',
      '#default_value' => $this->options['render_plugin'],
      '#weight' => -10,
    ];
    $form['access_plugin'] = [
      '#type' => 'select',
      '#title' => t('Custom Access Plugin'),
      '#description' => t('If you would like to specify an access plugin to use, set it here. 
        Leave unset to use the entity default plugin (recommended).'),
      '#options' => $this->createPluginList(),
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
      '#maxlength' => 1024,
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
      '#default_value' => $this->options['destination'],
      '#weight' => -1,
    ];
    $this->tokenForm($form, $form_state);
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
  public function render($empty = FALSE) {
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

      $opts['attributes']['class'] = $this->options['tokenize'] ? $this->tokenizeValue($this->options['button_classes']) : $this->options['button_classes'];

      // Build custom attributes.
      if ($this->options['button_attributes']) {
        $attrs = $this->options['button_attributes'] ? $this->tokenizeValue($this->options['button_attributes']) : $this->options['button_attributes'];
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
        $opts['query'] = $this->getQueryString();
      }

      // Get the url from the plugin and build the link.
      if ($this->options['context']) {
        $context = $this->options['tokenize'] ? $this->tokenizeValue($this->options['context']) : $this->options['context'];
        $url = $plugin_class::generateUrl($entity_type, $bundle, $opts, $context);
      }
      else {
        $url = $plugin_class::generateUrl($entity_type, $bundle, $opts);
      }
      $text = $this->options['button_text'] ? $this->options['button_text'] : 'Add ' . $bundle;
      $text = $this->options['tokenize'] ? $this->tokenizeValue($text) : $text;
      $text = t($text);

      // Generate the link.
      $l = NULL;
      /* @var $l \Drupal\Core\Link */
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
          $prefix = $this->options['tokenize'] ? $this->tokenizeValue($prefix) : $prefix;
          $l['#prefix'] = $prefix;
        }
        if (!empty($this->options['button_suffix']['value'])) {
          $suffix = check_markup($this->options['button_suffix']['value'], $this->options['button_suffix']['format']);
          $suffix = $this->options['tokenize'] ? $this->tokenizeValue($suffix) : $suffix;
          $l['#suffix'] = $suffix;
        }

        return $l;
      }

      return $l;
    }
    // Access is denied.
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

}
