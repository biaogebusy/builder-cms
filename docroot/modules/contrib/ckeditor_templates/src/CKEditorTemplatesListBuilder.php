<?php

namespace Drupal\ckeditor_templates;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Config\Entity\DraggableListBuilder;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\EntityListBuilder;
use Drupal\Core\Entity\EntityHandlerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a listing of CKEditor Templates.
 */
class CKEditorTemplatesListBuilder extends DraggableListBuilder {

  /**
   * The messenger interface.
   *
   * @var \Drupal\Core\Messenger\MessengerInterface
   */
  protected $messenger;

  /**
   * Creates a new instance of CKEditorTemplatesListBuilder.
   *
   * @param \Drupal\Core\Entity\EntityTypeInterface $entityType
   *   The entity type interface.
   * @param \Drupal\Core\Entity\EntityStorageInterface $storage
   *   The entity storage interface.
   * @param \Drupal\Core\Messenger\MessengerInterface $messenger
   *   The messenger interface.
   */
  public function __construct(EntityTypeInterface $entityType, EntityStorageInterface $storage, MessengerInterface $messenger) {
    parent::__construct($entityType, $storage);
    $this->messenger = $messenger;
  }

  /**
   * {@inheritdoc}
   */
  public static function createInstance(ContainerInterface $container, EntityTypeInterface $entity_type): CKEditorTemplatesListBuilder | EntityListBuilder | EntityHandlerInterface | static {
    return new static(
      $entity_type,
      $container->get('entity_type.manager')->getStorage($entity_type->id()),
      $container->get('messenger')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'ckeditor_templates_entity_list';
  }

  /**
   * {@inheritdoc}
   */
  public function buildHeader(): array {
    $header['label'] = $this->t('Label');
    $header['id'] = $this->t('Machine name');
    $header['status'] = $this->t('Status');
    $header['formats'] = $this->t('Formats');

    return $header + parent::buildHeader();
  }

  /**
   * {@inheritdoc}
   */
  public function buildRow(EntityInterface $entity): array {
    $formats = [];
    $formatOptions = $entity->get('formats');
    foreach (filter_formats() as $format) {
      if (in_array($format->id(), $formatOptions)) {
        $formats[] = $format->label();
      }
    }

    $row['label'] = $entity->label();
    $row['id']['#markup'] = $entity->id();
    $row['status']['#markup'] = $entity->status() ? $this->t('Enabled') : $this->t('Disabled');
    $row['formats']['#markup'] = implode(', ', $formats);

    return $row + parent::buildRow($entity);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    parent::submitForm($form, $form_state);
    $this->messenger->addStatus($this->t('The CKEditor Template Configuration settings have been updated.'));
  }

}
