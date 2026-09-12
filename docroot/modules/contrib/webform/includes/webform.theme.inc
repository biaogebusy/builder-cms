<?php

/**
 * @file
 * Theme hooks, preprocessor, and suggestions.
 */

use Drupal\Core\Hook\Attribute\LegacyHook;
use Drupal\webform\Hook\WebformThemeHooks;
use Drupal\Component\Utility\Html;
use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Render\Element;
use Drupal\Core\StringTranslation\ByteSizeMarkup;
use Drupal\Core\Template\Attribute;
use Drupal\file\Entity\File;
use Drupal\webform\Element\WebformSelectOther;
use Drupal\webform\Plugin\WebformElement\Radios as WebformRadios;
use Drupal\webform\Plugin\WebformElement\WebformEntityRadios;
use Drupal\webform\Utility\WebformAccessibilityHelper;
use Drupal\webform\Utility\WebformElementHelper;

/* ************************************************************************** */
// Theme hooks.
/* ************************************************************************** */

/**
 * Implements hook_theme().
 */
#[LegacyHook]
function webform_theme() {
  return \Drupal::service(WebformThemeHooks::class)->theme();
}

/**
 * Implements hook_theme_registry_alter().
 */
#[LegacyHook]
function webform_theme_registry_alter(&$theme_registry) {
  \Drupal::service(WebformThemeHooks::class)->themeRegistryAlter($theme_registry);
}

/* ************************************************************************** */
// Preprocessors.
/* ************************************************************************** */

/**
 * Implements preprocess_menu_local_action() for single local action link templates.
 *
 * Applies custom link attributes to local actions.
 * Custom attributes are used to open Webform UI modals.
 *
 * @see template_preprocess_menu_local_action();
 * @see \Drupal\webform\WebformEntityHandlersForm
 * @see \Drupal\webform_ui\WebformUiEntityEditForm
 * @see https://www.drupal.org/node/2897396
 */
function webform_preprocess_menu_local_action(&$variables) {
  $link = $variables['element']['#link'];
  // Only need to update local actions with link attributes.
  if (!isset($link['attributes'])) {
    return;
  }

  $link += [
    'localized_options' => [],
  ];
  $link['localized_options']['attributes'] = $link['attributes'];
  $link['localized_options']['attributes']['class'][] = 'button';
  $link['localized_options']['attributes']['class'][] = 'button-action';
  $link['localized_options']['set_active_class'] = TRUE;

  $variables['link'] = [
    '#type' => 'link',
    '#title' => $link['title'],
    '#options' => $link['localized_options'],
    '#url' => $link['url'],
  ];
}

/**
 * Implements hook_preprocess_checkboxes() for checkboxes templates.
 *
 * @see \Drupal\webform\Plugin\WebformElement\OptionsBase
 */
function webform_preprocess_checkboxes(&$variables) {
  if (!WebformElementHelper::isWebformElement($variables['element'])) {
    return;
  }

  _webform_preprocess_options($variables);
}

/**
 * Implements hook_preprocess_radios() for radios templates.
 *
 * @see \Drupal\webform\Plugin\WebformElement\OptionsBase
 */
function webform_preprocess_radios(&$variables) {
  if (!WebformElementHelper::isWebformElement($variables['element'])) {
    return;
  }

  _webform_preprocess_options($variables);
}

/**
 * Implements hook_preprocess_select() for select templates.
 */
function webform_preprocess_select(&$variables) {
  if (!WebformElementHelper::isWebformElement($variables['element'])) {
    return;
  }

  $element = $variables['element'];

  // When options are sorted (viu #sort_options: true) make sure the
  // select '_other_' options is always last.
  // @see \Drupal\webform\Element\WebformOtherBase::processWebformOther
  // @see template_preprocess_select().
  if (!empty($element['#sort_options']) && !empty($element['#webform_other'])) {
    $options = &$variables['options'];
    foreach ($options as $index => $option) {
      if ($option['value'] === WebformSelectOther::OTHER_OPTION) {
        unset($options[$index]);
        $options[] = $option;
        $options = array_values($options);
        break;
      }
    }
  }
}

/**
 * Prepares variable for status_messages.
 */
function webform_preprocess_status_messages(&$variables) {
  if (!isset($variables['status_headings']['info'])) {
    $variables['status_headings']['info'] = t('Information message');
  }
}

/* ************************************************************************** */
// Preprocess tables.
/* ************************************************************************** */

/**
 * Implements hook_preprocess_table() for table templates.
 */
