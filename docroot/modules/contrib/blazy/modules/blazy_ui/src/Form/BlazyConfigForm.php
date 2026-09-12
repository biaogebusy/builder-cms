<?php

namespace Drupal\blazy_ui\Form;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\blazy\BlazyApi;
use Drupal\blazy\Form\BlazyConfigFormBase;

/**
 * Defines blazy admin config form.
 */
class BlazyConfigForm extends BlazyConfigFormBase {

  use TraitConfigDescriptions;

  /**
   * {@inheritdoc}
   */
  protected $validatedOptions = [
    'css_scope',
    'placeholder',
    'unstyled_extensions',
    ['blazy', 'container'],
    ['blazy', 'offset'],
    ['blazy', 'saveViewportOffsetDelay'],
    ['blazy', 'validateDelay'],
    ['io', 'rootMargin'],
    ['io', 'threshold'],
    'extras',
  ];

  /**
   * {@inheritdoc}
   */
  protected $validatedPaths = [
    'placeholder',
  ];

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'blazy_settings_form';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['blazy.settings'];
  }

  /**
   * Implements \Drupal\Core\Form\FormInterface::buildForm().
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('blazy.settings');
    $doms = ['DOMPurify', 'dompurify'];
    $dom_exists = $exists = $this->manager->getLibrariesPath($doms);
    $svg_exists = $exists = BlazyApi::svgSanitizerExists();
    $svg_sanitizer = 'https://github.com/darylldoyle/svg-sanitizer';
    $class = $exists ? 'info' : 'warning';
    $hints = [];
    $help = '/admin/help/blazy_ui';
    $bl_help = '/admin/help/blazy_layout';
    $bl_exists = $this->manager->moduleExists('blazy_layout');

    if ($this->manager->moduleExists('help')) {
      $help = Url::fromUri('internal:/admin/help/blazy_ui')->toString();
      $bl_help = Url::fromUri('internal:/admin/help/blazy_layout')->toString();
    }

    // Adapted from Colorbox module, thanks.
    $dom_text = $dom_exists ?
      '[&check;] ' . $this->t(
        'The DOMPurify library is installed to sanitize lightbox captions. [<a href=":ui">Blazy help</a>]',
        [
          ':ui' => $help . '#dompurify',
        ]
      )
      :
      '[&cross;] ' . $this->t(
        '<b>Warning!</b> The <a href=":url">DOMPurify</a> library is not installed; required for HTML in lightbox captions. Without it, they are only sanitized server-side, or builtin. [<a href=":ui">Blazy help</a>].',
        [
          ':url' => 'https://github.com/cure53/DOMPurify/archive/main.zip',
          ':ui' => $help . '#dompurify',
        ]
      );

    $hints[] = [
      '#theme' => 'container',
      '#children' => ['#markup' => $dom_text],
    ];

    $svg_text = $svg_exists ?
      '[&check;] ' . $this->t(
        'The SVG Sanitizer library is installed to sanitize inline SVG. [<a href=":ui">Blazy help</a>]',
        [
          ':ui' => $help . '#svg',
        ]
      )
      :
      '[&cross;] ' . $this->t(
        '<b>Warning!</b> The <a href=":url">SVG Sanitizer</a> library is not installed; required to use SVG inline.  [<a href=":ui">Blazy help</a>].',
        [
          ':url' => $svg_sanitizer,
          ':ui' => $help . '#svg',
        ]
      );

    $hints[] = [
      '#theme' => 'container',
      '#children' => ['#markup' => $svg_text],
    ];

    $form['library_hints'] = [
      '#type' => 'container',
      'items' => $hints,
      '#attributes' => [
        'class' => [
          'messages-list__item',
          'messages',
          'messages--' . $class,
        ],
      ],
      '#wrapper_attributes' => ['class' => ['messages-list']],
    ];

    $data = [
      'help' => $help,
      'bl_exists' => $bl_exists,
      'bl_help' => $bl_help,
    ];
    $descriptions = $this->description($data);

    $form['admin_css'] = [
      '#type'          => 'checkbox',
      '#title'         => $this->t('Admin CSS'),
      '#default_value' => $config->get('admin_css'),
    ];

    $form['use_oembed'] = [
      '#type'          => 'checkbox',
      '#title'         => $this->t('Use oEmbed'),
      '#default_value' => $config->get('use_oembed'),
    ];

    $form['privacy_consent'] = [
      '#type'          => 'checkbox',
      '#title'         => $this->t('Use Privacy-Enhanced YouTube domain (WIP, not working, Experimental)'),
      '#default_value' => $config->get('privacy_consent'),
    ];

    $form['lazy_html'] = [
      '#type'          => 'checkbox',
      '#title'         => $this->t('Lazy load HTML (Experimental)'),
      '#default_value' => $config->get('lazy_html'),
    ];

    $form['use_encodedbox'] = [
      '#type'          => 'checkbox',
      '#title'         => $this->t('Use encoding for HTML (Experimental)'),
      '#default_value' => $config->get('use_encodedbox'),
    ];

    $form['ratio_modern'] = [
      '#type'          => 'checkbox',
      '#title'         => $this->t('Use modern CSS aspect-ratio (Experimental)'),
      '#default_value' => $config->get('ratio_modern'),
    ];

    $nojs = $config->get('nojs');
    $form['nojs'] = [
      '#type'          => 'checkboxes',
      '#title'         => $this->t('No JavaScript'),
      '#empty_option'  => '- None -',
      '#options' => [
        'lazy' => $this->t('Lazyload'),
        'polyfill' => $this->t('Basic polyfills (ie9-ie11)'),
        'classlist' => $this->t('classList polyfill (ie9-ie11)'),
        'promise' => $this->t('Promise polyfill (ie11)'),
        'raf' => $this->t('requestAnimationFrame polyfill (ie9)'),
        'webp' => $this->t('webp fallback (ie9-ie11, old Safari)'),
      ],
      '#default_value' => !empty($nojs) ? array_values((array) $nojs) : [],
    ];

    $form['noscript'] = [
      '#type'          => 'checkbox',
      '#title'         => $this->t('Add noscript'),
      '#default_value' => $config->get('noscript'),
    ];

    $form['one_pixel'] = [
      '#type'          => 'checkbox',
      '#title'         => $this->t('Responsive image 1px placeholder'),
      '#default_value' => $config->get('one_pixel'),
    ];

    $form['visible_class'] = [
      '#type'          => 'checkbox',
      '#title'         => $this->t('Add is-b-visible class'),
      '#default_value' => $config->get('visible_class'),
    ];

    $form['wrapper_class'] = [
      '#type'          => 'checkbox',
      '#title'         => $this->t('Remove field/ view wrapper classes'),
      '#default_value' => $config->get('wrapper_class'),
    ];

    $form['placeholder'] = [
      '#type'          => 'textfield',
      '#title'         => $this->t('Placeholder'),
      '#default_value' => $config->get('placeholder'),
    ];

    $form['unstyled_extensions'] = [
      '#type'          => 'textfield',
      '#title'         => $this->t('Extensions without image styles'),
      '#default_value' => $config->get('unstyled_extensions'),
    ];

    $fx = $this->manager->getImageEffects();
    $form['fx'] = [
      '#type'          => 'select',
      '#title'         => $this->t('Image effect'),
      '#empty_option'  => '- None -',
      '#options'       => array_combine($fx, $fx),
      '#default_value' => $config->get('fx'),
    ];

    $form['blur_client'] = [
      '#type'          => 'checkbox',
      '#title'         => $this->t('Use client-side blur'),
      '#default_value' => $config->get('blur_client'),
    ];

    $form['blur_storage'] = [
      '#type'          => 'checkbox',
      '#title'         => $this->t('Store blur in localStorage'),
      '#default_value' => $config->get('blur_storage'),
    ];

    $form['blur_minwidth'] = [
      '#type'          => 'number',
      '#title'         => $this->t('Blur min-width'),
      '#default_value' => $config->get('blur_minwidth') ?: 0,
      '#maxlength'     => 4,
      '#field_suffix'  => 'px',
    ];

    foreach (['client', 'storage', 'minwidth'] as $key) {
      $form['blur_' . $key]['#states'] = [
        'visible' => [
          'select[name="fx"]' => ['value' => 'blur'],
        ],
      ];
      if ($key == 'storage') {
        $form['blur_' . $key]['#states']['visible'][] = [
          'input[name="blur_client"]' => [
            'checked' => TRUE,
          ],
        ];
      }
    }

    foreach ($descriptions as $key => $description) {
      if (isset($form[$key])) {
        $form[$key]['#description'] = $description;
      }
    }

    $form['blazy'] = [
      '#type'        => 'details',
      '#tree'        => TRUE,
      '#open'        => FALSE,
      '#title'       => $this->t('Blazy settings'),
      '#description' => $descriptions['blazy'],
    ];

    $form['blazy']['loadInvisible'] = [
      '#type'          => 'checkbox',
      '#title'         => $this->t('Load invisible'),
      '#default_value' => $config->get('blazy.loadInvisible'),
    ];

    $form['blazy']['offset'] = [
      '#type'          => 'textfield',
      '#title'         => $this->t('Offset'),
      '#default_value' => $config->get('blazy.offset'),
      '#field_suffix'  => 'px',
      '#maxlength'     => 5,
      '#size'          => 10,
    ];

    $form['blazy']['saveViewportOffsetDelay'] = [
      '#type'          => 'textfield',
      '#title'         => $this->t('Save viewport offset delay'),
      '#default_value' => $config->get('blazy.saveViewportOffsetDelay'),
      '#field_suffix'  => 'ms',
      '#maxlength'     => 5,
      '#size'          => 10,
    ];

    $form['blazy']['validateDelay'] = [
      '#type'          => 'textfield',
      '#title'         => $this->t('Set validate delay'),
      '#default_value' => $config->get('blazy.validateDelay'),
      '#field_suffix'  => 'ms',
      '#maxlength'     => 5,
      '#size'          => 10,
    ];

    $form['blazy']['container'] = [
      '#type'          => 'textfield',
      '#title'         => $this->t('Scrolling container'),
      '#default_value' => $config->get('blazy.container'),
    ];

    foreach (array_keys($form['blazy']) as $key) {
      if (isset($form['blazy'][$key])
        && $description = $this->description($data)[$key] ?? NULL) {
        $form['blazy'][$key]['#description'] = $description;
      }
    }

    $form['io'] = [
      '#type'        => 'details',
      '#tree'        => TRUE,
      '#open'        => FALSE,
      '#title'       => $this->t('Intersection Observer API (IO) settings'),
      '#description' => $descriptions['io'],
    ];

    $form['io']['unblazy'] = [
      '#type'          => 'checkbox',
      '#title'         => $this->t('Unload bLazy'),
      '#default_value' => $config->get('io.unblazy'),
    ];

    $form['io']['rootMargin'] = [
      '#type'          => 'textfield',
      '#title'         => $this->t('rootMargin'),
      '#default_value' => $config->get('io.rootMargin') ?: '0px',
      '#maxlength'     => 120,
      '#size'          => 20,
    ];

    $form['io']['threshold'] = [
      '#type'          => 'textfield',
      '#title'         => $this->t('threshold'),
      '#default_value' => $config->get('io.threshold') ?: '0',
      '#maxlength'     => 120,
      '#size'          => 20,
    ];

    $form['io']['disconnect'] = [
      '#type'          => 'checkbox',
      '#title'         => $this->t('Disconnect'),
      '#default_value' => $config->get('io.disconnect'),
    ];

    foreach (array_keys($form['io']) as $key) {
      if (isset($form['io'][$key])
        && $description = $this->description($data)[$key] ?? NULL) {
        $form['io'][$key]['#description'] = $description;
      }
    }

    $form['blazy_layout'] = [
      '#type'        => 'details',
      '#tree'        => FALSE,
      '#open'        => TRUE,
      '#title'       => $this->t('Blazy Layout settings'),
      '#description' => $descriptions['blazy_layout'],
    ];

    $form['blazy_layout']['use_custom_css'] = [
      '#type'          => 'checkbox',
      '#title'         => $this->t('Allow custom inline CSS'),
      '#default_value' => $config->get('use_custom_css'),
      '#disabled'      => !$bl_exists,
    ];

    $form['blazy_layout']['css_scope'] = [
      '#type'          => 'textfield',
      '#title'         => $this->t('CSS selector scope'),
      '#default_value' => $config->get('css_scope'),
      '#disabled'      => !$bl_exists,
      '#states'        => [
        'visible' => [
          'input[name="use_custom_css"]' => ['checked' => TRUE],
        ],
      ],
    ];

    $form['blazy_layout']['max_region_count'] = [
      '#type'          => 'number',
      '#title'         => $this->t('Max region count'),
      '#default_value' => $config->get('max_region_count'),
      '#disabled'      => !$bl_exists,
    ];

    foreach (array_keys($form['blazy_layout']) as $key) {
      if (isset($form['blazy_layout'][$key])
        && $description = $this->description($data)[$key] ?? NULL) {
        $form['blazy_layout'][$key]['#description'] = $description;
      }
    }

    // Allows sub-modules to provide its own settings.
    $form['extras'] = [
      '#type'   => 'details',
      '#open'   => FALSE,
      '#tree'   => TRUE,
      '#title'  => $this->t('Extra settings'),
      '#access' => FALSE,
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * Implements \Drupal\Core\Form\FormInterface::submitForm().
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $config = $this->configFactory->getEditable('blazy.settings');
    $config
      ->set('admin_css', $form_state->getValue('admin_css'))
      ->set('use_custom_css', $form_state->getValue('use_custom_css'))
      ->set('css_scope', $form_state->getValue('css_scope'))
      ->set('lazy_html', $form_state->getValue('lazy_html'))
      ->set('nojs', $form_state->getValue('nojs'))
      ->set('fx', $form_state->getValue('fx'))
      ->set('blur_client', $form_state->getValue('blur_client'))
      ->set('blur_storage', $form_state->getValue('blur_storage'))
      ->set('blur_minwidth', $form_state->getValue('blur_minwidth'))
      ->set('noscript', $form_state->getValue('noscript'))
      ->set('one_pixel', $form_state->getValue('one_pixel'))
      ->set('visible_class', $form_state->getValue('visible_class'))
      ->set('wrapper_class', $form_state->getValue('wrapper_class'))
      ->set('placeholder', $form_state->getValue('placeholder'))
      ->set('unstyled_extensions', $form_state->getValue('unstyled_extensions'))
      ->set('use_encodedbox', $form_state->getValue('use_encodedbox'))
      ->set('use_oembed', $form_state->getValue('use_oembed'))
      ->set('privacy_consent', $form_state->getValue('privacy_consent'))
      ->set('ratio_modern', $form_state->getValue('ratio_modern'))
      ->set('max_region_count', $form_state->getValue('max_region_count'))
      ->set('blazy.loadInvisible', $form_state->getValue([
        'blazy',
        'loadInvisible',
      ]))
      ->set('blazy.offset', $form_state->getValue(['blazy', 'offset']))
      ->set('blazy.saveViewportOffsetDelay', $form_state->getValue([
        'blazy',
        'saveViewportOffsetDelay',
      ]))
      ->set('blazy.validateDelay', $form_state->getValue([
        'blazy',
        'validateDelay',
      ]))
      ->set('blazy.container', $form_state->getValue(['blazy', 'container']))
      ->set('io.unblazy', $form_state->getValue(['io', 'unblazy']))
      ->set('io.rootMargin', $form_state->getValue(['io', 'rootMargin']))
      ->set('io.threshold', $form_state->getValue(['io', 'threshold']))
      ->set('io.disconnect', $form_state->getValue(['io', 'disconnect']));

    if ($form_state->hasValue('extras')) {
      foreach ($form_state->getValue('extras') as $key => $value) {
        $config->set('extras.' . $key, $value);
      }
    }

    $config->save();

    // Invalidate the library discovery cache to update the responsive image.
    // @todo use LibraryDiscoveryCollector::clear() for D12.
    // $this->libraryDiscovery->clearCachedDefinitions();
    $this->configFactory->clearStaticCache();

    $this->messenger()->addMessage($this->t('Be sure to <a href=":clear_cache">clear the cache</a> if trouble to see the updated settings.', [
      ':clear_cache' => Url::fromRoute('system.performance_settings')->toString(),
    ]));

    parent::submitForm($form, $form_state);
  }

}
