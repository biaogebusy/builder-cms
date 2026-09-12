<?php

namespace Drupal\ai\Form;

use Drupal\Core\Form\FormStateInterface;
use Drupal\ai\Entity\AiFileInterface;
use Drupal\ai\Service\AiFileManager;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Entity\ContentEntityDeleteForm;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Delete form for AI File entities.
 *
 * Ensures the remote file is deleted successfully before deleting the local
 * entity. If remote deletion fails, local deletion is aborted.
 */
class AiFileDeleteForm extends ContentEntityDeleteForm implements ContainerInjectionInterface {

  /**
   * AI File manager service.
   */
  protected AiFileManager $aiFileManager;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    /** @var static $instance */
    $instance = parent::create($container);
    $instance->aiFileManager = $container->get('ai.file_manager');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $entity = $this->getEntity();
    if ($entity instanceof AiFileInterface) {
      $remote_deleted = $this->aiFileManager->remoteDelete($entity);
      if (!$remote_deleted) {
        $this->messenger()->addError($this->t('Remote deletion failed. The AI File was not deleted locally.'));
        // Redirect back to collection.
        $form_state->setRedirectUrl($entity->toUrl('collection'));
        return;
      }
    }
    parent::submitForm($form, $form_state);
  }

}