function webform_preprocess_table(&$variables) {
  // Add links to 'Translate' webform tab.
  if (\Drupal::routeMatch()->getRouteName() === 'entity.webform.config_translation_overview') {
    /** @var \Drupal\webform\WebformInterface $webform */
    $webform = \Drupal::routeMatch()->getParameter('webform');
    foreach ($variables['rows'] as &$row) {
      // Check first cell.
      if (!isset($row['cells'][0]['content'])
        || !is_array($row['cells'][0]['content'])
        || !isset($row['cells'][0]['content']['#markup'])) {
        continue;
      }

      // Check last cell edit link.
      if (!isset($row['cells'][1]['content'])
        || !is_array($row['cells'][1]['content'])
        || !isset($row['cells'][1]['content']['#links'])
        || !is_array($row['cells'][1]['content']['#links'])
        || !isset($row['cells'][1]['content']['#links']['edit'])) {
        continue;
      }

      // Get language from edit link.
      $route_parameters = $row['cells'][1]['content']['#links']['edit']['url']->getRouteParameters();
      $langcode = $route_parameters['langcode'] ?? NULL;
      $language = \Drupal::languageManager()->getLanguage($langcode);

      // Convert the first cell in the row to a link.
      $row['cells'][0]['content'] = [
        '#type' => 'link',
        '#url' => $webform->toUrl('canonical', ['language' => $language]),
        '#title' => $row['cells'][0]['content'],
      ];
    }
  }
}

/* ************************************************************************** */
// Preprocess containers.
/* ************************************************************************** */

/**
 * Implements hook_preprocess_datetime_form() for datetime form element templates.
 */
function webform_preprocess_datetime_form(&$variables) {
  if (!WebformElementHelper::isWebformElement($variables['element'])) {
    return;
  }

  $element = $variables['element'];

  // Date and time custom placeholder.
  if (isset($element['#date_date_placeholder']) && isset($variables['content']['date'])) {
    $variables['content']['date']['#attributes']['placeholder'] = $element['#date_date_placeholder'];
  }
  if (isset($element['#date_time_placeholder']) && isset($variables['content']['time'])) {
    $variables['content']['time']['#attributes']['placeholder'] = $element['#date_time_placeholder'];
  }

  // Add .container-inline to datetime form wrapper which is missing from the
  // stable base theme.
  // @see core/themes/classy/templates/form/datetime-form.html.twig
  // @see core/themes/stable/templates/form/datetime-form.html.twig
  $variables['attributes']['class'][] = 'container-inline';
}

/**
 * Implements hook_preprocess_details() for details element templates.
 */
function webform_preprocess_details(&$variables) {
  if (!WebformElementHelper::isWebformElement($variables['element'])) {
    return;
  }

  // Setup description, help, and more.
  _webform_preprocess_element($variables);

  $element = &$variables['element'];

  // Hide details title.
  if (isset($element['#title_display']) && $element['#title_display'] === 'invisible') {
    $variables['title'] = WebformAccessibilityHelper::buildVisuallyHidden($variables['title']);
  }

  // Remove invalid 'required' and 'aria-required' attributes from details.
  if (isset($element['#webform_key'])) {
    unset(
      $variables['attributes']['required'],
      $variables['attributes']['aria-required']
    );
  }
}

/**
 * Implements hook_preprocess_fieldset() for fieldset templates.
 */
function webform_preprocess_fieldset(&$variables) {
  if (!WebformElementHelper::isWebformElement($variables['element'])) {
    return;
  }

  // Setup description, help, and more.
  _webform_preprocess_element($variables, ['legend', 'title']);

  $element = &$variables['element'];

  // Apply inline title defined by radios, checkboxes, and buttons.
  // @see \Drupal\webform\Plugin\WebformElement\OptionsBase::prepare
  if (isset($element['#_title_display'])) {
    $variables['attributes']['class'][] = 'webform-fieldset--title-inline';
  }

  // If the title display is none remove the legend.title and set
  // display to none.
  if (isset($element['#title_display']) && $element['#title_display'] === 'none') {
    $variables['legend'] = ['attributes' => new Attribute(['style' => 'display:none'])];
  }
  elseif (isset($element['#title_attributes'])
    && $variables['legend']['attributes'] instanceof Attribute) {
    $variables['legend']['attributes']->merge(new Attribute($element['#title_attributes']));
  }

  // Add .js-webform-form-type-* class to be used JavaScript and #states API.
  // @see js/webform.states.js
  if (isset($element['#type'])) {
    $variables['attributes']['class'][] = 'js-webform-type-' . Html::getClass($element['#type']);
    $variables['attributes']['class'][] = 'webform-type-' . Html::getClass($element['#type']);
  }

  // Remove invalid 'required' attributes from fieldset.
  //
  // Issue #3174459: W3C Validation: required attribute not allowed on
  // fieldset tag.
  // @see https://www.drupal.org/project/drupal/issues/3174459
  if (isset($element['#webform_key'])) {
    unset($variables['attributes']['required']);
  }

  // Add aria-required="true" only on fieldsets containing required
  // radiobuttons.
  //
  // Issue #3240249: Aria-required on fieldset trigger accessibility fails.
  // @see https://www.drupal.org/project/webform/issues/3240249
  // @see https://www.drupal.org/project/webform/issues/3277192
  /** @var \Drupal\webform\Plugin\WebformElementManagerInterface $element_manager */
  $element_manager = \Drupal::service('plugin.manager.webform.element');
  $element_plugin = $element_manager->getElementInstance($element);
  $is_radios = ($element_plugin instanceof WebformRadios || $element_plugin instanceof WebformEntityRadios);
  if ($is_radios) {
    $id = $variables['attributes']['id'] . '-legend';
    $variables['legend']['attributes']['id'] = $id;

    if (isset($variables['attributes']['aria-required'])) {
      // The aria-required is _not_ necessary on a fieldset of radio elements.
      unset($variables['attributes']['aria-required']);
    }
  }
  elseif (isset($variables['attributes']['aria-required'])) {
    unset($variables['attributes']['aria-required']);
  }
}

