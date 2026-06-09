<?php

namespace Drupal\ai_external_moderation\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\ai\AiProviderPluginManager;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Configure AI External moderations module.
 */
class ModerationConfigurations extends ConfigFormBase {

  use StringTranslationTrait;

  /**
   * Config settings.
   */
  const CONFIG_NAME = 'ai_external_moderation.settings';

  /**
   * The provider manager.
   *
   * @var \Drupal\ai\AiProviderPluginManager
   */
  protected AiProviderPluginManager $providerManager;

  /**
   * Constructor.
   */
  final public function __construct(AiProviderPluginManager $provider_manager) {
    $this->providerManager = $provider_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('ai.provider')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'ai_content_settings';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames(): array {
    return [
      static::CONFIG_NAME,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {

    // Load config.
    $config = $this->config(static::CONFIG_NAME);
    $providers = $this->providerManager->getProvidersForOperationType('chat');

    // Get all the setups.
    $i = 0;
    $moderations = $config->get('moderations') ?? [];

    // If its in the form state, then we are adding a new one.
    if (empty($moderations) && $form_state->getValue('moderations')) {
      $moderations = $form_state->getValue('moderations');
    }
    foreach ($moderations as $moderation) {
      $this->moderationForm($form, $moderation, $i);
      $i++;
    }

    // $i is a count of how many moderation forms we've added: if we have more
    // providers than this number, we'll need additional forms to let people add
    // them.
    if (count($providers) > $i) {
      $this->moderationForm($form, [], $i, TRUE);
    }
    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    // Get the moderation.
    $moderations = $form_state->getValue('moderations');
    foreach ($moderations as $moderation) {
      $provider = $moderation['provider'];
      // Get models.
      $models = $moderation['models'];
      foreach ($models as $model) {
        $parts = explode('__', $model);
        if ($provider == $parts[0]) {
          $form_state->setErrorByName('moderations', $this->t('Model %model cannot be from the same provider as the provider %provider. This tool is only to set an external moderation layer on another provider.', [
            '%model' => $model,
            '%provider' => $provider,
          ]));
        }
      }
    }
    parent::validateForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    // Load config.
    $config = $this->config(static::CONFIG_NAME);
    $moderations = $form_state->getValue('moderations');
    // Remove empty moderation.
    foreach ($moderations as $i => $moderation) {
      if (empty($moderation['provider'])) {
        unset($moderations[$i]);
      }
      // Remove empty models.
      foreach ($moderation['models'] as $t => $model) {
        if (empty($model)) {
          unset($moderations[$i]['models'][$t]);
        }
      }

      // Unset the add model button.
      unset($moderations[$i]['add']);
    }
    $config->set('moderations', $moderations);
    $config->save();
    parent::submitForm($form, $form_state);
  }

  /**
   * One form element for each moderation.
   *
   * @param array $form
   *   The form passed by reference.
   * @param array $moderation
   *   The moderation.
   * @param int $i
   *   The index.
   * @param bool $open
   *   If the details should be open.
   */
  protected function moderationForm(&$form, array $moderation, int $i, bool $open = FALSE) {
    $form['moderations']['#tree'] = TRUE;

    $form['moderations'][$i] = [
      '#type' => 'details',
      '#title' => !empty($moderation['provider']) ? $this->t('External Moderation %provider', [
        '%provider' => $moderation['provider'],
      ]) : $this->t('External Moderation'),
      '#open' => $open,
      '#attributes' => [
        'id' => 'moderations-' . $i,
      ],
    ];

    // Get all moderation models.
    $moderation_models = $this->providerManager->getSimpleProviderModelOptions('moderation');
    // Get all providers for chat.
    $providers = $this->providerManager->getProvidersForOperationType('chat');
    $options = [];
    foreach ($providers as $provider) {
      $options[$provider['id']] = $provider['label'];
    }

    $form['moderations'][$i]['provider'] = [
      '#type' => 'select',
      '#title' => $this->t('Provider'),
      '#options' => $options,
      '#empty_option' => $this->t('Select a provider'),
      '#default_value' => $moderation['provider'] ?? NULL,
    ];

    $form['moderations'][$i]['tags'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Tags'),
      '#description' => $this->t('Select tags to invoke the model on. Should be comma separated. Empty means all.'),
      '#default_value' => $moderation['tags'] ?? NULL,
    ];

    $model_order = $moderation['models'] ?? [0 => NULL];
    $form['moderations'][$i]['model_title'] = [
      '#type' => 'item',
      '#title' => $this->t('Model Order'),
    ];

    $t = 0;

    foreach ($model_order as $t => $model) {
      $form['moderations'][$i]['models'][$t] = [
        '#type' => 'select',
        '#options' => $moderation_models,
        '#empty_option' => $this->t('Select a model'),
        '#default_value' => $model,
      ];

      // If we have no default value, this model still needs to be configured so
      // we will force it to be open.
      if (!$model) {
        $form['moderations'][$i]['#open'] = TRUE;
      }
    }

    // Moderation models includes an empty "choose an option" key, so the actual
    // number of available models is this minus one.
    $actual_models = count($moderation_models) - 1;

    // Whereas $t is the count of models select options we've added starting at
    // 0, so needs one adding to it to make the actual number.
    $added_models = $t + 1;

    // So now if we have more actual models than we've added select lists for,
    // let the user add another.
    if ($actual_models > $added_models) {
      $form['moderations'][$i]['add'] = [
        '#type' => 'submit',
        '#value' => 'Add Another Model (' . $i . ')',
        '#attributes' => [
          'data-add-model' => $i,
        ],
        '#submit' => ['::addModel'],
        '#ajax' => [
          'callback' => '::addModelCallback',
          'wrapper' => 'moderations-' . $i,
        ],
      ];
    }
  }

  /**
   * Add a model to the moderation.
   */
  public function addModel(array &$form, FormStateInterface $form_state) {
    $form_state->set('moderations', $form_state->getValue('moderations'));
    $form_state->setRebuild();
  }

  /**
   * Ajax callback for adding a model.
   */
  public function addModelCallback(array &$form, FormStateInterface $form_state) {
    $i = $form_state->getTriggeringElement()['#attributes']['data-add-model'];
    $form_state->setRebuild();
    return $form['moderations'][$i];
  }

}
