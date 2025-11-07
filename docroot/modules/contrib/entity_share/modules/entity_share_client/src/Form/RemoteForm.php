<?php

declare(strict_types = 1);

namespace Drupal\entity_share_client\Form;

use Drupal\Component\Utility\UrlHelper;
use Drupal\Core\Entity\EntityForm;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Form\SubformState;
use Drupal\Core\Plugin\PluginFormInterface;
use Drupal\entity_share_client\ClientAuthorization\ClientAuthorizationInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Entity form of the remote entity.
 *
 * @package Drupal\entity_share_client\Form
 */
class RemoteForm extends EntityForm {

  /**
   * Injected plugin service.
   *
   * @var \Drupal\entity_share_client\ClientAuthorization\ClientAuthorizationPluginManager
   */
  protected $authPluginManager;

  /**
   * The currently configured auth plugin.
   *
   * @var \Drupal\entity_share_client\ClientAuthorization\ClientAuthorizationInterface
   */
  protected $authPlugin;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    $instance = parent::create($container);
    $instance->authPluginManager = $container->get('plugin.manager.entity_share_client_authorization');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function form(array $form, FormStateInterface $form_state) {
    $form = parent::form($form, $form_state);

    /** @var \Drupal\entity_share_client\Entity\RemoteInterface $remote */
    $remote = $this->entity;
    $form['label'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Label'),
      '#maxlength' => 255,
      '#default_value' => $remote->label(),
      '#description' => $this->t('Label for the remote website.'),
      '#required' => TRUE,
    ];

    $form['id'] = [
      '#type' => 'machine_name',
      '#default_value' => $remote->id(),
      '#machine_name' => [
        'source' => ['label'],
        'exists' => '\Drupal\entity_share_client\Entity\Remote::load',
      ],
      '#disabled' => !$remote->isNew(),
    ];

    $form['url'] = [
      '#type' => 'url',
      '#title' => $this->t('URL'),
      '#maxlength' => 255,
      '#description' => $this->t('The remote URL. Example: http://example.com'),
      '#default_value' => $remote->get('url'),
      '#required' => TRUE,
    ];

    $this->addAuthOptions($form, $form_state);

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    // Validate URL.
    if (!UrlHelper::isValid($form_state->getValue('url'), TRUE)) {
      $form_state->setError($form['url'], $this->t('Invalid URL.'));
    }
    $selectedPlugin = $this->getSelectedPlugin($form, $form_state);
    if ($selectedPlugin instanceof PluginFormInterface) {
      $subformState = SubformState::createForSubform($form['auth']['data'], $form, $form_state);
      $selectedPlugin->validateConfigurationForm($form['auth']['data'], $subformState);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    parent::submitForm($form, $form_state);
    $selectedPlugin = $this->getSelectedPlugin($form, $form_state);
    $subformState = SubformState::createForSubform($form['auth']['data'], $form, $form_state);
    // Store the remote entity in case the plugin submission needs its data.
    $subformState->set('remote', $this->entity);
    $selectedPlugin->submitConfigurationForm($form['auth']['data'], $subformState);
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state) {
    /** @var \Drupal\entity_share_client\Entity\RemoteInterface $remote */
    $remote = $this->entity;

    if (!empty($form['auth']['#plugins'])) {
      $selectedPlugin = $this->getSelectedPlugin($form, $form_state);
      $remote->mergePluginConfig($selectedPlugin);
    }

    $status = $remote->save();

    switch ($status) {
      case SAVED_NEW:
        $this->messenger()->addStatus($this->t('Created the %label remote website.', [
          '%label' => $remote->label(),
        ]));
        break;

      default:
        $this->messenger()->addStatus($this->t('Saved the %label remote website.', [
          '%label' => $remote->label(),
        ]));
    }
    $form_state->setRedirectUrl($remote->toUrl('collection'));
  }

  /**
   * Helper function to build the authorization options in the form.
   *
   * @param array $form
   *   The form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current form state.
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   */
  protected function addAuthOptions(array &$form, FormStateInterface $form_state) {
    $options = [];
    $plugins = [];
    $commonUuid = '';
    if ($this->hasAuthPlugin()) {
      $options[$this->authPlugin->getPluginId()] = $this->authPlugin->getLabel();
      $plugins[$this->authPlugin->getPluginId()] = $this->authPlugin;
      // Ensure all plugins will have the same uuid in the configuration to
      // avoid duplication of entries in the key value store.
      $existing_plugin_configuration = $this->authPlugin->getConfiguration();
      $commonUuid = $existing_plugin_configuration['uuid'];
    }
    $availablePlugins = $this->authPluginManager->getAvailablePlugins($commonUuid);
    foreach ($availablePlugins as $id => $plugin) {
      if (empty($options[$id])) {
        // This plugin type was not previously set as an option.
        $options[$id] = $plugin->getLabel();
        $plugins[$id] = $plugin;
      }
    }
    // Do we have a value?
    $selected = $form_state->getValue('pid');
    if (!empty($selected)) {
      $selectedPlugin = $plugins[$selected];
    }
    elseif (!empty($this->authPlugin)) {
      // Is a plugin previously stored?
      $selectedPlugin = $this->authPlugin;
    }
    else {
      // Fallback: take the first option.
      $selectedPlugin = reset($plugins);
    }
    $form['auth'] = [
      '#type' => 'container',
      '#plugins' => $plugins,
      'pid' => [
        '#type' => 'radios',
        '#title' => $this->t('Authorization methods'),
        '#options' => $options,
        '#default_value' => $selectedPlugin->getPluginId(),
        '#ajax' => [
          'wrapper' => 'plugin-form-ajax-container',
          'callback' => [get_class($this), 'ajaxPluginForm'],
        ],
      ],
      'data' => [],
    ];
    $subformState = SubformState::createForSubform($form['auth']['data'], $form, $form_state);
    $form['auth']['data'] = $selectedPlugin->buildConfigurationForm($form['auth']['data'], $subformState);
    $form['auth']['data']['#tree'] = TRUE;
    $form['auth']['data']['#prefix'] = '<div id="plugin-form-ajax-container">';
    $form['auth']['data']['#suffix'] = '</div>';
  }

  /**
   * Callback function to return the credentials portion of the form.
   *
   * @param array $form
   *   The rebuilt form.
   * @param \Drupal\Core\Form\FormStateInterface $formState
   *   The current form state.
   *
   * @return array
   *   A portion of the render array.
   */
  public static function ajaxPluginForm(array $form, FormStateInterface $formState) {
    return $form['auth']['data'];
  }

  /**
   * Helper method to instantiate plugin from this entity.
   *
   * @return bool
   *   True if the remote entity has a plugin.
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   */
  protected function hasAuthPlugin() {
    /** @var \Drupal\entity_share_client\Entity\RemoteInterface $remote */
    $remote = $this->entity;
    $plugin = $remote->getAuthPlugin();
    if ($plugin instanceof ClientAuthorizationInterface) {
      $this->authPlugin = $plugin;
      return TRUE;
    }
    return FALSE;
  }

  /**
   * Helper method to get selected plugin from the form.
   *
   * @param array $form
   *   The form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current form state.
   *
   * @return \Drupal\entity_share_client\ClientAuthorization\ClientAuthorizationInterface
   *   The selected plugin.
   */
  protected function getSelectedPlugin(
    array &$form,
    FormStateInterface $form_state) {
    $authPluginId = $form_state->getValue('pid');
    $plugins = $form['auth']['#plugins'];
    /** @var \Drupal\entity_share_client\ClientAuthorization\ClientAuthorizationInterface $selectedPlugin */
    $selectedPlugin = $plugins[$authPluginId];
    return $selectedPlugin;
  }

}