/* ************************************************************************** */
// Preprocess form element.
/* ************************************************************************** */

/**
 * Implements hook_preprocess_form_element() for form element templates.
 */
function webform_preprocess_form_element(&$variables) {
  if (!WebformElementHelper::isWebformElement($variables['element'])) {
    return;
  }

  $element = &$variables['element'];

  // Setup description, help, and more.
  _webform_preprocess_element($variables, ['label', '#title']);

  // Make sure the #description_display is always applied to account for
  // #more which is placed in the #description.
  // @see template_preprocess_form_element()
  if (isset($variables['description'])) {
    $variables['description_display'] = $element['#description_display'];
  }

  // Issue #2700439: Description class not added to
  // form-element.html.twig template.
  // @see https://www.drupal.org/project/drupal/issues/2700439
  if (isset($variables['description_display']) && $variables['description_display'] === 'before') {
    // Add system .description class.
    if (isset($variables['description'])) {
      if (empty($variables['description']['attributes'])) {
        $variables['description']['attributes'] = new Attribute();
      }
      $variables['description']['attributes']->addClass('description');
    }
  }

  // Add missing classes to the Claro theme's form elements.
  // @see core/modules/system/templates/form-element.html.twig
  // @see claro/templates/form-element.html.twig
  // @todo Once Claro and Oliver are stable determine if this code is needed.
  static $is_claro_theme;
  static $is_olivero_theme;
  if (!isset($is_claro_theme)) {
    /** @var \Drupal\webform\WebformThemeManagerInterface $theme_manager */
    $theme_manager = \Drupal::service('webform.theme_manager');
    $is_claro_theme = $theme_manager->isActiveTheme('claro');
    $is_olivero_theme = $theme_manager->isActiveTheme('olivero');
  }
  if ($is_claro_theme || $is_olivero_theme) {
    // Add system .form-type-TYPE class.
    if (!empty($variables['type'])) {
      $variables['attributes']['class'][] = 'form-type-' . Html::getClass($variables['type']);
    }

    // Add system .description class.
    if (isset($variables['description'])) {
      if (empty($variables['description']['attributes'])) {
        $variables['description']['attributes'] = new Attribute();
      }
      $variables['description']['attributes']->addClass('description');
    }
  }

  // Add .js-webform-form-type-* class to be used JavaScript and #states API.
  // @see js/webform.states.js
  if (isset($element['#type'])) {
    $variables['attributes']['class'][] = 'js-webform-type-' . Html::getClass($element['#type']);
    $variables['attributes']['class'][] = 'webform-type-' . Html::getClass($element['#type']);
  }
}

/**
 * Implements hook_preprocess_form_element_label() for form element label templates.
 */
function webform_preprocess_form_element_label(&$variables) {
  $element = &$variables['element'];

  // Fix variable title #markup tha contains a render array which is most
  // likely a Help tooltip.
  // @see template_preprocess_form_element_label()
  // @see webform_preprocess_form_element()
  if (isset($variables['title'])
    && is_array($variables['title'])
    && is_array($variables['title']['#markup'])) {
    $variables['title'] = $variables['title']['#markup'];
  }

  // Remove label 'for' attribute.
  if (!empty($element['#attributes']['webform-remove-for-attribute'])) {
    unset($element['#attributes']['webform-remove-for-attribute']);
    unset($variables['attributes']['webform-remove-for-attribute']);
    unset($variables['attributes']['for']);
  }
}

/* ************************************************************************** */
// Preprocess file/image elements.
/* ************************************************************************** */

/**
 * Implements hook_preprocess_file_managed_file() for file managed file templates.
 *
 * @see https://stackoverflow.com/questions/21842274/cross-browser-custom-styling-for-file-upload-button
 * @see template_preprocess_file_managed_file()
 */
