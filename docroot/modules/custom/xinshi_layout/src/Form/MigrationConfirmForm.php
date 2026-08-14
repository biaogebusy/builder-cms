<?php

namespace Drupal\xinshi_layout\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\layout_builder\Plugin\SectionStorage\OverridesSectionStorage;

/**
 * Lists translations with Panelizer data that need Layout Builder migration.
 */
class MigrationConfirmForm extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'xinshi_layout_migration_confirm';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $unsynced = $this->findUnsyncedTranslations();

    if (empty($unsynced)) {
      $form['empty'] = [
        '#markup' => '<p>' . $this->t('All translations are in sync. Nothing to migrate.') . '</p>',
      ];
      return $form;
    }

    $form['info'] = [
      '#markup' => '<p>' . $this->t('These translations have Panelizer data but no Layout Builder data. Select the items to migrate, then click "Migrate selected". The translation\'s own Panelizer blocks will be converted to Layout Builder sections.') . '</p>',
    ];

    $form['items'] = [
      '#type' => 'tableselect',
      '#header' => [
        'nid' => $this->t('Node ID'),
        'title' => $this->t('Title'),
        'langcode' => $this->t('Language'),
        'panelizer_blocks' => $this->t('Panelizer blocks'),
      ],
      '#options' => [],
      '#empty' => $this->t('All translations are in sync.'),
    ];

    foreach ($unsynced as $item) {
      $key = $item['nid'] . ':' . $item['langcode'];
      $form['items']['#options'][$key] = [
        'nid' => $item['nid'],
        'title' => $item['title'],
        'langcode' => $item['langcode'],
        'panelizer_blocks' => $item['panelizer_blocks'],
      ];
      $form['items']['#default_value'][$key] = $key;
    }

    $form['actions']['#type'] = 'actions';
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Migrate selected'),
      '#button_type' => 'primary',
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $selected = array_filter($form_state->getValue('items') ?? []);
    if (empty($selected)) {
      $this->messenger()->addWarning($this->t('No items selected.'));
      return;
    }

    $operations = [];
    foreach ($selected as $key) {
      [$nid, $langcode] = explode(':', $key, 2);
      $operations[] = [
        '\Drupal\xinshi_layout\Batch\LayoutMigrationBatch::processItem',
        [(int) $nid, $langcode],
      ];
    }

    $batch = [
      'title' => $this->t('Migrating translations to Layout Builder...'),
      'operations' => $operations,
      'finished' => '\Drupal\xinshi_layout\Batch\LayoutMigrationBatch::finished',
      'init_message' => $this->t('Starting migration...'),
      'progress_message' => $this->t('Processed @current out of @total.'),
      'error_message' => $this->t('Migration encountered an error.'),
    ];

    batch_set($batch);
  }

  /**
   * Finds translations that have Panelizer data but lack Layout Builder data.
   *
   * @return array
   */
  private function findUnsyncedTranslations() {
    $unsynced = [];
    $node_storage = \Drupal::entityTypeManager()->getStorage('node');
    $nids = $node_storage->getQuery()
      ->condition('type', 'landing_page')
      ->accessCheck(FALSE)
      ->execute();

    if (empty($nids)) {
      return [];
    }

    /** @var \Drupal\node\NodeInterface[] $nodes */
    $nodes = $node_storage->loadMultiple($nids);

    foreach ($nodes as $node) {
      $source_langcode = $node->language()->getId();

      foreach ($node->getTranslationLanguages() as $langcode => $language) {
        if ($langcode === $source_langcode) {
          continue;
        }
        if (!$node->hasTranslation($langcode)) {
          continue;
        }
        $trans = $node->getTranslation($langcode);

        // Skip if already has Layout Builder data.
        if ($this->hasLayoutBuilderData($trans)) {
          continue;
        }

        // Check if translation has Panelizer data to migrate.
        $panelizer_blocks = $this->countPanelizerBlocks($trans);
        if ($panelizer_blocks > 0) {
          $unsynced[] = [
            'nid' => $node->id(),
            'title' => $node->label(),
            'langcode' => $langcode,
            'panelizer_blocks' => $panelizer_blocks,
          ];
        }
      }
    }

    return $unsynced;
  }

  /**
   * Checks whether a node has Layout Builder section data.
   *
   * @param \Drupal\node\NodeInterface $node
   * @return bool
   */
  private function hasLayoutBuilderData($node) {
    if (!$node->hasField(OverridesSectionStorage::FIELD_NAME)) {
      return FALSE;
    }
    $field = $node->get(OverridesSectionStorage::FIELD_NAME);
    if ($field->isEmpty()) {
      return FALSE;
    }
    foreach ($field as $item) {
      if (!empty($item->section) && count($item->section->getComponents()) > 0) {
        return TRUE;
      }
    }
    return FALSE;
  }

  /**
   * Counts block_content blocks in a node's Panelizer field.
   *
   * @param \Drupal\node\NodeInterface $node
   * @return int
   */
  private function countPanelizerBlocks($node) {
    if (!$node->hasField('panelizer')) {
      return 0;
    }
    $panelizer = $node->get('panelizer')->getValue();
    $blocks = $panelizer[0]['panels_display']['blocks'] ?? [];
    $count = 0;
    foreach ($blocks as $block) {
      if (($block['provider'] ?? '') === 'block_content') {
        $count++;
      }
    }
    return $count;
  }

}
