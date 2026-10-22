<?php

declare(strict_types=1);

namespace Drupal\xinshi_office\Form;

use Drupal\Core\Form\ConfirmFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\xinshi_office\OfficeAdministration;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

/** Confirm trash and restore operations with Form API session CSRF protection. */
final class OfficeDeleteForm extends ConfirmFormBase {

  protected array $document = [];
  protected bool $delete = TRUE;

  public function __construct(protected OfficeAdministration $administration) {}

  /** {@inheritdoc} */
  public static function create(ContainerInterface $container): static {
    return new static($container->get('xinshi_office.administration'));
  }

  /** {@inheritdoc} */
  public function getFormId(): string { return 'xinshi_office_delete_confirm'; }

  /** {@inheritdoc} */
  public function getQuestion() {
    return $this->delete ? $this->t('Move “@name” to the trash?', ['@name' => $this->document['name']])
      : $this->t('Restore “@name” from the trash?', ['@name' => $this->document['name']]);
  }

  /** {@inheritdoc} */
  public function getDescription() {
    return $this->delete
      ? $this->t('The document will be hidden from the owner’s list and cannot be edited. Its versions and files will be retained. You can restore it from the trash. Storage usage will not decrease.')
      : $this->t('The document will return to the owner’s list with its existing versions and files.');
  }

  /** {@inheritdoc} */
  public function getConfirmText() { return $this->delete ? $this->t('Move to trash') : $this->t('Restore'); }

  /** {@inheritdoc} */
  public function getCancelUrl(): Url {
    return Url::fromRoute('xinshi_office.admin_view', ['document_id' => $this->document['document_id']]);
  }

  /** {@inheritdoc} */
  public function buildForm(array $form, FormStateInterface $form_state, ?string $document_id = NULL,
    string $operation = 'delete'): array {
    $this->administration->authorize(TRUE);
    $this->document = $this->administration->document($document_id)['document'];
    $this->delete = $operation === 'delete';
    $form = parent::buildForm($form, $form_state);
    $form['owner'] = ['#plain_text' => $this->t('Owner user ID: @uid. Version: @revision.',
      ['@uid' => $this->document['uid'], '@revision' => $this->document['current_revision']])];
    $form['expected_revision'] = ['#type' => 'hidden', '#default_value' => $this->document['current_revision']];
    $form['expected_deleted'] = ['#type' => 'hidden', '#default_value' => $this->document['deleted']];
    $form['#cache']['max-age'] = 0;
    return $form;
  }

  /** {@inheritdoc} */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    try {
      $this->administration->changeStatus($this->document['document_id'], $this->delete,
        (int) $form_state->getValue('expected_revision'), (int) $form_state->getValue('expected_deleted'));
      $this->messenger()->addStatus($this->delete ? $this->t('The document was moved to the trash.')
        : $this->t('The document was restored.'));
      $form_state->setRedirect('xinshi_office.admin', [], ['query' => ['deleted' => $this->delete ? '1' : '0']]);
    }
    catch (ConflictHttpException) {
      $this->messenger()->addError($this->t('The document changed. Review the current document before confirming again.'));
      $form_state->setRedirect('xinshi_office.admin_view', ['document_id' => $this->document['document_id']]);
    }
  }

}