function webform_preprocess_file_managed_file(&$variables) {
  if (!WebformElementHelper::isWebformElement($variables['element'])) {
    return;
  }

  $element = &$variables['element'];
  if (empty($element['#button'])) {
    return;
  }

  // Don't alter hidden file upload input.
  if (!Element::isVisibleElement($element['upload'])) {
    return;
  }

  // Create an unique id for the file upload input and label.
  $button_id = Html::getUniqueId($variables['element']['upload']['#id'] . '-button');

  // Create a label that is styled like an action button.
  $label = [
    '#type' => 'html_tag',
    '#tag' => 'label',
    '#value' => $element['#button__title'] ?? (empty($element['#multiple']) ? t('Choose file') : t('Choose files')),
    '#attributes' => $element['#button__attributes'] ?? [],
  ];

  // Add 'for' attribute.
  $label['#attributes']['for'] = $button_id;

  // Add default button classes.
  if (empty($label['#attributes']['class'])) {
    $label['#attributes']['class'][] = 'button';
    $label['#attributes']['class'][] = 'button-action';
  }

  // Add .webform-file-button.
  $label['#attributes']['class'][] = 'webform-file-button';

  // Make sure the label is first.
  $element = ['label' => $label] + $element;

  // Set the custom button ID for file upload input.
  $element['upload']['#attributes']['id'] = $button_id;

  // Hide the file upload.
  $element['upload']['#attributes']['class'][] = 'webform-file-button-input';

  // Attach library.
  $element['upload']['#attached']['library'][] = 'webform/webform.element.file.button';

  // Remove #errors to prevent 'aria-invalid' from being added to the
  // remove file checkbox which is valid.
  // @see \Drupal\Core\Render\Element\RenderElementBase::setAttributes
  foreach (Element::children($element) as $child_key) {
    if (NestedArray::getValue($element, [$child_key, 'selected', '#type']) === 'checkbox') {
      unset($element[$child_key]['selected']['#errors']);
    }
  }
}

/**
 * Implements hook_preprocess_file_upload_help() for file upload help text templates.
 *
 * @see \Drupal\webform\Plugin\WebformElement\WebformManagedFileBase::prepare
 */
function webform_preprocess_file_upload_help(&$variables) {
  // Retrieve upload validators from file upload element variables.
  $upload_validators = $variables['upload_validators'];

  // Exit early if no 'webform_file_limit' is set.
  if (!isset($upload_validators['webform_file_limit'])) {
    return;
  }

  // Get the webform's overall file size limit and the individual file element's limit.
  $webform_file_limit = $upload_validators['webform_file_limit'];
  $element_file_limit = (isset($upload_validators['FileSizeLimit']))
    ? $upload_validators['FileSizeLimit']['fileLimit']
    : 0;

  // Prepare translated arguments for the file size descriptions.
  $t_args = [
    // Overall size limit for all files.
    '@webform_file_limit' => ByteSizeMarkup::create($webform_file_limit),
    // Size limit for individual files.
    '@element_file_limit' => ByteSizeMarkup::create($element_file_limit),
  ];

  // Determine the appropriate description based on file size limits.
  $file_limit_description = ($element_file_limit < $webform_file_limit)
    // If individual file limit is smaller than the overall limit, include both in the message.
    ? t('@element_file_limit limit per file. The accumulated size of all files in this form cannot exceed @webform_file_limit.', $t_args)
    // Otherwise, only mention the overall file size limit.
    : t('The accumulated size of all files in this form cannot exceed @webform_file_limit.', $t_args);

  // Remove any existing descriptions that match the outdated '@size limit.' message.
  $descriptions = array_filter(
    $variables['descriptions'],
    fn($description) => $description->getUntranslatedString() !== '@size limit.'
  );

  // Add the updated file limit description to the list of descriptions.
  $variables['descriptions'] = [...$descriptions, $file_limit_description];
}

/**
 * Implements hook_preprocess_file_link() for file link templates.
 *
 * @see webform_file_access
 */
function webform_preprocess_file_link(&$variables) {
  /** @var \Drupal\file\FileInterface $file */
  $file = $variables['file'];
  $file = ($file instanceof File) ? $file : File::load($file->fid);

  // Remove link to temporary anonymous private file uploads.
  if ($file->isTemporary() && $file->getOwner() && $file->getOwner()->isAnonymous() && str_starts_with($file->getFileUri(), 'private://webform/')) {
    $variables['link'] = $file->getFilename();
  }

  // Add file size variable to Claro theme.
  if (empty($variables['file_size'])
    && $file->getSize()
    && \Drupal::service('webform.theme_manager')->isActiveTheme('claro')) {
    $variables['file_size'] = ByteSizeMarkup::create($file->getSize(), \Drupal::languageManager()->getCurrentLanguage()->getId());
  }
}

/**
 * Implements hook_preprocess_image() for image templates.
 *
 * Make sure the image src for the 'webform_image_file' src is an absolute URL.
 */
function webform_preprocess_image(&$variables) {
  global $base_url;
  if (isset($variables['attributes']['class'])
    && in_array('webform-image-file', (array) $variables['attributes']['class'])
    && !preg_match('#^https?://#', $variables['attributes']['src'])) {
    $variables['attributes']['src'] = $base_url . preg_replace('/^' . preg_quote(base_path(), '/') . '/', '/', $variables['attributes']['src']);
  }
}

