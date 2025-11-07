<?php

declare(strict_types = 1);

namespace Drupal\entity_share_client\Form;

use Drupal\Component\Utility\Html;
use Drupal\Core\Entity\EntityForm;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Form\SubformState;
use Drupal\Core\Plugin\PluginFormInterface;
use Drupal\entity_share_client\ImportProcessor\ImportProcessorInterface;
use Drupal\entity_share_client\ImportProcessor\ImportProcessorPluginManager;
use Drupal\entity_share_client\Service\ImportConfigManipulatorInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Entity form of the import config entity.
 */
class ImportConfigForm extends EntityForm {

  /**
   * The import processor plugin manager.
   *
   * @var \Drupal\entity_share_client\ImportProcessor\ImportProcessorPluginManager
   */
  protected $importProcessorPluginManager;

  /**
   * The import config manipulator.
   *
   * @var \Drupal\entity_share_client\Service\ImportConfigManipulatorInterface
   */
  protected $importConfigManipulator;

  /**
   * Constructs an ImportConfigForm object.
   *
   * @param \Drupal\entity_share_client\ImportProcessor\ImportProcessorPluginManager $import_processor_plugin_manager
   *   The import processor plugin manager.
   * @param \Drupal\entity_share_client\Service\ImportConfigManipulatorInterface $import_config_manipulator
   *   The import config manipulator.
   */
  public function __construct(
    ImportProcessorPluginManager $import_processor_plugin_manager,
    ImportConfigManipulatorInterface $import_config_manipulator
  ) {
    $this->importProcessorPluginManager = $import_processor_plugin_manager;
    $this->importConfigManipulator = $import_config_manipulator;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('plugin.manager.entity_share_client_import_processor'),
      $container->get('entity_share_client.import_config_manipulator')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function form(array $form, FormStateInterface $form_state) {
    $form = parent::form($form, $form_state);

    /** @var \Drupal\entity_share_client\Entity\ImportConfigInterface $import_config */
    $import_config = $this->entity;
    $form['label'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Label'),
      '#maxlength' => 255,
      '#default_value' => $import_config->label(),
      '#description' => $this->t("Label for the Import config."),
      '#required' => TRUE,
    ];

    $form['id'] = [
      '#type' => 'machine_name',
      '#default_value' => $import_config->id(),
      '#machine_name' => [
        'exists' => '\Drupal\entity_share_client\Entity\ImportConfig::load',
      ],
      '#disabled' => !$import_config->isNew(),
    ];

    $form['import_maxsize'] = [
      '#type' => 'number',
      '#title' => $this->t('Max size'),
      '#description' => $this->t("The JSON:API's page limit option to limit the number of entities per page."),
      '#default_value' => $import_config->get('import_maxsize'),
      '#min' => 1,
      '#max' => 50,
      '#required' => TRUE,
    ];

    // Retrieve lists of all processors, and the stages and weights they have.
    if (!$form_state->has('processors')) {
      $all_processors = $this->getAllProcessors();
      $sort_processors = function (ImportProcessorInterface $processor_a, ImportProcessorInterface $processor_b) {
        return strnatcasecmp($processor_a->label(), $processor_b->label());
      };
      uasort($all_processors, $sort_processors);
      $form_state->set('processors', $all_processors);
    }
    else {
      $all_processors = $form_state->get('processors');
    }

    $stages = $this->importProcessorPluginManager->getProcessingStages();
    /** @var \Drupal\entity_share_client\ImportProcessor\ImportProcessorInterface[][] $processors_by_stage */
    $processors_by_stage = [];
    foreach ($all_processors as $processor_id => $processor) {
      foreach (array_keys($stages) as $stage) {
        if ($processor->supportsStage($stage)) {
          $processors_by_stage[$stage][$processor_id] = $processor;
        }
      }
    }

    $enabled_processors = $this->importConfigManipulator->getImportProcessors($import_config);

    $form['#tree'] = TRUE;
    $form['#attached']['library'][] = 'entity_share_client/admin';
    $form['#attached']['library'][] = 'entity_share_client/import-processors';

    // Add the list of processors with checkboxes to enable/disable them.
    $form['status'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Enabled'),
      '#attributes' => [
        'class' => [
          'entity-share-client-status-wrapper',
        ],
      ],
    ];
    foreach ($all_processors as $processor_id => $processor) {
      $clean_css_id = Html::cleanCssIdentifier($processor_id);
      $is_enabled = !empty($enabled_processors[$processor_id]);
      $is_locked = $processor->isLocked();
      $form['status'][$processor_id] = [
        '#type' => 'checkbox',
        '#title' => $processor->label(),
        '#default_value' => $is_locked || $is_enabled,
        '#description' => $processor->getDescription(),
        '#attributes' => [
          'class' => [
            'entity-share-client-processor-status-' . $clean_css_id,
          ],
          'data-id' => $clean_css_id,
        ],
        '#disabled' => $is_locked,
      ];
    }

    $form['weights'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Processor order'),
    ];
    // Order enabled processors per stage.
    foreach ($stages as $stage => $description) {
      $form['weights'][$stage] = [
        '#type' => 'fieldset',
        '#title' => $description['label'],
        '#attributes' => [
          'class' => [
            'entity-share-client-stage-wrapper',
            'entity-share-client-stage-wrapper-' . Html::cleanCssIdentifier($stage),
          ],
        ],
      ];
      $form['weights'][$stage]['order'] = [
        '#type' => 'table',
      ];
      $form['weights'][$stage]['order']['#tabledrag'][] = [
        'action' => 'order',
        'relationship' => 'sibling',
        'group' => 'entity-share-client-processor-weight-' . Html::cleanCssIdentifier($stage),
      ];
    }
    foreach ($processors_by_stage as $stage => $processors) {
      // Sort the processors by weight for this stage.
      $processor_weights = [];
      foreach ($processors as $processor_id => $processor) {
        $processor_weights[$processor_id] = $processor->getWeight($stage);
      }
      asort($processor_weights);

      foreach ($processor_weights as $processor_id => $weight) {
        $processor = $processors[$processor_id];
        $form['weights'][$stage]['order'][$processor_id]['#attributes']['class'][] = 'draggable';
        $form['weights'][$stage]['order'][$processor_id]['#attributes']['class'][] = 'entity-share-client-processor-weight--' . Html::cleanCssIdentifier($processor_id);
        $form['weights'][$stage]['order'][$processor_id]['#weight'] = $weight;
        $form['weights'][$stage]['order'][$processor_id]['label']['#plain_text'] = $processor->label();
        $form['weights'][$stage]['order'][$processor_id]['weight'] = [
          '#type' => 'weight',
          '#title' => $this->t('Weight for processor %title', ['%title' => $processor->label()]),
          '#title_display' => 'invisible',
          '#delta' => 50,
          '#default_value' => $weight,
          '#parents' => ['processors', $processor_id, 'weights', $stage],
          '#attributes' => [
            'class' => [
              'entity-share-client-processor-weight-' . Html::cleanCssIdentifier($stage),
            ],
          ],
        ];
      }
    }

    // Add vertical tabs containing the settings for the processors. Tabs for
    // disabled processors are hidden with JS magic, but need to be included in
    // case the processor is enabled.
    $form['processor_settings'] = [
      '#title' => $this->t('Processor settings'),
      '#type' => 'vertical_tabs',
    ];

    foreach ($all_processors as $processor_id => $processor) {
      if ($processor instanceof PluginFormInterface) {
        $form['settings'][$processor_id] = [
          '#type' => 'details',
          '#title' => $processor->label(),
          '#group' => 'processor_settings',
          '#parents' => ['processors', $processor_id, 'settings'],
          '#attributes' => [
            'class' => [
              'entity-share-client-processor-settings-' . Html::cleanCssIdentifier($processor_id),
            ],
          ],
        ];
        $processor_form_state = SubformState::createForSubform($form['settings'][$processor_id], $form, $form_state);
        $form['settings'][$processor_id] += $processor->buildConfigurationForm($form['settings'][$processor_id], $processor_form_state);
      }
      else {
        unset($form['settings'][$processor_id]);
      }
    }

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    parent::validateForm($form, $form_state);

    $values = $form_state->getValues();
    $processors = $this->getAllProcessors();

    // Iterate over all processors that have a form and are enabled.
    foreach (array_keys(array_filter($values['status'])) as $processor_id) {
      $processor = $processors[$processor_id];
      if ($processor instanceof PluginFormInterface) {
        $processor_form_state = SubformState::createForSubform($form['settings'][$processor_id], $form, $form_state);
        $processor->validateConfigurationForm($form['settings'][$processor_id], $processor_form_state);
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state) {
    $values = $form_state->getValues();
    /** @var \Drupal\entity_share_client\Entity\ImportConfigInterface $import_config */
    $import_config = $this->entity;

    // Store processor settings.
    $import_processor_settings = [];
    $processors = $this->getAllProcessors();
    foreach ($processors as $processor_id => $processor) {
      // Disabled processors.
      if (empty($values['status'][$processor_id])) {
        continue;
      }
      if ($processor instanceof PluginFormInterface) {
        $processor_form_state = SubformState::createForSubform($form['settings'][$processor_id], $form, $form_state);
        $processor->submitConfigurationForm($form['settings'][$processor_id], $processor_form_state);
      }
      if (!empty($values['processors'][$processor_id]['weights'])) {
        foreach ($values['processors'][$processor_id]['weights'] as $stage => $weight) {
          $processor->setWeight($stage, (int) $weight);
        }
      }

      $import_processor_settings[$processor_id] = $processor->getConfiguration();
    }
    $import_config->set('import_processor_settings', $import_processor_settings);

    $import_config->set('status', TRUE);
    $status = $import_config->save();

    switch ($status) {
      case SAVED_NEW:
        $this->messenger()->addStatus($this->t('Created the %label Import config.', [
          '%label' => $import_config->label(),
        ]));
        break;

      default:
        $this->messenger()->addStatus($this->t('Saved the %label Import config.', [
          '%label' => $import_config->label(),
        ]));
    }
    $form_state->setRedirectUrl($import_config->toUrl('collection'));
  }

  /**
   * Retrieves all available processors.
   *
   * @return \Drupal\entity_share_client\ImportProcessor\ImportProcessorInterface[]
   *   The import processors.
   */
  protected function getAllProcessors() {
    /** @var \Drupal\entity_share_client\Entity\ImportConfigInterface $import_config */
    $import_config = $this->entity;
    $processors = $this->importConfigManipulator->getImportProcessors($import_config);

    foreach (array_keys($this->importProcessorPluginManager->getDefinitions()) as $name) {
      if (isset($processors[$name])) {
        continue;
      }
      else {
        $processors[$name] = $this->importProcessorPluginManager->createInstance($name);
      }
    }

    return $processors;
  }

}
