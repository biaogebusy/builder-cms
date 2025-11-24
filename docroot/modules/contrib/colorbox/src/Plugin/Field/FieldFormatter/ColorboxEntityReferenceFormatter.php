<?php

namespace Drupal\colorbox\Plugin\Field\FieldFormatter;

use Drupal\colorbox\ElementAttachmentInterface;
use Drupal\Core\Asset\LibraryDiscoveryInterface;
use Drupal\Core\Entity\EntityDisplayRepositoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\Plugin\Field\FieldFormatter\EntityReferenceFormatterBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Link;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Plugin implementation of the 'colorbox' formatter.
 *
 * @FieldFormatter(
 *   id = "colorbox_view_modes",
 *   module = "colorbox",
 *   label = @Translation("Colorbox (view modes)"),
 *   field_types = {
 *     "entity_reference"
 *   }
 * )
 */
class ColorboxEntityReferenceFormatter extends EntityReferenceFormatterBase implements ContainerFactoryPluginInterface {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The entity display repository.
   *
   * @var \Drupal\Core\Entity\EntityDisplayRepositoryInterface
   */
  protected $entityDisplayRepository;

  /**
   * The module handler.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $moduleHandler;

  /**
   * The current user.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $currentUser;

  /**
   * The colorbox attachment service.
   *
   * @var \Drupal\colorbox\ElementAttachmentInterface
   */
  protected $attachment;

  /**
   * Library discovery service.
   *
   * @var \Drupal\Core\Asset\LibraryDiscoveryInterface
   */
  private $libraryDiscovery;

