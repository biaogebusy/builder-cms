<?php

namespace Drupal\replication\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Class SettingsForm.
 *
 * @package Drupal\replication\Form
 */
class SettingsForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return [
      'replication.settings',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'replication_settings';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('replication.settings');

    $options = [
      'uid' => $this->t('Map by UID'),
      'anonymous' => $this->t('Map to Anonymous'),
      'uid_1' => $this->t('Map to UID 1'),
    ];

    $form['config'] = array(
      '#type' => 'fieldset',
      '#title' => $this->t('Replication configuration'),
    );

    $form['config']['mapping_type'] = [
      '#type' => 'select',
      '#title' => $this->t('Users mapping type'),
      '#default_value' => $config->get('mapping_type'),
      '#options' => $options,
      '#description' => $this->t("Select how users will be mapped when they can't be mapped by username or email."),
    ];

    $form['config']['uid'] = [
      '#type' => 'textfield',
      '#title' => $this->t('UID'),
      '#default_value' => $config->get('mapping_type') === 'uid' ? $config->get('uid') : '',
      '#maxlength' => 60,
      '#size' => 30,
      '#states' => [
        'visible' => [
          'select[name="mapping_type"]' => ['value' => 'uid'],
        ],
      ],
    ];

    $form['config']['changes_limit'] = [
      '#type' => 'number',
      '#title' => t('Changes limit'),
      '#default_value' => $config->get('changes_limit'),
      '#description' => $this->t("This is the limit of changes the 
      replicator will GET per request, if the limit is a smaller number than 
      the total changes, then it will do multiple requests to get all the 
      changes. The bigger this number is, the slower will be the request, but at 
      the same time - the smaller is the limit, the higher is the number of 
      requests, so, there should be set an optimal limit, to not impact the 
      performance. Values range 10 - 1000."),
      '#required' => TRUE,
      '#min' => 10,
      '#max' => 1000,
      '#step' => 10,
    ];

    $form['config']['bulk_docs_limit'] = [
      '#type' => 'number',
      '#title' => t('Bulk docs limit'),
      '#default_value' => $config->get('bulk_docs_limit'),
      '#description' => $this->t("This is the limit of entities the 
      replicator will POST per request, if the limit is a smaller number than 
      the total number of entities that have to be transferred to the destination, 
      then it will do multiple requests to transfer all the entities. The bigger 
      this number is, the slower will be the request and the destination site will 
      need more resources to process all the data, so, there should be set an 
      optimal limit, to not impact the performance. Values range 10 - 1000."),
      '#required' => TRUE,
      '#min' => 10,
      '#max' => 1000,
      '#step' => 10,
    ];

    $form['config']['replication_execution_limit'] = [
      '#type' => 'select',
      '#title' => $this->t('Replication execution limit'),
      '#default_value' => $config->get('replication_execution_limit'),
      '#options' => [
        1 => $this->t('1 hour'),
        2 => $this->t('2 hours'),
        4 => $this->t('4 hours'),
        8 => $this->t('8 hours'),
      ],
      '#description' => $this->t("The maximum time a replication can run, if it exceeds this time then the replication is marked as failed."),
      '#required' => TRUE,
    ];

    $form['config']['verbose_logging'] = [
      '#type' => 'checkbox',
      '#title' => t('Verbose logging'),
      '#default_value' => (int) $config->get('verbose_logging'),
      '#description' => $this->t('This will enable verbose replication logging.'),
    ];

    $form['config']['actions']['#type'] = 'actions';
    $form['config']['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Save configuration'),
      '#button_type' => 'primary',
    ];

    // By default, render the form using system-config-form.html.twig.
    $form['#theme'] = 'system_config_form';

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    parent::validateForm($form, $form_state);

    $storage = \Drupal::entityTypeManager()->getStorage('user');
    $uid = trim($form_state->getValue('uid'));
    if ($form_state->getValue('mapping_type') === 'uid' && is_numeric($uid)) {
      if (!$storage->load($uid)) {
        $form_state->setErrorByName('uid', "Provided UID doesn't exist.");
      }
    }
    elseif ($form_state->getValue('mapping_type') === 'uid' && !is_numeric($uid)) {
      $form_state->setErrorByName('uid', 'Empty or wrong format for the UID field.');
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    parent::submitForm($form, $form_state);

    $config = $this->config('replication.settings');
    $mapping_type = $form_state->getValue('mapping_type');
    switch ($mapping_type) {
      case 'uid':
        $uid = $form_state->getValue('uid');
        break;
      case 'anonymous':
        $uid = 0;
        break;
      case 'uid_1':
        $uid = 1;
        break;
      default:
        $uid = NULL;
    }

    $changes_limit = $form_state->getValue('changes_limit');
    $bulk_docs_limit = $form_state->getValue('bulk_docs_limit');
    $replication_execution_limit = $form_state->getValue('replication_execution_limit');
    $verbose_logging = (bool) $form_state->getValue('verbose_logging');

    $config
      ->set('mapping_type', $mapping_type)
      ->set('changes_limit', $changes_limit)
      ->set('bulk_docs_limit', $bulk_docs_limit)
      ->set('replication_execution_limit', $replication_execution_limit)
      ->set('verbose_logging', $verbose_logging)
      ->set('uid', trim($uid))
      ->save();
  }

}
