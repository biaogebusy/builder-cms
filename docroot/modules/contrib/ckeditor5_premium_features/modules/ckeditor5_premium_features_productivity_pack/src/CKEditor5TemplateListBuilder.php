<?php

/*
 * Copyright (c) 2003-2026, CKSource Holding sp. z o.o. All rights reserved.
 * For licensing, see https://ckeditor.com/legal/ckeditor-oss-license
 */

declare(strict_types=1);

namespace Drupal\ckeditor5_premium_features_productivity_pack;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Config\Entity\DraggableListBuilder;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a listing of ckeditor5 content templates.
 */
class CKEditor5TemplateListBuilder extends DraggableListBuilder {

  /**
   * The constructor.
   *
   * @param \Drupal\Core\Messenger\MessengerInterface $messenger
   *   The Messenger.
   * @param mixed ...$parents_arguments
   *   Parents arguments.
   */
  public function __construct(MessengerInterface $messenger, ...$parents_arguments) {
    $this->messenger = $messenger;
    parent::__construct(...$parents_arguments);
  }

  /**
   * {@inheritdoc}
   */
  public static function createInstance(ContainerInterface $container, EntityTypeInterface $entity_type) {
    return new static(
      $container->get('messenger'),
      $entity_type,
      $container->get('entity_type.manager')->getStorage($entity_type->id())
    );
  }

  /**
   * {@inheritDoc}
   */
  public function getFormId() {
    return 'ckeditor5_template_entity_list';
  }

  /**
   * {@inheritdoc}
   */
  public function buildHeader() {
    $header['label'] = $this->t('Label');
    $header['id'] = $this->t('Machine name');
    $header['status'] = $this->t('Status');
    $header['textFormats'] = $this->t('Formats');

    return $header + parent::buildHeader();
  }

  /**
   * {@inheritdoc}
   */
  public function buildRow(EntityInterface $entity) {
    $entityFormatOptions = $entity->get('textFormats');
    $availableFilterFormats = filter_formats();
    $textFormats = array_map(fn($format) =>
      in_array($format->id(), $entityFormatOptions) ? $format->label() : NULL,
      $availableFilterFormats);
    $textFormats = array_filter($textFormats, fn($format) => $format);
    $row['label'] = $entity->label();
    $row['id']['#markup'] = $entity->id();
    $row['status']['#markup'] = $entity->status() ? $this->t('Enabled') : $this->t('Disabled');
    $row['textFormats']['#markup'] = implode(', ', $textFormats);

    return $row + parent::buildRow($entity);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    // Ensure empty array is passed when trying to submit form with no entities
    // to avoid warning message.
    if (!$form_state->getValue('entities')) {
      $form_state->setValue('entities', []);
    }
    parent::submitForm($form, $form_state);
    $this->messenger->addStatus($this->t('CKEditor5 Content Templates Configuration settings have been updated.'));
  }

}