  /**
   * Constructs an ColorboxEntityReferenceFormatter object.
   *
   * @param string $plugin_id
   *   The plugin_id for the formatter.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Field\FieldDefinitionInterface $field_definition
   *   The definition of the field to which the formatter is associated.
   * @param array $settings
   *   The formatter settings.
   * @param string $label
   *   The formatter label display setting.
   * @param string $view_mode
   *   The view mode.
   * @param array $third_party_settings
   *   Any third party settings settings.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\Entity\EntityDisplayRepositoryInterface $entity_display_repository
   *   The entity display repository.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler.
   * @param \Drupal\Core\Session\AccountInterface $current_user
   *   The current user.
   * @param \Drupal\colorbox\ElementAttachmentInterface $attachment
   *   The colorbox attachment service.
   * @param \Drupal\Core\Asset\LibraryDiscoveryInterface $library_discovery
   *   Library discovery service.
   */
  public function __construct($plugin_id, $plugin_definition, FieldDefinitionInterface $field_definition, array $settings, $label, $view_mode, array $third_party_settings, EntityTypeManagerInterface $entity_type_manager, EntityDisplayRepositoryInterface $entity_display_repository, ModuleHandlerInterface $module_handler, AccountInterface $current_user, ElementAttachmentInterface $attachment, LibraryDiscoveryInterface $library_discovery) {
    parent::__construct($plugin_id, $plugin_definition, $field_definition, $settings, $label, $view_mode, $third_party_settings);
    $this->entityTypeManager = $entity_type_manager;
    $this->entityDisplayRepository = $entity_display_repository;
    $this->moduleHandler = $module_handler;
    $this->currentUser = $current_user;
    $this->attachment = $attachment;
    $this->libraryDiscovery = $library_discovery;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $plugin_id,
      $plugin_definition,
      $configuration['field_definition'],
      $configuration['settings'],
      $configuration['label'],
      $configuration['view_mode'],
      $configuration['third_party_settings'],
      $container->get('entity_type.manager'),
      $container->get('entity_display.repository'),
      $container->get('module_handler'),
      $container->get('current_user'),
      $container->get('colorbox.attachment'),
      $container->get('library.discovery')
    );
  }

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings() {
    return [
      'colorbox_view_mode' => '',
      'colorbox_modal_view_mode' => '',
      'colorbox_gallery' => 'post',
      'colorbox_gallery_custom' => '',
    ] + parent::defaultSettings();
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state) {
    $target_type = $this->getFieldSetting('target_type');
    $view_modes = array_map(function ($view_mode) {
      return $view_mode['label'];
    }, $this->entityDisplayRepository->getViewModes($target_type));
    $description_link = Link::fromTextAndUrl(
      $this->t('Configure view modes'),
      Url::fromRoute('entity.entity_view_mode.collection')
    );

    $element['colorbox_view_mode'] = [
      '#title' => $this->t('View mode for content'),
      '#type' => 'select',
      '#default_value' => $this->getSetting('colorbox_view_mode'),
      '#options' => $view_modes,
      '#description' => $description_link->toRenderable() + [
        '#access' => $this->currentUser->hasPermission('administer display modes'),
      ],
    ];
    $element['colorbox_modal_view_mode'] = [
      '#title' => $this->t('View mode for Colorbox'),
      '#type' => 'select',
      '#default_value' => $this->getSetting('colorbox_modal_view_mode'),
      '#options' => $view_modes,
      '#description' => $description_link->toRenderable() + [
        '#access' => $this->currentUser->hasPermission('administer display modes'),
      ],
    ];

    $gallery = [
      'post' => $this->t('Per post gallery'),
      'page' => $this->t('Per page gallery'),
      'field_post' => $this->t('Per field in post gallery'),
      'field_page' => $this->t('Per field in page gallery'),
      'custom' => $this->t('Custom (with tokens)'),
      'none' => $this->t('No gallery'),
    ];
    $element['colorbox_gallery'] = [
      '#title' => $this->t('Gallery'),
      '#type' => 'select',
      '#default_value' => $this->getSetting('colorbox_gallery'),
      '#options' => $gallery,
      '#description' => $this->t('How Colorbox should group the entities.'),
    ];
    $element['colorbox_gallery_custom'] = [
      '#title' => $this->t('Custom gallery'),
      '#type' => 'textfield',
      '#default_value' => $this->getSetting('colorbox_gallery_custom'),
      '#description' => $this->t('All entity references on a page with the same gallery value (rel attribute) will be grouped together. It must only contain lowercase letters, numbers, and underscores.'),
      '#required' => FALSE,
      '#states' => [
        'visible' => [
          ':input[name$="[settings_edit_form][settings][colorbox_gallery]"]' => ['value' => 'custom'],
        ],
      ],
    ];
    if ($this->moduleHandler->moduleExists('token')) {
      $element['colorbox_token_gallery'] = [
        '#type' => 'fieldset',
        '#title' => t('Replacement patterns'),
        '#theme' => 'token_tree_link',
        '#token_types' => [$form['#entity_type'], 'file'],
        '#states' => [
          'visible' => [
            ':input[name$="[settings_edit_form][settings][colorbox_gallery]"]' => ['value' => 'custom'],
          ],
        ],
      ];
    }
    else {
      $element['colorbox_token_gallery'] = [
        '#type' => 'fieldset',
        '#title' => $this->t('Replacement patterns'),
        '#description' => '<strong class="error">' . $this->t('For token support the <a href="@token_url">token module</a> must be installed.', ['@token_url' => 'http://drupal.org/project/token']) . '</strong>',
        '#states' => [
          'visible' => [
            ':input[name$="[settings_edit_form][settings][colorbox_gallery]"]' => ['value' => 'custom'],
          ],
        ],
      ];
    }

    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public function settingsSummary() {
    $summary = [];

    $target_type = $this->getFieldSetting('target_type');
    $view_modes = $this->entityDisplayRepository->getViewModes($target_type);
    if (!empty($view_modes[$this->getSetting('colorbox_view_mode')])) {
      $summary[] = $this->t('Content view mode: @view_mode', ['@view_mode' => $view_modes[$this->getSetting('colorbox_view_mode')]['label']]);
    }

    if (!empty($view_modes[$this->getSetting('colorbox_modal_view_mode')])) {
      $summary[] = $this->t('Colorbox view mode: @view_mode', ['@view_mode' => $view_modes[$this->getSetting('colorbox_modal_view_mode')]['label']]);
    }

    $gallery = [
      'post' => $this->t('Per post gallery'),
      'page' => $this->t('Per page gallery'),
      'field_post' => $this->t('Per field in post gallery'),
      'field_page' => $this->t('Per field in page gallery'),
      'custom' => $this->t('Custom (with tokens)'),
      'none' => $this->t('No gallery'),
    ];
    if ($this->getSetting('colorbox_gallery')) {
      $summary[] = $this->t('Colorbox gallery type: @type', ['@type' => $gallery[$this->getSetting('colorbox_gallery')]]) . ($this->getSetting('colorbox_gallery') == 'custom' ? ' (' . $this->getSetting('colorbox_gallery_custom') . ')' : '');
    }

    return $summary;
  }

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode) {
    $elements = [];
    $settings = $this->getSettings();
    $entities = $this->getEntitiesToView($items, $langcode);
    $target_type = $this->getFieldSetting('target_type');

    // Early opt-out if the field is empty.
    if (empty($entities)) {
      return $elements;
    }

    $view_mode_storage = $this->entityTypeManager->getStorage('entity_view_mode');

    // Collect cache tags and view modes.
    $cache_tags = [];
    $content_view_mode = 'full';
    $modal_view_mode = 'full';

    if (!empty($settings['colorbox_view_mode']) && $view_mode = $view_mode_storage->load($target_type . '.' . $settings['colorbox_view_mode'])) {
      $content_view_mode = $settings['colorbox_view_mode'];
      $cache_tags = array_merge($cache_tags, $view_mode->getCacheTags());
    }

    if (!empty($settings['colorbox_modal_view_mode']) && $view_mode = $view_mode_storage->load($target_type . '.' . $settings['colorbox_modal_view_mode'])) {
      $modal_view_mode = $settings['colorbox_modal_view_mode'];
      $cache_tags = array_merge($cache_tags, $view_mode->getCacheTags());
    }

    foreach ($entities as $delta => $entity) {
      $elements[$delta] = [
        '#theme' => 'colorbox_view_mode_formatter',
        '#item' => $entity,
        '#content' => $this->entityTypeManager
          ->getViewBuilder($entity->getEntityTypeId())
          ->view($entity, $content_view_mode),
        '#modal' => $this->entityTypeManager
          ->getViewBuilder($entity->getEntityTypeId())
          ->view($entity, $modal_view_mode),
        '#entity' => $items->getEntity(),
        '#field_name' => $this->fieldDefinition->getName(),
        '#settings' => $settings,
        '#cache' => [
          'tags' => $cache_tags,
        ],
      ];
    }

    // Attach the Colorbox JS and CSS.
    if ($this->attachment->isApplicable()) {
      $this->attachment->attach($elements);
      $dompurify = $this->libraryDiscovery->getLibraryByName('colorbox', 'dompurify');
      $dompurify_file = !empty($dompurify['js'][0]['data']) ?
      DRUPAL_ROOT . '/' . $dompurify['js'][0]['data'] : NULL;
      $dompurify_exists = !empty($dompurify) && !empty($dompurify_file) &&
        file_exists($dompurify_file);
      if ($dompurify_exists) {
        $elements['#attached']['library'][] = 'colorbox/dompurify';
      }
    }

    return $elements;
  }

  /**
   * {@inheritdoc}
   */
  public function calculateDependencies() {
    $dependencies = parent::calculateDependencies();

    $view_modes = [];
    if (!empty($this->getSetting('colorbox_view_mode'))) {
      $view_modes['colorbox_view_mode'] = $this->getSetting('colorbox_view_mode');
    }
    if (!empty($this->getSetting('colorbox_modal_view_mode'))) {
      $view_modes['colorbox_modal_view_mode'] = $this->getSetting('colorbox_modal_view_mode');
    }

    $target_type = $this->getFieldSetting('target_type');
    $view_mode_storage = $this->entityTypeManager->getStorage('entity_view_mode');
    foreach ($view_modes as $setting => $view_mode_id) {
      $view_mode = $view_mode_storage->load($target_type . '.' . $view_mode_id);
      if ($view_mode) {
        // If this formatter uses a valid view modes to display the image, add
        // the view mode configuration entity as dependency of this formatter.
        $dependencies[$view_mode->getConfigDependencyKey()][] = $view_mode->getConfigDependencyName();
      }
    }

    return $dependencies;
  }

  /**
   * {@inheritdoc}
   */
  public function onDependencyRemoval(array $dependencies) {
    $changed = parent::onDependencyRemoval($dependencies);

    $view_modes = [];
    if (!empty($this->getSetting('colorbox_view_mode'))) {
      $view_modes['colorbox_view_mode'] = $this->getSetting('colorbox_view_mode');
    }
    if (!empty($this->getSetting('colorbox_modal_view_mode'))) {
      $view_modes['colorbox_modal_view_mode'] = $this->getSetting('colorbox_modal_view_mode');
    }

    $target_type = $this->getFieldSetting('target_type');
    $view_mode_storage = $this->entityTypeManager->getStorage('entity_view_mode');
    foreach ($view_modes as $setting => $view_mode_id) {
      $view_mode = $view_mode_storage->load($target_type . '.' . $view_mode_id);
      if (!$view_mode || !empty($dependencies[$view_mode->getConfigDependencyKey()][$view_mode->getConfigDependencyName()])) {
        $this->setSetting($setting, NULL);
        $changed = TRUE;
      }
    }

    return $changed;
  }

}