/* ************************************************************************** */
// Preprocess helpers.
/* ************************************************************************** */

/**
 * Prepares variables for checkboxes and radios options templates.
 *
 * Below code must be called by template_preprocess_(radios|checkboxes) which
 * reset the element's 'attributes';
 */
function _webform_preprocess_options(array &$variables) {
  $element = &$variables['element'];

  $variables['attributes']['class'][] = Html::getClass('js-webform-' . $element['#type']);

  if (!empty($element['#options_display'])) {
    if (str_starts_with($element['#options_display'], 'buttons_')) {
      $variables['attributes']['class'][] = 'webform-options-display-buttons';
    }
    $variables['attributes']['class'][] = Html::getClass('webform-options-display-' . $element['#options_display']);
    $variables['#attached']['library'][] = 'webform/webform.element.options';
  }
}

/**
 * Prepares webform element description, help, and more templates.
 *
 * @see template_preprocess_form_element()
 * @see core/modules/system/templates/form-element.html.twig
 * @see template_preprocess_details()
 * @see /core/modules/system/templates/details.html.twig
 * @see template_preprocess_fieldset()
 * @see /core/modules/system/templates/fieldset.html.twig
 * @see template_preprocess_webform_section()
 * @see /webform/templates/webform-section.html.twig
 */
function _webform_preprocess_element(array &$variables, $title_parents = ['title']) {
  $element = &$variables['element'];
  $type = $element['#type'] ?? '';

  // Fix details 'description' property which does not have description.content.
  // @see template_preprocess_details
  // @see Issue #2896169: Details elements have incorrect aria-describedby attributes
  if (!empty($element['#description'])) {
    // Normalize description into a simple render array.
    if (is_array($element['#description'])) {
      $description = [$element['#description']];
    }
    else {
      $description = ['#markup' => $element['#description']];
    }

    if ($type === 'details') {
      $description_attributes = [];
      if (!empty($element['#id'])) {
        $description_attributes['id'] = $element['#id'] . '--description';
      }
      $variables['description'] = [];
      $variables['description']['content'] = [
        '#type' => 'container',
        '#attributes' => new Attribute($description_attributes),
      ] + $description;
    }
    else {
      $variables['description'] += ['attributes' => new Attribute()];
      // Wrap description in a container.
      $variables['description']['content'] = [
        '#type' => 'container',
        '#attributes' => $variables['description']['attributes'],
      ] + $description;
      $variables['description']['attributes'] = new Attribute();
    }

    $variables['description']['content']['#attributes']->addClass('webform-element-description');

    // Handle invisible descriptions.
    if (isset($element['#description_display']) && $element['#description_display'] === 'invisible') {
      $variables['description']['content']['#attributes']->addClass('visually-hidden');
      $variables['description_display'] = 'after';
    }

    // Nest description content so that we can a more link
    // below the description.
    $variables['description']['content'] = [
      'description' => $variables['description']['content'],
    ];
  }
  elseif (isset($variables['description']) && empty($variables['description'])) {
    // Unset $variable['description'] which can be set to NULL or empty string.
    // This allows $variable['description'] to be converted to render array.
    // @see template_preprocess_details()
    // @see template_preprocess_form_element()
    unset($variables['description']);
  }

  // Move #description to #help for webform admin routes.
  _webform_preprocess_description_help($variables);

  // Add (read) more to #description.
  _webform_preprocess_form_element_description_more($variables);

  // Add help to title (aka label).
  _webform_preprocess_help($variables, $title_parents);

  // Hide 'item' label[for].
  if ($type === 'item') {
    $variables['label']['#attributes']['webform-remove-for-attribute'] = TRUE;
  }
}

/**
 * Prepares #description and #help properties for form element templates.
 */
function _webform_preprocess_description_help(array &$variables) {
  /** @var \Drupal\webform\WebformRequestInterface $webform_request */
  $webform_request = \Drupal::service('webform.request');
  /** @var \Drupal\webform\WebformLibrariesManagerInterface $libraries_manager */
  $libraries_manager = \Drupal::service('webform.libraries_manager');

  $element = &$variables['element'];

  // Move #description to #help for webform admin routes.
  if (\Drupal::config('webform.settings')->get('ui.description_help')
    && $webform_request->isWebformAdminRoute()
    && $libraries_manager->isIncluded('tippyjs')
    && \Drupal::routeMatch()->getRouteName() !== 'webform.contribute.settings'
    && !isset($element['#help'])
    && !empty($element['#title']) && (empty($element['#title_display']) || !in_array($element['#title_display'], ['attribute', 'invisible']))
    && !empty($element['#description']) && (empty($element['#description_display']) || !in_array($element['#description_display'], ['invisible']))
  ) {
    // Render the description.
    $description = (is_array($element['#description'])) ? \Drupal::service('renderer')->render($element['#description']) : $element['#description'];
    // Replace breaks in admin tooltips with horizontal rules.
    $description = str_replace('<br /><br />', '<hr />', $description);
    $element['#help'] = ['#markup' => $description];

    // We must still render the description as visually hidden because the input
    // has an 'aria-describedby' attribute pointing to the description's id.
    $variables['description_display'] = 'after';
    $variables['description']['content']['description']['#attributes']->addClass('visually-hidden');

    // Remove all links from the #description since it will be .visually-hidden
    // and unreachable via tabbing.
    if (isset($variables['description']['content']['description']['#markup'])) {
      $variables['description']['content']['description']['#markup'] = strip_tags($variables['description']['content']['description']['#markup']);
    }
  }
}

