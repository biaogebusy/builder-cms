<?php

declare(strict_types=1);

namespace Drupal\xinshi_office\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;

/** Accessible GET filters for the administrative document list. */
final class OfficeFilterForm extends FormBase {

  /** {@inheritdoc} */
  public function getFormId(): string { return 'xinshi_office_admin_filter'; }

  /** {@inheritdoc} */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $query = $this->getRequest()->query;
    $form['#method'] = 'get';
    $form['name'] = ['#type' => 'textfield', '#title' => $this->t('Document name'), '#maxlength' => 200,
      '#default_value' => $query->getString('name')];
    $form['kind'] = ['#type' => 'select', '#title' => $this->t('Document type'),
      '#options' => ['' => $this->t('All types'), 'word' => 'Word', 'sheet' => 'Excel'],
      '#default_value' => $query->getString('kind')];
    $form['uid'] = ['#type' => 'number', '#title' => $this->t('Owner user ID'), '#min' => 1, '#step' => 1,
      '#default_value' => $query->getString('uid')];
    $form['deleted'] = ['#type' => 'select', '#title' => $this->t('Status'),
      '#options' => ['0' => $this->t('Active'), '1' => $this->t('Trash')],
      '#default_value' => $query->getString('deleted', '0')];
    $form['actions'] = ['#type' => 'actions'];
    $form['actions']['submit'] = ['#type' => 'submit', '#value' => $this->t('Filter')];
    $form['#cache']['max-age'] = 0;
    return $form;
  }

  /** {@inheritdoc} */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $query = array_intersect_key($form_state->getValues(), array_flip(['name', 'kind', 'uid', 'deleted']));
    $form_state->setRedirect('xinshi_office.admin', [], ['query' => $query]);
  }

}
