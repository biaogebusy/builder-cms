<?php

namespace Drupal\crop\Form;

use Drupal\Core\Entity\EntityConfirmFormBase;
use Drupal\Core\Form\ConfirmFormHelper;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Url;

/**
 * Provides a form for crop type deletion.
 */
class CropTypeFlushForm extends EntityConfirmFormBase {

  /**
   * {@inheritdoc}
   */
  public function getQuestion(): TranslatableMarkup {
    return $this->t('Are you sure you want to flush all %type crops?', ['%type' => $this->entity->label()]);
  }

  /**
   * {@inheritdoc}
   */
  public function getCancelUrl(): Url {
    return new Url('crop.overview_types');
  }

  /**
   * {@inheritdoc}
   */
  public function getConfirmText(): TranslatableMarkup {
    return $this->t('Flush');
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $count = $this->entityTypeManager->getStorage('crop')->getQuery()
      ->condition('type', $this->entity->id())
      ->accessCheck()
      ->range(0, 1)
      ->count()
      ->execute();
    if (!$count) {
      $form['#title'] = $this->getQuestion();
      $form['description'] = [
        '#prefix' => '<p>',
        '#markup' => $this->t('There are no %type crops, so you can safely delete this crop type.', ['%type' => $this->entity->label()]),
        '#suffix' => '</p>',
      ];
      $form['actions'] = [
        'cancel' => ConfirmFormHelper::buildCancelLink($this, $this->getRequest()),
      ];
      return $form;
    }

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $storage = $this->entityTypeManager->getStorage('crop');

    $crops = $storage->loadByProperties([
      "type" => $this->entity->id(),
    ]);
    $storage->delete($crops);

    $t_args = ['%type' => $this->entity->label()];
    $this->messenger()->addMessage($this->t('All %type crops have been deleted.', $t_args));
    $this->logger('crop')->notice('All %type crops were deleted.', $t_args);

    $form_state->setRedirectUrl($this->getCancelUrl());
  }

}