/**
 * Append #help to title or element variable.
 */
function _webform_preprocess_help(array &$variables, $title_parents = ['title']) {
  $element = &$variables['element'];
  $type = $element['#type'] ?? '';

  if (empty($element['#help'])) {
    return;
  }

  $help_display = $element['#help_display'] ?? 'title_after';

  // Determine target variable (aka render element).
  $targets = [
    'title_before' => 'title',
    'title_after' => 'title',
    // Details don't support prefix and suffix.
    // @see details.html.twig
    'element_before' => ($type === 'details') ? 'children' : 'prefix',
    'element_after' => ($type === 'details') ? 'children' : 'suffix',
  ];
  $target = $targets[$help_display];

  // Determine the target element.
  if ($target === 'title') {
    // User title parent to the title (target) element.
    $target_element =& NestedArray::getValue($variables, $title_parents);
    // Empty title should not display help.
    if (empty($target_element)) {
      return;
    }
  }
  else {
    $variables += [$target => []];
    $target_element = &$variables[$target];
  }

  // Default #help_title to element's #title.
  if (empty($element['#help_title']) && !empty($element['#title'])) {
    $element['#help_title'] = $element['#title'];
  }

  $build = [];
  if (!empty($target_element)) {
    $build[$target] = (is_array($target_element)) ? $target_element : ['#markup' => $target_element];
  }
  $build['help'] = [
    '#type' => 'webform_help',
  ] + array_intersect_key($element, array_flip(['#help', '#help_title']));

  // Add help attributes.
  if (isset($element['#help_attributes'])) {
    $build['help']['#attributes'] = $element['#help_attributes'];
  }

  // Get #title_display and move help before title for 'inline' titles.
  if (isset($element['#_title_display'])) {
    // #_title_display is set via WebformElementBase::prepare.
    // @see \Drupal\webform\Plugin\WebformElementBase::prepare.
    $title_display = $element['#_title_display'];
  }
  elseif (isset($element['#title_display'])) {
    $title_display = $element['#title_display'];
  }
  else {
    $title_display = NULL;
  }

  // Place help before the target.
  if (isset($build[$target])) {
    if (($target === 'title' && $title_display === 'inline')
      || $help_display === 'title_before'
      || $help_display === 'element_before') {
      $build[$target]['#weight'] = 0;
      $build['help']['#weight'] = -1;
    }
  }

  // Add help container classes to element wrapper.
  $variables['attributes']['class'][] = Html::getClass('webform-element-help-container--' . preg_replace('/(_after|_before)/', '', $help_display));
  $variables['attributes']['class'][] = Html::getClass('webform-element-help-container--' . $help_display);

  // Replace $variables with new render array containing help.
  $target_element = $build;
}

/**
 * Prepares #more property for form element template.
 *
 * @see template_preprocess_form_element()
 * @see form-element.html.twig
 * @see template_preprocess_datetime_wrapper()
 * @see datetime-wrapper.html.twig
 */
function _webform_preprocess_form_element_description_more(array &$variables) {
  $element = &$variables['element'];
  if (empty($element['#more'])) {
    return;
  }

  // Make sure description is displayed.
  if (!isset($variables['description_display'])) {
    $variables['description_display'] = 'after';
  }

  // Add more element.
  $variables['description']['content']['more'] = [
    '#type' => 'webform_more',
    '#attributes' => (!empty($element['#id'])) ? ['id' => $element['#id'] . '--more'] : [],
  ] + array_intersect_key($element, array_flip(['#more', '#more_title']));
}

/* ************************************************************************** */
// Theme suggestions.
/* ************************************************************************** */

/**
 * Provides alternate named suggestions for a specific theme hook.
 *
 * @param array $variables
 *   An array of variables passed to the theme hook.
 * @param string $hook
 *   The base hook name.
 *
 * @return array
 *   An array of theme suggestions.
 */
