<?php

namespace Drupal\bootstrap_styles\Plugin\BootstrapStyles\Style;

use Drupal\bootstrap_styles\Style\StylePluginBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Ajax\HtmlCommand;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\media\Entity\Media;
use Drupal\file\Entity\File;

/**
 * Class BackgroundMedia.
 *
 * @package Drupal\bootstrap_styles\Plugin\Style
 *
 * @Style(
 *   id = "background_media",
 *   title = @Translation("Background Media"),
 *   group_id = "background",
 *   weight = 2
 * )
 */
class BackgroundMedia extends StylePluginBase implements ContainerFactoryPluginInterface {

  /**
   * The entity type bundle info.
   *
   * @var \Drupal\Core\Entity\EntityTypeBundleInfoInterface
   */
  protected $entityTypeBundleInfo;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Constructs a BackgroundMedia object.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin ID for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The configuration factory.
   * @param \Drupal\Core\Entity\EntityTypeBundleInfoInterface $entity_type_bundle_info
   *   The entity type bundle info.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, ConfigFactoryInterface $config_factory, EntityTypeBundleInfoInterface $entity_type_bundle_info, EntityTypeManagerInterface $entity_type_manager) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $config_factory);
    $this->entityTypeBundleInfo = $entity_type_bundle_info;
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('config.factory'),
      $container->get('entity_type.bundle.info'),
      $container->get('entity_type.manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $config = $this->config();

    // Background image media bundle.
    $media_bundles = [];
    $media_bundles_info = $this->entityTypeBundleInfo->getBundleInfo('media');
    // Ignore if match any of the following names.
    $disabled_bundles = [
      'audio',
      'audio_file',
      'instagram',
      'tweet',
      'document',
      'remote_video',
    ];

    foreach ($media_bundles_info as $key => $bundle) {
      if (!in_array($key, $disabled_bundles)) {
        $media_bundles[$key] = $bundle['label'] . ' (' . $key . ')';
      }
    }

    $form['background']['background_image_bundle'] = [
      '#type' => 'select',
      '#title' => $this->t('Image background media bundle'),
      '#options' => $media_bundles,
      '#description' => $this->t('Image background media entity bundle.'),
      '#default_value' => $config->get('background_image.bundle'),
      '#ajax' => [
        'callback' => __CLASS__ . '::getFields',
        'event' => 'change',
        'method' => 'html',
        'wrapper' => 'media_image_bundle_fields',
        'progress' => [
          'type' => 'throbber',
          'message' => NULL,
        ],
      ],
    ];

    $form['background']['background_image_field'] = [
      '#type' => 'select',
      '#title' => $this->t('Image background media field'),
      '#options' => $this->getFieldsByBundle($config->get('background_image.bundle')),
      '#description' => $this->t('Image background media entity field.'),
      '#default_value' => $config->get('background_image.field'),
      '#attributes' => ['id' => 'media_image_bundle_fields'],
      '#validated' => TRUE,
    ];

    $form['background']['background_local_video_bundle'] = [
      '#type' => 'select',
      '#title' => $this->t('Local video background media bundle'),
      '#options' => $media_bundles,
      '#description' => $this->t('Background for local video media entity bundle.'),
      '#default_value' => $config->get('background_local_video.bundle'),
      '#ajax' => [
        'callback' => __CLASS__ . '::getFields',
        'event' => 'change',
        'method' => 'html',
        'wrapper' => 'media_local_video_bundle_fields',
        'progress' => [
          'type' => 'throbber',
          'message' => NULL,
        ],
      ],
    ];

    $form['background']['background_local_video_field'] = [
      '#type' => 'select',
      '#title' => $this->t('Local video background media field'),
      '#options' => $this->getFieldsByBundle($config->get('background_local_video.bundle')),
      '#description' => $this->t('Local video background media entity field.'),
      '#default_value' => $config->get('background_local_video.field'),
      '#attributes' => ['id' => 'media_local_video_bundle_fields'],
      '#validated' => TRUE,
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function getFieldsByBundle($bundle) {
    $field_map = \Drupal::service('entity_field.manager')->getFieldMap();
    $media_field_map = $field_map['media'];
    $fields = [];
    foreach ($media_field_map as $field_name => $field_info) {
      if (
        in_array($bundle, $field_info['bundles']) &&
        in_array($field_info['type'], ['image', 'file']) &&
        $field_name !== 'thumbnail'
      ) {
        $fields[$field_name] = $field_name;
      }
    }
    return $fields;
  }

  /**
   * {@inheritdoc}
   */
  public function getFields(array &$element, FormStateInterface $form_state) {
    $triggering_element = $form_state->getTriggeringElement();
    $bundle = $triggering_element['#value'];
    $wrapper_id = $triggering_element["#ajax"]["wrapper"];
    $rendered_field = '';

    $field_map = \Drupal::service('entity_field.manager')->getFieldMap();
    $media_field_map = $field_map['media'];

    foreach ($media_field_map as $field_name => $field_info) {
      if (
        in_array($bundle, $field_info['bundles']) &&
        in_array($field_info['type'], ['image', 'file']) &&
        $field_name !== 'thumbnail'
      ) {
        $rendered_field .= '<option value="' . $field_name . '">' . $field_name . '</option>';
      }
    }

    $response = new AjaxResponse();
    $response->addCommand(new HtmlCommand('#' . $wrapper_id, $rendered_field));
    return $response;
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    $this->config()
      ->set('background_image.bundle', $form_state->getValue('background_image_bundle'))
      ->set('background_image.field', $form_state->getValue('background_image_field'))
      ->set('background_local_video.bundle', $form_state->getValue('background_local_video_bundle'))
      ->set('background_local_video.field', $form_state->getValue('background_local_video_field'))
      ->save();
  }

  /**
   * {@inheritdoc}
   */
  public function buildStyleFormElements(array &$form, FormStateInterface $form_state, $storage) {
    $icon_path = \Drupal::service('extension.list.module')->getPath('bootstrap_styles') . '/images/';
    $form['background_type']['#options']['image'] = $this->getSvgIconMarkup($icon_path . 'plugins/background/background-image.svg');
    $form['background_type']['#options']['video'] = $this->getSvgIconMarkup($icon_path . 'plugins/background/background-video.svg');
    if (!$form['background_type']['#default_value']) {
      if (isset($storage['background']) && isset($storage['background']['background_type'])) {
        if ($storage['background']['background_type'] == 'image') {
          $form['background_type']['#default_value'] = 'image';
        }
        elseif ($storage['background']['background_type'] == 'video') {
          $form['background_type']['#default_value'] = 'video';
        }
      }
    }

    // Background media.
    $config = $this->config();
    // Check if the bundle exist.
    if ($config->get('background_image.bundle') && $this->entityTypeManager->getStorage('media_type')->load($config->get('background_image.bundle'))) {
      $form['background_image'] = [
        '#type' => 'media_library',
        '#title' => $this->t('Background image'),
        '#description' => $this->t('Background image'),
        '#allowed_bundles' => [$config->get('background_image.bundle')],
        '#default_value' => $storage['background_media']['image']['media_id'] ?? NULL,
        '#states' => [
          'visible' => [
            ':input.bs_background--type' => ['value' => 'image'],
          ],
        ],
      ];
    }
    // Check if the bundle exist.
    if ($config->get('background_local_video.bundle') && $this->entityTypeManager->getStorage('media_type')->load($config->get('background_local_video.bundle'))) {
      $form['background_video'] = [
        '#type' => 'media_library',
        '#title' => $this->t('Background video'),
        '#description' => $this->t('Background video'),
        '#allowed_bundles' => [$config->get('background_local_video.bundle')],
        '#default_value' => $storage['background_media']['video']['media_id'] ?? NULL,
        '#states' => [
          'visible' => [
            ':input.bs_background--type' => ['value' => 'video'],
          ],
        ],
      ];
    }

    $form['background_options'] = [
      '#type' => 'container',
      '#attributes' => [
        'class' => ['bs_background--options bs_row'],
      ],
      '#states' => [
        'visible' => [
          ':input.bs_background--type' => ['value' => 'image'],
        ],
      ],
    ];

    $form['background_options']['background_position'] = [
      '#type' => 'radios',
      '#title' => $this->t('Position'),
      '#options' => [
        'left top' => $this->t('Left Top'),
        'center top' => $this->t('Center Top'),
        'right top' => $this->t('Right Top'),
        'left center' => $this->t('Left Center'),
        'center' => $this->t('Center'),
        'right center' => $this->t('Right Center'),
        'left bottom' => $this->t('Left Bottom'),
        'center bottom' => $this->t('Center Bottom'),
        'right bottom' => $this->t('Right Bottom'),
      ],
      '#default_value' => $storage['background_media']['background_options']['background_position'] ?? 'center',
      '#attributes' => [
        'class' => ['bs_background--position bs_col bs_col--50'],
      ],
    ];

    $form['background_options']['background_repeat'] = [
      '#type' => 'radios',
      '#title' => $this->t('Repeat'),
      '#options' => [
        'no-repeat' => $this->getSvgIconMarkup($icon_path . 'plugins/background-repeat/background-no-repeat.svg'),
        'repeat' => $this->getSvgIconMarkup($icon_path . 'plugins/background-repeat/background-repeat.svg'),
        'repeat-x' => $this->getSvgIconMarkup($icon_path . 'plugins/background-repeat/background-repeat-xy.svg'),
        'repeat-y' => $this->getSvgIconMarkup($icon_path . 'plugins/background-repeat/background-repeat-xy.svg'),
      ],
      '#default_value' => $storage['background_media']['background_options']['background_repeat'] ?? 'no-repeat',
      '#attributes' => [
        'class' => ['bs_background--repeat bs_col bs_col--50'],
      ],
    ];

    $form['background_options']['background_attachment'] = [
      '#type' => 'radios',
      '#title' => $this->t('Attachment'),
      '#options' => [
        'not_fixed' => $this->t('Not Fixed'),
        'fixed' => $this->t('Fixed'),
      ],
      '#default_value' => $storage['background_media']['background_options']['background_attachment'] ?? 'not_fixed',
      '#attributes' => [
        'class' => ['bs_background--attachment bs_col bs_col--100'],
      ],
      '#prefix' => '<hr class="bs_divider"/>',
      '#suffix' => '<hr class="bs_divider"/>',
    ];

    $form['background_options']['background_size'] = [
      '#type' => 'radios',
      '#title' => $this->t('Size'),
      '#options' => [
        'cover' => $this->t('Cover'),
        'contain' => $this->t('Contain'),
        'auto' => $this->t('Auto'),
      ],
      '#default_value' => $storage['background_media']['background_options']['background_size'] ?? 'cover',
      '#attributes' => [
        'class' => ['bs_background--size bs_col bs_col--100'],
      ],
      '#suffix' => '<hr class="bs_divider"/>',
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitStyleFormElements(array $group_elements) {
    return [
      'background_media' => [
        'image' => [
          'media_id' => $group_elements['background_image']['media_library_selection'] ?? $group_elements['background_image'],
        ],
        'video' => [
          'media_id' => $group_elements['background_video']['media_library_selection'] ?? $group_elements['background_video'],
        ],
        'background_options' => [
          'background_position' => $group_elements['background_options']['background_position'],
          'background_repeat' => $group_elements['background_options']['background_repeat'],
          'background_attachment' => $group_elements['background_options']['background_attachment'],
          'background_size' => $group_elements['background_options']['background_size'],
        ],
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function build(array $build, array $storage, $theme_wrapper = NULL) {
    $config = $this->config();

    // Backwards compatibility for layouts created on the 1.x version.
    if (isset($storage['background_media']['media_id'])) {
      $media_id = $storage['background_media']['media_id'];
      $background_type = $storage['background']['background_type'];
      $storage['background_media'][$background_type]['media_id'] = $media_id;
    }

    if (isset($storage['background']['background_type'])) {
      if ($config->get('background_image.bundle') && $storage['background']['background_type'] == 'image' && isset($storage['background_media']['image']['media_id']) && ($media_id = $storage['background_media']['image']['media_id'])) {
        $media_entity = Media::load($media_id);
        $media_field_name = $config->get('background_image.field');

        // Check if the field exist.
        if ($media_entity && $media_entity->hasField($media_field_name)) {
          $background_image_style = $this->buildBackgroundMediaImage($media_entity, $media_field_name, $storage);
          // Assign the style to element or its theme wrapper if exist.
          if ($theme_wrapper && isset($build['#theme_wrappers'][$theme_wrapper])) {
            $build['#theme_wrappers'][$theme_wrapper]['#attributes']['style'][] = $background_image_style;
            $build['#theme_wrappers'][$theme_wrapper]['#attributes']['class'][] = 'bg-image';
          }
          else {
            $build['#attributes']['style'][] = $background_image_style;
            $build['#attributes']['class'][] = 'bg-image';
          }
        }
      }

      if ($config->get('background_local_video.bundle') && $storage['background']['background_type'] == 'video' && isset($storage['background_media']['video']['media_id']) && ($media_id = $storage['background_media']['video']['media_id'])) {
        // Added to avoid problem about media background style in the section:
        // check if $media_id is an array and extract the value of
        //  'media_library_selection' if present.
        if (is_array($media_id) && isset($media_id['media_library_selection'])) {
          $media_id = $media_id['media_library_selection'];
        }

        $media_entity = Media::load($media_id);
        $media_field_name = $config->get('background_local_video.field');
        // Check if the field exist.
        if ($media_entity && $media_entity->hasField($media_field_name)) {
          $background_video_url = $this->buildBackgroundMediaLocalVideo($media_entity, $media_field_name);

          $build['#theme_wrappers']['bs_video_background'] = [
            '#video_background_url' => $background_video_url,
            '#attributes' => ['class' => ['bg-video']],
          ];

          if ($config->get('background_image.bundle') && isset($storage['background_media']['image']['media_id']) && ($media_id = $storage['background_media']['image']['media_id'])) {
            $media_entity = Media::load($media_id);
            $media_field_name = $config->get('background_image.field');

            // Check if the field exist.
            if ($media_entity && $media_entity->hasField($media_field_name)) {
              $background_image_style = $this->buildBackgroundMediaImage($media_entity, $media_field_name, $storage);

              $build['#theme_wrappers']['bs_video_background']['#attributes']['style'][] = $background_image_style;
              $build['#theme_wrappers']['bs_video_background']['#attributes']['class'][] = 'bg-image';
            }
          }

          if (isset($storage['background_color']['class']) && $storage['background_color']['class'] !== '' && $storage['background_color']['class'] !== '_none') {
            $build['#theme_wrappers']['bs_video_background']['#attributes']['class'][] = 'bg-color';
            $build['#theme_wrappers']['bs_video_background']['#attributes']['class'][] = $storage['background_color']['class'];
          }
        }
      }
    }

    return $build;
  }

  /**
   * Helper function to the background media image style.
   *
   * @param object $media_entity
   *   A media entity object.
   * @param object $field_name
   *   The Media entity local video field name.
   * @param object $storage
   *   The styles storage.
   *
   * @return string
   *   Background media image style.
   */
  public function buildBackgroundMediaImage($media_entity, $field_name, $storage) {
    $fid = $media_entity->get($field_name)->target_id;
    $file = File::load($fid);
    $background_url = $file->createFileUrl();

    $style = 'background-image: url(' . $background_url . ');';
    $background_position = 'background-position: center;';
    $background_repeat = 'background-repeat: no-repeat;';
    $background_size = 'background-size: cover;';
    $background_attachment = '';
    if (isset($storage['background_media']['background_options'])) {
      // Background position.
      if (isset($storage['background_media']['background_options']['background_position']) && !empty($storage['background_media']['background_options']['background_position'])) {
        $background_position = 'background-position: ' . $storage['background_media']['background_options']['background_position'] . ';';
      }

      // Background repeat.
      if (isset($storage['background_media']['background_options']['background_repeat']) && !empty($storage['background_media']['background_options']['background_repeat'])) {
        $background_repeat = 'background-repeat: ' . $storage['background_media']['background_options']['background_repeat'] . ';';
      }

      // Background attachment.
      if (isset($storage['background_media']['background_options']['background_attachment']) && !empty($storage['background_media']['background_options']['background_attachment'])) {
        if ($storage['background_media']['background_options']['background_attachment'] != 'not_fixed') {
          $background_attachment = 'background-attachment: ' . $storage['background_media']['background_options']['background_attachment'] . ';';
        }
      }

      // Background size.
      if (isset($storage['background_media']['background_options']['background_size']) && !empty($storage['background_media']['background_options']['background_size'])) {
        $background_size = 'background-size: ' . $storage['background_media']['background_options']['background_size'] . ';';
      }
    }

    $style .= ' ' . $background_position;
    $style .= ' ' . $background_repeat;
    $style .= ' ' . $background_size;
    if ($background_attachment) {
      $style .= ' ' . $background_attachment;
    }

    return $style;
  }

  /**
   * Helper function to the background media local video style.
   *
   * @param object $media_entity
   *   A media entity object.
   * @param object $field_name
   *   The Media entity local video field name.
   *
   * @return string
   *   Background media local video style.
   */
  public function buildBackgroundMediaLocalVideo($media_entity, $field_name) {
    $fid = $media_entity->get($field_name)->target_id;
    $file = File::load($fid);
    return $file->createFileUrl();
  }

}
