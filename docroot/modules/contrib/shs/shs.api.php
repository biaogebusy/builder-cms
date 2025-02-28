<?php

/**
 * @file
 * Hooks for the shs module.
 *
 * This file contains no working PHP code; it exists to provide additional
 * documentation for doxygen as well as to document hooks in the standard Drupal
 * manner.
 */

/**
 * Alter the list of used javascript classes to create the shs widgets.
 *
 * @param array $definitions
 *   List of class names keyed by type and class key.
 * @param array $context
 *   Additional information about the current context (i.e. additional field
 *   settings).
 */
function hook_shs_class_definitions_alter(array &$definitions, array $context) {
  if (!empty($context['settings']['custom_widget'])) {
    // Use custom class for option elements.
    $definitions['views']['widgetItem'] = 'Drupal.customShs.MyWidgetItemView';
  }
}

/**
 * Alter the list of used javascript classes for an individual field.
 *
 * @param array $definitions
 *   List of class names keyed by type and class key.
 * @param array $context
 *   Additional information about the current context (i.e. additional field
 *   settings).
 */
function hook_shs_FIELDNAME_class_definitions_alter(array &$definitions, array $context) {
  // Use custom class for option elements.
  $definitions['views']['widgetItem'] = 'Drupal.customShs.MyWidgetItemView';
}

/**
 * Alter Javascript settings of shs widgets in entity forms and views.
 *
 * @param array $settings_shs
 *   Javascript settings for shs widgets.
 * @param string $bundle
 *   Bundle name of vocabulary the settings are used for.
 * @param string $field_name
 *   Name of field the provided settings are used for.
 */
function hook_shs_js_settings_alter(array &$settings_shs, $bundle, $field_name) {
  if ($field_name == 'field_article_terms') {
    $settings_shs['settings']['anyLabel'] = t('- Select an item -');
  }
}

/**
 * Alter Javascript settings for a single shs widget.
 *
 * @param array $settings_shs
 *   Javascript settings for the specified field.
 * @param string $bundle
 *   Bundle name of vocabulary the settings are used for.
 * @param string $field_name
 *   Name of field the provided settings are used for.
 */
function hook_shs_FIELDNAME_js_settings_alter(array &$settings_shs, $bundle, $field_name) {
  $settings_shs['labels'] = [
    // No label for first level.
    FALSE,
    t('Country'),
    t('City'),
  ];
  // Small speed-up for anmiations (defaults to 400ms).
  $settings_shs['display']['animationSpeed'] = 100;
}

/**
 * Alter the <strong>uncached</strong> term data for all bundles and fields.
 *
 * @param array $data
 *   Array with term data used for a single SHS widget.
 * @param array $context
 *   Associativ array containing information about the current context:
 *   - bundle: Name of vocabulary the data is fetched from
 *   - identifier: Identifier of field to fetch the data for
 *   - parent: Term Id of parent term (0 for first level)
 *
 * @see ShsController::getTermData()
 */
function hook_shs_term_data_alter(array &$data, array $context) {
  // Prepend each term name (rendered option label) with dots.
  array_walk($data, function (&$term, &$key) {
    $term->name = $term->name . ' ...';
  });
}

/**
 * Alter the <strong>uncached</strong> term data for a specific bundle.
 *
 * @param array $data
 *   Array with term data used for a single SHS widget.
 * @param array $context
 *   Associativ array containing information about the current context:
 *   - bundle: Name of vocabulary the data is fetched from
 *   - identifier: Identifier of field to fetch the data for
 *   - parent: Term Id of parent term (0 for first level)
 *
 * @see ShsController::getTermData()
 * @see hook_shs_term_data_alter()
 */
function hook_shs__bundle_BUNDLENAME__term_data_alter(array &$data, array $context) {

}

/**
 * Alter the <strong>uncached</strong> term data for a specific field.
 *
 * @param array $data
 *   Array with term data used for a single SHS widget.
 * @param array $context
 *   Associativ array containing information about the current context:
 *   - bundle: Name of vocabulary the data is fetched from
 *   - identifier: Identifier of field to fetch the data for
 *   - parent: Term Id of parent term (0 for first level)
 *
 * @see ShsController::getTermData()
 * @see hook_shs_term_data_alter()
 */
function hook_shs__field_IDENTIFIER__term_data_alter(array &$data, array $context) {

}

/**
 * Alter the response of SHS sent to the browser for all bundles and fields.
 *
 * Note: this hook allows you to alter the cached data. It is called on every
 *  single request SHS makes to fetch the data!
 *
 * @param string $content
 *   Json encoded string with data from ShsController::getTermData().
 * @param array $context
 *   Associativ array containing information about the current context:
 *   - bundle: Name of vocabulary the data is fetched from
 *   - identifier: Identifier of field to fetch the data for
 *   - parent: Term Id of parent term (0 for first level)
 *   - encodingOptions: encoding options used by json_encode(). Do not change.
 */
function hook_shs_term_data_response_alter(&$content, array $context) {
  $data = json_decode($content);
  // Prepend the term name (rendered option label) with its key (option value).
  array_walk($data, function (&$term, &$key) {
    $term->name = $key . ': ' . $term->name;
  });
  $options = isset($context['encodingOptions']) ? $context['encodingOptions'] : 0;
  $content = json_encode($data, $options);
}

/**
 * Alter the response of SHS sent to the browser for a single bundle.
 *
 * @param string $content
 *   Json encoded string with data from ShsController::getTermData().
 * @param array $context
 *   Associativ array containing information about the current context:
 *   - bundle: Name of vocabulary the data is fetched from
 *   - identifier: Identifier of field to fetch the data for
 *   - parent: Term Id of parent term (0 for first level)
 *   - encodingOptions: encoding options used by json_encode(). Do not change.
 *
 * @see hook_shs_term_data_response_alter()
 */
function hook_shs__bundle_BUNDLENAME__term_data_response_alter(&$content, array $context) {

}

/**
 * Alter the response of SHS sent to the browser for a single field.
 *
 * @param string $content
 *   Json encoded string with data from ShsController::getTermData().
 * @param array $context
 *   Associativ array containing information about the current context:
 *   - bundle: Name of vocabulary the data is fetched from
 *   - identifier: Identifier of field to fetch the data for
 *   - parent: Term Id of parent term (0 for first level)
 *   - encodingOptions: encoding options used by json_encode(). Do not change.
 *
 * @see hook_shs_term_data_response_alter()
 */
function hook_shs__field_IDENTIFIER__term_data_response_alter(&$content, array $context) {

}