function _webform_theme_suggestions(array $variables, $hook) {
  $suggestions = [];

  if ($hook === 'webform' && isset($variables['element']) && isset($variables['element']['#webform_id'])) {
    $suggestions[] = $hook . '__' . $variables['element']['#webform_id'];
  }
  elseif ($hook === 'webform_submission_form' && isset($variables['form']) && isset($variables['form']['#webform_id'])) {
    $suggestions[] = $hook . '__' . $variables['form']['#webform_id'];
  }
  elseif (str_starts_with($hook, 'webform_element_base_') || str_starts_with($hook, 'webform_container_base_')) {
    $element = $variables['element'];

    if (!empty($element['#type'])) {
      $type = $element['#type'];
      $name = $element['#webform_key'];

      $suggestions[] = $hook . '__' . $type;
      $suggestions[] = $hook . '__' . $type . '__' . $name;
    }
  }
  else {
    $webform = NULL;
    $webform_submission = NULL;
    $sanitized_view_mode = NULL;
    if (isset($variables['elements']) && isset($variables['elements']['#webform_submission'])) {
      /** @var \Drupal\webform\WebformSubmissionInterface $webform_submission */
      $webform_submission = $variables['elements']['#webform_submission'];
      $webform = $webform_submission->getWebform();
      $sanitized_view_mode = strtr($variables['elements']['#view_mode'], '.', '_');
    }
    elseif (isset($variables['webform_submission'])) {
      /** @var \Drupal\webform\WebformSubmissionInterface $webform_submission */
      $webform_submission = $variables['webform_submission'];
      $webform = $webform_submission->getWebform();
    }
    elseif (isset($variables['webform'])) {
      /** @var \Drupal\webform\WebformInterface $webform */
      $webform = $variables['webform'];
    }

    if ($webform) {
      $suggestions[] = $hook . '__' . $webform->id();
      if (isset($variables['handler'])) {
        /** @var \Drupal\webform\Plugin\WebformHandlerInterface $handler */
        $handler = $variables['handler'];
        $suggestions[] = $hook . '__' . $webform->id() . '__' . $handler->getPluginId();
        $suggestions[] = $hook . '__' . $webform->id() . '__' . $handler->getPluginId() . '__' . $handler->getHandlerId();
      }
      if ($sanitized_view_mode) {
        $suggestions[] = $hook . '__' . $webform->id() . '__' . $sanitized_view_mode;
        $suggestions[] = $hook . '__' . $sanitized_view_mode;
      }
    }
  }

  return $suggestions;
}

/**
 * Helper function used to generate hook_theme_suggestions_HOOK().
 */
function _webform_devel_hook_theme_suggestions_generate() {
  $theme = webform_theme();
  print '<pre>';
  foreach ($theme as $hook => $info) {
    $suggestion = FALSE;
    if ($hook === 'webform') {
      $suggestion = TRUE;
    }
    elseif (str_starts_with($hook, 'webform_element_base_') || str_starts_with($hook, 'webform_container_base_')) {
      $suggestion = TRUE;
    }
    elseif (isset($info['variables'])
      && !array_key_exists('element', $info['variables'])
      && (array_key_exists('webform_submission', $info['variables']) || array_key_exists('webform', $info['variables']))) {
      $suggestion = TRUE;
    }

    if ($suggestion) {
      print "/**
 * Implements hook_theme_suggestions_HOOK().
 */
function webform_theme_suggestions_$hook(array \$variables) {
  return _webform_theme_suggestions(\$variables, '$hook');
}

";
    }
  }
  print '</pre>';
  exit;
}

/* ************************************************************************** */
// Webform theme suggestions.
// Generate using _webform_devel_hook_theme_suggestions_generate();
/* ************************************************************************** */

/**
 * Implements hook_theme_suggestions_HOOK().
 */
#[LegacyHook]
function webform_theme_suggestions_webform(array $variables) {
  return \Drupal::service(WebformThemeHooks::class)->themeSuggestionsWebform($variables);
}

/**
 * Implements hook_theme_suggestions_HOOK().
 */
#[LegacyHook]
function webform_theme_suggestions_webform_confirmation(array $variables) {
  return \Drupal::service(WebformThemeHooks::class)->themeSuggestionsWebformConfirmation($variables);
}

/**
 * Implements hook_theme_suggestions_HOOK().
 */
#[LegacyHook]
function webform_theme_suggestions_webform_preview(array $variables) {
  return \Drupal::service(WebformThemeHooks::class)->themeSuggestionsWebformPreview($variables);
}

/**
 * Implements hook_theme_suggestions_HOOK().
 */
#[LegacyHook]
function webform_theme_suggestions_webform_submission(array $variables) {
  return \Drupal::service(WebformThemeHooks::class)->themeSuggestionsWebformSubmission($variables);
}

/**
 * Implements hook_theme_suggestions_HOOK().
 */
#[LegacyHook]
function webform_theme_suggestions_webform_submission_form(array $variables) {
  return \Drupal::service(WebformThemeHooks::class)->themeSuggestionsWebformSubmissionForm($variables);
}

/**
 * Implements hook_theme_suggestions_HOOK().
 */
#[LegacyHook]
function webform_theme_suggestions_webform_submission_navigation(array $variables) {
  return \Drupal::service(WebformThemeHooks::class)->themeSuggestionsWebformSubmissionNavigation($variables);
}

/**
 * Implements hook_theme_suggestions_HOOK().
 */
