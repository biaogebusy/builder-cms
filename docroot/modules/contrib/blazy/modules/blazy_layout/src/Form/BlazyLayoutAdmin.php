<?php

namespace Drupal\blazy_layout\Form;

use Drupal\Component\Utility\Xss;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Url;
use Drupal\blazy\Form\BlazyAdminBase;
use Drupal\blazy\Plugin\Field\FieldFormatter\BlazyFormatterEntityTrait;
use Drupal\blazy_layout\BlazyLayoutDefault as Defaults;
use Drupal\blazy_layout\BlazyLayoutManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Extends base form for Blazy layout instance configuration form.
 */
class BlazyLayoutAdmin extends BlazyAdminBase implements BlazyLayoutAdminInterface {

  use StringTranslationTrait;
  use BlazyFormatterEntityTrait;
  use TraitAdminDescriptions;

  /**
   * The blazy layout manager service.
   *
   * @var \Drupal\blazy_layout\BlazyLayoutManagerInterface
   */
  protected $layoutManager;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    $instance = parent::create($container);
    $instance->setLayoutManager($container->get('blazy_layout'));

    return $instance;
  }

  /**
   * Sets manager service.
   */
  public function setLayoutManager(BlazyLayoutManagerInterface $layoutManager) {
    $this->layoutManager = $layoutManager;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function formBase(array &$form, array $settings, array $options = []): void {
    $excludes = $options['excludes'] ?? [];
    $elements = [];
    $attrs    = ['class' => ['is-tooltip']];
    $max      = (int) $this->layoutManager->config('max_region_count');
    $url      = '/admin/config/media/blazy';

    $this->checkDefinition($settings, $options);

    if ($this->layoutManager->moduleExists('blazy_ui')) {
      $url = Url::fromUri('internal:/admin/config/media/blazy')->toString();
    }

    if ($max < 10) {
      $max = 20;
    }

    $options = range(1, $max);
    $data = [
      'max' => $max,
      'url' => $url,
    ];
    $elements['count'] = [
      '#type'        => 'select',
      '#title'       => $this->t('Region count'),
      '#options'     => array_combine($options, $options),
      '#description' => $this->description($data)['count'],
    ];

    $elements['style'] = [
      '#type'        => 'select',
      '#title'       => $this->t('Layout engine'),
      '#options'     => $this->blazyManager->getStyles(),
      '#description' => $this->openingDescriptions()['style'],
    ];

    foreach (array_keys($elements) as $key) {
      if ($excludes && in_array($key, $excludes)) {
        unset($elements[$key]);
        continue;
      }
      $elements[$key]['#default_value'] = $settings[$key] ?? '';
      $elements[$key]['#attributes'] = $attrs;
      $elements[$key]['#required'] = TRUE;
      $elements[$key]['#weight'] = 20;
    }

    $form += $elements;
  }

  /**
   * {@inheritdoc}
   */
  public function formStyles(array &$form, array $settings, array $options = []): void {
    $this->checkDefinition($settings, $options);

    $excludes = $options['excludes'] ?? [];
    $attrs    = ['class' => ['is-tooltip']];
    $region   = $settings['rid'] ?? NULL;

    if ($region) {
      $parents = ['layout_settings', 'regions', $region, 'settings', 'styles'];
    }
    else {
      $parents = ['layout_settings', 'settings', 'styles'];
    }

    $form['styles'] = [
      '#type'       => 'details',
      '#tree'       => TRUE,
      '#open'       => FALSE,
      '#title'      => $this->t('Styles'),
      '#parents'    => $parents,
      '#weight'     => 10,
      '#attributes' => [
        'class' => [
          'form-wrapper--b-layout-styles',
        ],
        'data-b-region' => $region ?: 'bg',
      ],
    ];

    // Colors.
    $form_id = 'colors';
    $form['styles'][$form_id] = [
      '#type'        => 'details',
      '#tree'        => TRUE,
      '#open'        => TRUE,
      '#title'       => $this->t('Colors'),
      '#parents'     => array_merge($parents, [$form_id]),
      '#description' => $this->description()['colors'],
    ];

    /** @var array $colors */
    $colors = &$form['styles'][$form_id];

    foreach (array_keys(Defaults::styleSettings()) as $key) {
      $type  = strpos($key, '_color') ? 'color' : 'range';
      $title = str_replace('_', ' ', $key);

      $colors[$key] = [
        '#type'  => $type,
        '#title' => $this->t('@title', ['@title' => ucfirst($title)]),
      ];
    }

    foreach ($this->layoutManager->getKeys($colors) as $key) {
      if ($excludes && in_array($key, $excludes)) {
        unset($colors[$key]);
        continue;
      }

      $value = $settings[$form_id][$key] ?? '';

      if (is_array($colors[$key]) && $colors[$key]['#type'] == 'range') {
        $colors[$key]['#min'] = 0;
        $colors[$key]['#max'] = 1;
        $colors[$key]['#step'] = 0.1;
        $colors[$key]['#field_suffix'] = $value ?: ' ';
        $attrs['data-b-prop'] = 'opacity';
      }

      if ($colors[$key]['#type'] == 'color') {
        $colors[$key]['#field_suffix'] = $value ?: ' ';

        $bg = strpos($key, 'background') !== FALSE || strpos($key, 'overlay') !== FALSE;
        $attrs['data-b-prop'] = $bg ? 'background-color' : 'color';
      }

      $colors[$key]['#default_value'] = $value;
      $colors[$key]['#attributes'] = $attrs;
    }

    // Layouts.
    $form_id = 'layouts';
    $form['styles'][$form_id] = [
      '#type'    => 'details',
      '#tree'    => TRUE,
      '#open'    => TRUE,
      '#title'   => $this->t('Layouts'),
      '#parents' => array_merge($parents, [$form_id]),
    ];

    $layouts = &$form['styles'][$form_id];
    foreach (Defaults::sublayoutSettings() as $key => $value) {
      $type  = is_bool($value) ? 'checkbox' : 'textfield';
      $title = str_replace('_', ' ', $key);

      $description = '';
      if ($key == 'ete') {
        $title = 'Edge to edge';
        $description = $this->description()['ete'];
      }
      elseif ($key == 'padding') {
        $description = $this->description()['padding'];
      }
      elseif ($key == 'max_width') {
        $description = $this->description()['max_width'];
      }
      elseif ($key == 'gapless') {
        $description = $this->description()['gapless'];
      }

      $layouts[$key] = [
        '#type'        => $type,
        '#title'       => $this->t('@title', ['@title' => ucfirst($title)]),
        '#description' => $description,
      ];
    }

    foreach ($this->layoutManager->getKeys($layouts) as $key) {
      if ($excludes && in_array($key, $excludes)) {
        unset($layouts[$key]);
        continue;
      }

      $attrs['data-b-prop'] = str_replace('_', '', $key);
      $layouts[$key]['#default_value'] = $settings[$form_id][$key] ?? '';
      $layouts[$key]['#attributes'] = $attrs;
    }

    // Media.
    $form_id = 'media';
    $help = '/admin/help/blazy_layout';
    $exists = $this->layoutManager->moduleExists('media_library_form_element');

    if ($this->layoutManager->moduleExists('help')) {
      $help = Url::fromUri('internal:/admin/help/blazy_layout')->toString();
    }

    $form['styles'][$form_id] = [
      '#type'    => 'details',
      '#tree'    => TRUE,
      '#open'    => TRUE,
      '#title'   => $this->t('Media'),
      '#parents' => array_merge($parents, [$form_id]),
    ];

    $media = &$form['styles'][$form_id];
    $data = ['help' => $help];
    $description = $this->description($data)['media'];

    if (!$exists) {
      $description .= $this->description($data)['mlfe'];
    }

    $media['#description'] = $description;

    $this->baseImageForm($media, $this->definition);

    // Remove irrelevant link to content as it is itself.
    // @todo unset($media['media_switch']['#options']['content']);
    foreach (Defaults::layoutMediaSettings() as $key => $value) {
      $type = is_bool($value) ? 'checkbox' : 'select';
      $title = str_replace('_', ' ', $key);
      $value = $settings[$form_id][$key] ?? $value;
      $description = '';
      $weight = NULL;
      $self = FALSE;

      // @todo remove deprecated option after an update.
      if ($key == 'use_player') {
        continue;
      }

      // BC for use_player.
      if ($key == 'media_switch' && !empty($settings[$form_id]['use_player'])) {
        $value = 'media';
      }

      if ($key == 'id') {
        $self = TRUE;
        $title = 'background media';
        $type = $exists ? 'media_library' : 'textfield';
        $weight = -110;
      }
      elseif ($key == 'background') {
        $weight = -108;
      }
      elseif ($key == 'link') {
        $description = $this->description($data)['link'];
      }

      if ($self) {
        $media[$key]['#type'] = $type;
        $media[$key]['#title'] = $this->t('@title', ['@title' => ucfirst($title)]);
      }

      if ($description) {
        $media[$key]['#description'] = $description;
      }

      if ($type == 'select') {
        $media[$key]['#empty_option'] = $this->t('- None -');
      }

      if ($weight) {
        $media[$key]['#weight'] = $weight;
      }

      if ($key == 'id') {
        $value = $settings[$form_id]['media_library_selection'] ?? $value;
        if ($exists) {
          // @todo add options to avoid hard-coded bundles.
          $media[$key]['#allowed_bundles'] = ['image', 'video', 'remote_video'];
          $media[$key]['#cardinality'] = 1;
        }
        else {
          $media[$key]['#disabled'] = TRUE;
        }
      }

      $attrs['data-b-prop'] = str_replace('_', '', $key);

      $media[$key]['#default_value'] = $value;
      $media[$key]['#attributes'] = $attrs;
    }

    // Add tabs for readability.
    $this->tabify($form, 'styles', $region);
  }

  /**
   * {@inheritdoc}
   *
   * @todo refine and merge with self::formWrappers().
   */
  public function formSettings(array &$form, array $settings, array $options = []): void {
    $excludes    = $options['excludes'] ?? [];
    $defaults    = Defaults::layoutSettings();
    $admin_css   = $this->layoutManager->config('admin_css', 'blazy.settings');
    $attrs       = ['class' => ['is-tooltip']];
    $bottoms     = ['align_items', 'grid_auto_rows'];
    $elements    = $options = [];
    $description = '';

    foreach ($defaults as $key => $value) {
      if ($excludes && in_array($key, $excludes)) {
        continue;
      }

      switch ($key) {
        case 'wrapper':
          $options = Defaults::mainWrapperOptions();
          $description = '';
          break;

        case 'classes':
          $options = [];
          $description = $this->description()['classes'];
          break;

        case 'align_items':
          $options = Defaults::alignItems();
          $description = $this->description()['align_items'];
          break;

        case 'grid_auto_rows':
          $options = [];
          $description = $this->description()['grid_auto_rows'];
          break;

        default:
          $options = [];
          $description = '';
          break;
      }

      $type = is_bool($value) ? 'checkbox' : 'textfield';
      if ($options) {
        $type = 'select';
      }

      if ($key == 'id') {
        $type = 'hidden';
      }

      $title = str_replace('_', ' ', $key);

      if ($type !== 'hidden') {
        $elements[$key] = [
          '#title'       => $this->t('@title', ['@title' => ucfirst($title)]),
          '#description' => $description,
          '#attributes'  => $attrs,
          '#required'    => $key == 'wrapper',
        ];

        if ($options) {
          $elements[$key]['#options'] = $options;
          if (empty($elements[$key]['#required'])) {
            $elements[$key]['#empty_option'] = $this->t('- None -');
          }
        }
      }

      $elements[$key]['#type'] = $type;
    }

    // Defines the default values if available.
    foreach ($elements as $name => $element) {
      $type     = $element['#type'];
      $fallback = $type == 'checkbox' ? FALSE : '';
      $value    = $defaults[$name] ?? $fallback;

      if (is_array($value)) {
        continue;
      }

      // Stupid, but in case more stupidity gets in the way.
      if ($type == 'textfield') {
        $value = is_string($value) ? strip_tags($value) : $value;
        $elements[$name]['#size'] = 20;
        $elements[$name]['#maxlength'] = 255;
      }

      $elements[$name]['#default_value'] = $settings[$name] ?? $value;
      if ($type !== 'hidden') {
        $elements[$name]['#attributes']['class'][] = 'is-tooltip';

        if ($admin_css) {
          if ($type == 'checkbox') {
            $elements[$name]['#title_display'] = 'before';
          }

          foreach ($bottoms as $key) {
            if (!isset($elements[$key]['#wrapper_attributes'])) {
              $elements[$key]['#wrapper_attributes'] = [];
            }

            $wattrs = &$elements[$key]['#wrapper_attributes'];
            $wattrs['class'][] = 'b-tooltip__bottom';
          }
        }
      }
    }

    $form += $elements;
  }

  /**
   * {@inheritdoc}
   */
  public function formWrappers(
    array &$form,
    array $settings,
    array $options = [],
    $root = TRUE,
  ): void {
    $excludes = $options['excludes'] ?? [];
    $attrs    = ['class' => ['is-tooltip']];
    $elements = [];

    $elements['wrapper'] = [
      '#type'     => 'select',
      '#options'  => $root ? Defaults::mainWrapperOptions() : Defaults::regionWrapperOptions(),
      '#required' => TRUE,
      '#title'    => $this->t('Wrapper'),
    ];

    $elements['attributes'] = [
      '#type'        => 'textfield',
      '#title'       => $this->t('Attributes'),
      '#description' => $this->description()['attributes'],
      '#access'      => FALSE,
    ];

    $elements['classes'] = [
      '#type'        => 'textfield',
      '#title'       => $this->t('Classes'),
      '#description' => $this->description()['subclasses'],
    ];

    $elements['row_classes'] = [
      '#type'        => 'textfield',
      '#title'       => $this->t('Row classes'),
      '#description' => $this->description()['row_classes'],
      '#access'      => FALSE,
    ];

    foreach (array_keys($elements) as $key) {
      if ($excludes && in_array($key, $excludes)) {
        unset($elements[$key]);
        continue;
      }

      $value = $settings[$key] ?? '';
      $elements[$key]['#default_value'] = $value ? Xss::filter($value) : '';
      $elements[$key]['#attributes'] = $attrs;
    }

    $form += $elements;
  }

  /**
   * Checks for definition.
   */
  protected function checkDefinition(array $settings, array $options): void {
    $definition = [
      'background' => TRUE,
      'multimedia' => TRUE,
      'responsive_image' => TRUE,
      'no_box_caption_custom' => TRUE,
      'no_loading' => TRUE,
      'no_preload' => TRUE,
      'namespace' => 'blazy',
    ];

    // @todo add option to choose either Media or Content type.
    $media_bundles = [];
    foreach (['image', 'video', 'remote_video'] as $key) {
      $media_bundles = [$key => ['label' => ucfirst($key)]];
    }

    $names = ['text', 'string', 'link'];
    $links = $this->getFieldOptionsWithBundles($media_bundles, $names, 'media');

    $definition['links'] = $links;

    $settings['media_switch'] = '';
    $definition['settings'] = $settings;
    $this->toScopes($definition);
  }

  /**
   * Checks for valid color excluding black (#000000) by design.
   */
  protected function getColor($key, array $settings) {
    $colors = $settings['styles'];
    return !empty($colors[$key]) && $colors[$key] != '#000000' ? $colors[$key] : FALSE;
  }

}
