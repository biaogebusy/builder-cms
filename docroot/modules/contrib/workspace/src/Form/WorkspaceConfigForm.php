<?php

namespace Drupal\workspace\Form;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\multiversion\Workspace\WorkspaceManagerInterface;
use Drupal\workspace\Entity\WorkspacePointer;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Class WorkspaceConfigForm.
 */
class WorkspaceConfigForm extends ConfigFormBase {

  /**
   * Drupal\multiversion\Workspace\WorkspaceManagerInterface definition.
   *
   * @var \Drupal\multiversion\Workspace\WorkspaceManagerInterface
   */
  protected $workspaceManager;

  protected $entityTypeManager;

  /**
   * Constructs a new WorkspaceConfigForm object.
   */
  public function __construct(
    ConfigFactoryInterface $config_factory,
    WorkspaceManagerInterface $workspace_manager,
    EntityTypeManagerInterface $entity_type_manager
  ) {
    parent::__construct($config_factory);
    $this->workspaceManager = $workspace_manager;
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.factory'),
      $container->get('workspace.manager'),
      $container->get('entity_type.manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return [
      'workspace.settings',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'workspace_config_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config_settings = $this->config('workspace.settings');

    $form['default'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Default settings'),
    ];
    $form['default']['description'] = [
      '#type' => 'markup',
      '#markup' => $this->t('Settings that will be used as default when creating new workspaces.'),
    ];

    $this->addDefaultTargetWorkspaceSettingField($form, $config_settings);
    $this->addFilterSettingsFields($form, $config_settings);

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    parent::submitForm($form, $form_state);
    $config_settings = $this->config('workspace.settings');

    foreach ([
      'push_replication_settings' => $this->t('Push replication settings'),
      'pull_replication_settings' => $this->t('Pull replication settings'),
      'upstream' => $this->t('Default target workspace'),
    ] as $key => $item) {
      $value_settings = $form_state->getValue($key);
      $config_settings->set($key, $value_settings)
        ->save();
    }
  }

  /**
   * {@inheritdoc}
   */
  private function addFilterSettingsFields(&$form, $config_settings) {
    $options = [];
    $replication_settings = $this->entityTypeManager->getStorage('replication_settings')->loadByProperties([]);
    foreach ($replication_settings as $replication_setting) {
      $options[$replication_setting->id()] = $replication_setting->label();
    }

    $form['default']['pull_replication_settings'] = [
      '#type' => 'select',
      '#required' => FALSE,
      '#empty_option' => $this->t('- None -'),
      '#empty_value' => '_none',
      '#title' => $this->t('Default replication settings on update'),
      '#description' => $this->t('The default settings to use when content is pulled from upstream.'),
      '#options' => $options,
      '#default_value' => $config_settings->get('pull_replication_settings'),
    ];

    $form['default']['push_replication_settings'] = [
      '#type' => 'select',
      '#required' => FALSE,
      '#empty_option' => $this->t('- None -'),
      '#empty_value' => '_none',
      '#title' => $this->t('Default replication settings on deploy'),
      '#description' => $this->t('The default settings to use when content is pushed to upstream.'),
      '#options' => $options,
      '#default_value' => $config_settings->get('push_replication_settings'),
    ];
  }

  /**
   * {@inheritdoc}
   */
  private function addDefaultTargetWorkspaceSettingField(&$form, $config_settings) {
    $options = [];
    $workspace_pointers = WorkspacePointer::loadMultiple();
    foreach ($workspace_pointers as $workspace_pointer) {
      if ($workspace_pointer->getWorkspaceAvailable() === FALSE) {
        continue;
      }
      /** @var \Drupal\multiversion\Entity\WorkspaceInterface $workspace */
      $workspace = $workspace_pointer->getWorkspace();
      if ($workspace && !$workspace->isPublished()) {
        continue;
      }
      $options[$workspace_pointer->id()] = $workspace_pointer->label();
    }

    $form['default']['upstream'] = [
      '#type' => 'select',
      '#required' => FALSE,
      '#empty_option' => $this->t('- None -'),
      '#empty_value' => 0,
      '#title' => $this->t('Assign default target workspace'),
      '#description' => $this->t('The default workspace to push to and pull from.'),
      '#options' => $options,
      '#default_value' => $config_settings->get('upstream'),
    ];
  }

}