#[LegacyHook]
function webform_theme_suggestions_webform_submission_information(array $variables) {
  return \Drupal::service(WebformThemeHooks::class)->themeSuggestionsWebformSubmissionInformation($variables);
}

/**
 * Implements hook_theme_suggestions_HOOK().
 */
#[LegacyHook]
function webform_theme_suggestions_webform_submission_data(array $variables) {
  return \Drupal::service(WebformThemeHooks::class)->themeSuggestionsWebformSubmissionData($variables);
}

/**
 * Implements hook_theme_suggestions_HOOK().
 */
#[LegacyHook]
function webform_theme_suggestions_webform_element_base_html(array $variables) {
  return \Drupal::service(WebformThemeHooks::class)->themeSuggestionsWebformElementBaseHtml($variables);
}

/**
 * Implements hook_theme_suggestions_HOOK().
 */
#[LegacyHook]
function webform_theme_suggestions_webform_element_base_text(array $variables) {
  return \Drupal::service(WebformThemeHooks::class)->themeSuggestionsWebformElementBaseText($variables);
}

/**
 * Implements hook_theme_suggestions_HOOK().
 */
#[LegacyHook]
function webform_theme_suggestions_webform_container_base_html(array $variables) {
  return \Drupal::service(WebformThemeHooks::class)->themeSuggestionsWebformContainerBaseHtml($variables);
}

/**
 * Implements hook_theme_suggestions_HOOK().
 */
#[LegacyHook]
function webform_theme_suggestions_webform_container_base_text(array $variables) {
  return \Drupal::service(WebformThemeHooks::class)->themeSuggestionsWebformContainerBaseText($variables);
}

/**
 * Implements hook_theme_suggestions_HOOK().
 */
#[LegacyHook]
function webform_theme_suggestions_webform_email_html(array $variables) {
  return \Drupal::service(WebformThemeHooks::class)->themeSuggestionsWebformEmailHtml($variables);
}

/**
 * Implements hook_theme_suggestions_HOOK().
 */
#[LegacyHook]
function webform_theme_suggestions_webform_email_message_html(array $variables) {
  return \Drupal::service(WebformThemeHooks::class)->themeSuggestionsWebformEmailMessageHtml($variables);
}

/**
 * Implements hook_theme_suggestions_HOOK().
 */
#[LegacyHook]
function webform_theme_suggestions_webform_email_message_text(array $variables) {
  return \Drupal::service(WebformThemeHooks::class)->themeSuggestionsWebformEmailMessageText($variables);
}

/**
 * Implements hook_theme_suggestions_HOOK().
 */
#[LegacyHook]
function webform_theme_suggestions_webform_progress(array $variables) {
  return \Drupal::service(WebformThemeHooks::class)->themeSuggestionsWebformProgress($variables);
}

/**
 * Implements hook_theme_suggestions_HOOK().
 */
#[LegacyHook]
function webform_theme_suggestions_webform_progress_bar(array $variables) {
  return \Drupal::service(WebformThemeHooks::class)->themeSuggestionsWebformProgressBar($variables);
}

/**
 * Implements hook_theme_suggestions_HOOK().
 */
#[LegacyHook]
function webform_theme_suggestions_webform_progress_tracker(array $variables) {
  return \Drupal::service(WebformThemeHooks::class)->themeSuggestionsWebformProgressTracker($variables);
}

/* ************************************************************************** */
// Custom theme suggestions.
/* ************************************************************************** */

/**
 * Add webform options display suggestions to radios and checkboxes.
 *
 * @param array $variables
 *   Template variables.
 * @param string $type
 *   Type of options element. (checkboxes or radios)
 *
 * @return array
 *   If element include #options_display a template suggestion for
 *   TYPE-OPTIONS-DISPLAY
 */
function _webform_theme_suggestions_options(array $variables, $type) {
  if (!WebformElementHelper::isWebformElement($variables['element'])) {
    return [];
  }

  $element = $variables['element'];
  $suggestions = [];
  if (!empty($element['#options_display']) && str_starts_with($element['#options_display'], 'buttons')) {
    $suggestions[] = $type . '__webform_' . $element['#options_display'];
  }
  return $suggestions;
}

/**
 * Implements hook_theme_suggestions_HOOK().
 */
#[LegacyHook]
function webform_theme_suggestions_radios(array $variables) {
  return \Drupal::service(WebformThemeHooks::class)->themeSuggestionsRadios($variables);
}

/**
 * Implements hook_theme_suggestions_HOOK().
 */
#[LegacyHook]
function webform_theme_suggestions_checkboxes(array $variables) {
  return \Drupal::service(WebformThemeHooks::class)->themeSuggestionsCheckboxes($variables);
}

/**
 * Implements hook_theme_suggestions_HOOK().
 */
#[LegacyHook]
function webform_theme_suggestions_form_element(array $variables) {
  return \Drupal::service(WebformThemeHooks::class)->themeSuggestionsFormElement($variables);
}
