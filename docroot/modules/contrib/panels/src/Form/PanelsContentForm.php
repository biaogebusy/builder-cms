<?php

namespace Drupal\panels\Form;

use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\TempStore\SharedTempStoreFactory;
use Drupal\ctools\Form\AjaxFormTrait;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a form for editing a panel variant display's content.
 */
class PanelsContentForm extends FormBase {

  use AjaxFormTrait;

  /**
   * Tempstore factory.
   *
   * @var \Drupal\Core\TempStore\SharedTempStoreFactory
   */
  protected $tempstore;

  /**
   * The tempstore ID.
   *
   * @var string
   */
  protected $tempstore_id;

  /**
   * ModuleHandler.
   *
   * @var \Drupal\Core\Extension\ModuleHandler
   *   ModuleHandler.
   */
  protected $moduleHandler;

  /**
   * Constructs a new VariantPluginContentForm.
   *
   * @param \Drupal\Core\TempStore\SharedTempStoreFactory $tempstore
   *   The tempstore factory.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $moduleHandler
   *   The module handler.
   */
  public function __construct(SharedTempStoreFactory $tempstore, ModuleHandlerInterface $moduleHandler) {
    $this->tempstore = $tempstore;
    $this->moduleHandler = $moduleHandler;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('tempstore.shared'),
      $container->get('module_handler')
    );
  }

  /**
   * Get the tempstore ID.
   *
   * @return string
   */
  protected function getTempstoreId() {
    return $this->tempstore_id;
  }

  /**
   * Get the tempstore.
   *
   * @return \Drupal\Core\TempStore\SharedTempStoreFactory
   */
  protected function getTempstore() {
    return $this->tempstore->get($this->getTempstoreId());
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'panels_block_page_content';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form['#attached']['library'][] = 'block/drupal.block';
    $this->tempstore_id = $form_state->getFormObject()->getTempstoreId();
    $cached_values = $form_state->getTemporaryValue('wizard');
    /** @var \Drupal\panels\Plugin\DisplayVariant\PanelsDisplayVariant $variant_plugin */
    $variant_plugin = $cached_values['plugin'];
    // Allow to configure the page title, even when adding a new display.
    // Default to the page label in that case.
    $form['page_title'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Page title'),
      '#description' => $this->t('Configure the page title that will be used for this display.'),
      '#default_value' => $variant_plugin->getConfiguration()['page_title'] ?: '',
    ];
    $pattern_plugin = $variant_plugin->getPattern();
    $machine_name = $pattern_plugin->getMachineName($cached_values);

    if ($this->moduleHandler->moduleExists('token')) {
      $contexts = $pattern_plugin->getDefaultContexts($this->tempstore, $this->tempstore_id, $machine_name);
      $tokens = $this->getContextAsTokenData($contexts);

      $form['token_tree'] = [
        '#type' => 'container',
        'token_tree_link' => [
          '#theme' => 'token_tree_link',
          '#token_types' => $tokens,
        ],
      ];
    }

    // Set up the attributes used by a modal to prevent duplication later.
    $attributes = $this->getAjaxAttributes();
    $add_button_attributes = $this->getAjaxButtonAttributes();

    if ($block_assignments = $variant_plugin->getRegionAssignments()) {
      // Build a table of all blocks used by this variant.
      $form['add'] = [
        '#type' => 'link',
        '#title' => $this->t('Add new block'),
        '#url' => $pattern_plugin->getBlockListUrl($this->tempstore_id, $machine_name, NULL, $this->getRequest()->getRequestUri()),
        '#attributes' => $add_button_attributes,
        '#attached' => [
          'library' => [
            'core/drupal.ajax',
          ],
        ],
      ];
      $form['blocks'] = [
        '#type' => 'table',
        '#header' => [
          $this->t('Label'),
          $this->t('Plugin ID'),
          $this->t('Region'),
          $this->t('Weight'),
          $this->t('Operations'),
        ],
        '#attributes' => [
          'id' => 'blocks',
        ],
        '#empty' => $this->t('There are no regions for blocks.'),
      ];
      // Loop through the blocks per region.
      foreach ($block_assignments as $region => $blocks) {
        // Add a section for each region and allow blocks to be dragged between
        // them.
        $form['blocks']['#tabledrag'][] = [
          'action' => 'match',
          'relationship' => 'sibling',
          'group' => 'block-region-select',
          'subgroup' => 'block-region-' . $region,
          'hidden' => FALSE,
        ];
        $form['blocks']['#tabledrag'][] = [
          'action' => 'order',
          'relationship' => 'sibling',
          'group' => 'block-weight',
          'subgroup' => 'block-weight-' . $region,
        ];
        $form['blocks']['region-' . $region] = [
          '#attributes' => [
            'class' => ['region-title', 'region-title-' . $region],
            'no_striping' => TRUE,
          ],
        ];
        $form['blocks']['region-' . $region]['title'] = [
          '#markup' => $variant_plugin->getRegionName($region),
          '#wrapper_attributes' => [
            'colspan' => 5,
          ],
        ];
        $form['blocks']['region-' . $region . '-message'] = [
          '#attributes' => [
            'class' => [
              'region-message',
              'region-' . $region . '-message',
              empty($blocks) ? 'region-empty' : 'region-populated',
            ],
          ],
        ];
        $form['blocks']['region-' . $region . '-message']['message'] = [
          '#markup' => '<em>' . $this->t('No blocks in this region') . '</em>',
          '#wrapper_attributes' => [
            'colspan' => 5,
          ],
        ];

        /** @var \Drupal\Core\Block\BlockPluginInterface[] $blocks */
        foreach ($blocks as $block_id => $block) {
          $row = [
            '#attributes' => [
              'class' => ['draggable'],
            ],
          ];
          $row['label']['#markup'] = $block->label();
          $row['id']['#markup'] = $block->getPluginId();
          // Allow the region to be changed for each block.
          $row['region'] = [
            '#title' => $this->t('Region'),
            '#title_display' => 'invisible',
            '#type' => 'select',
            '#options' => $variant_plugin->getRegionNames(),
            '#default_value' => $variant_plugin->getRegionAssignment($block_id),
            '#attributes' => [
              'class' => ['block-region-select', 'block-region-' . $region],
            ],
          ];
          // Allow the weight to be changed for each block.
          $configuration = $block->getConfiguration();
          $row['weight'] = [
            '#type' => 'weight',
            '#default_value' => isset($configuration['weight']) ? $configuration['weight'] : 0,
            '#title' => $this->t('Weight for @block block', ['@block' => $block->label()]),
            '#title_display' => 'invisible',
            '#attributes' => [
              'class' => ['block-weight', 'block-weight-' . $region],
            ],
          ];
          // Add the operation links.
          $operations = [];
          $operations['edit'] = [
            'title' => $this->t('Edit'),
            'url' => $pattern_plugin->getBlockEditUrl($this->tempstore_id, $machine_name, $block_id, $this->getRequest()->getRequestUri()),
            'attributes' => $attributes,
          ];
          $operations['delete'] = [
            'title' => $this->t('Delete'),
            'url' => $pattern_plugin->getBlockDeleteUrl($this->tempstore_id, $machine_name, $block_id, $this->getRequest()->getRequestUri()),
            'attributes' => $attributes,
          ];

          $row['operations'] = [
            '#type' => 'operations',
            '#links' => $operations,
          ];
          $form['blocks'][$block_id] = $row;
        }
      }
    }
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $cached_values = $form_state->getTemporaryValue('wizard');
    /** @var \Drupal\page_manager\Plugin\DisplayVariant\PageBlockDisplayVariant $variant_plugin */
    $variant_plugin = $cached_values['plugin'];

    // If the blocks were rearranged, update their values.
    if (!$form_state->isValueEmpty('blocks')) {
      foreach ($form_state->getValue('blocks') as $block_id => $block_values) {
        $variant_plugin->updateBlock($block_id, $block_values);
      }
    }
    // Page Variant title handling.
    if ($form_state->hasValue('page_title')) {
      $configuration = $variant_plugin->getConfiguration();
      $configuration['page_title'] = $form_state->getValue('page_title');
      $variant_plugin->setConfiguration($configuration);
    }
  }

  /**
   * Get context as token data.
   *
   * @param array $contexts
   *   Context.
   *
   * @return array
   *   Array of tokens.
   */
  protected function getContextAsTokenData(array $contexts) {
    $tokens = [];
    foreach ($contexts as $context_key => $context) {
      if (strpos($context->getContextDefinition()->getDataType(), 'entity:') === 0) {
        $token_type = substr($context->getContextDefinition()->getDataType(), 7);
        if ($token_type == 'taxonomy_term') {
          $token_type = 'term';
        }
        $tokens[] = $token_type;
      }
    }
    return $tokens;
  }

}
