<?php

namespace Drupal\simple_oauth\Form;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\TypedConfigManagerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Form\RedundantEditableConfigNamesTrait;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Url;
use Drupal\simple_oauth\Plugin\ScopeProviderManagerInterface;
use Drupal\simple_oauth\Service\Filesystem\FileSystemCheckerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * The settings form.
 *
 * @internal
 */
class Oauth2TokenSettingsForm extends ConfigFormBase {

  use RedundantEditableConfigNamesTrait;

  /**
   * The file system checker.
   *
   * @var \Drupal\simple_oauth\Service\Filesystem\FileSystemCheckerInterface
   */
  protected FileSystemCheckerInterface $fileSystemChecker;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * The scope provider plugin manager.
   *
   * @var \Drupal\simple_oauth\Plugin\ScopeProviderManagerInterface
   */
  protected ScopeProviderManagerInterface $scopeProviderManager;

  /**
   * Oauth2TokenSettingsForm constructor.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *   The factory for configuration objects.
   * @param \Drupal\Core\Config\TypedConfigManagerInterface $typedConfigManager
   *   The typed config manager.
   * @param \Drupal\simple_oauth\Service\Filesystem\FileSystemCheckerInterface $file_system_checker
   *   The simple_oauth.filesystem service.
   * @param \Drupal\Core\Messenger\MessengerInterface $messenger
   *   The messenger service.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\simple_oauth\Plugin\ScopeProviderManagerInterface $scope_provider_manager
   *   The scope provider plugin manager.
   */
  public function __construct(
    ConfigFactoryInterface $configFactory,
    TypedConfigManagerInterface $typedConfigManager,
    FileSystemCheckerInterface $file_system_checker,
    MessengerInterface $messenger,
    EntityTypeManagerInterface $entity_type_manager,
    ScopeProviderManagerInterface $scope_provider_manager,
  ) {
    parent::__construct($configFactory, $typedConfigManager);
    $this->fileSystemChecker = $file_system_checker;
    $this->messenger = $messenger;
    $this->entityTypeManager = $entity_type_manager;
    $this->scopeProviderManager = $scope_provider_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): self {
    return new static(
      $container->get('config.factory'),
      $container->get('config.typed'),
      $container->get('simple_oauth.filesystem_checker'),
      $container->get('messenger'),
      $container->get('entity_type.manager'),
      $container->get('plugin.manager.scope_provider')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'oauth2_token_settings';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $scope_provider_options = $this->getScopeProviderOptions();
    $form['scope_provider'] = [
      '#type' => 'select',
      '#title' => $this->t('Scope provider'),
      '#description' => $this->t('The active scope provider. The dynamic scope provider makes use of config entity; which makes it possible to manage the scopes via the UI.'),
      '#options' => $scope_provider_options,
      '#disabled' => $this->originalScopeProviderHasData() || count($scope_provider_options) === 1,
      '#required' => TRUE,
      '#config_target' => 'simple_oauth.settings:scope_provider',
    ];
    $form['token_cron_batch_size'] = [
      '#type' => 'number',
      '#title' => $this->t('Token batch size.'),
      '#description' => $this->t('The number of expired token to delete per batch during cron. Use 0 for no limit.'),
      '#required' => TRUE,
      '#config_target' => 'simple_oauth.settings:token_cron_batch_size',
    ];
    $form['public_key'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Public Key'),
      '#description' => $this->t('The path to the public key file.'),
      '#config_target' => 'simple_oauth.settings:public_key',
      '#element_validate' => ['::validateExistingFile'],
      '#required' => TRUE,
      '#attributes' => ['id' => 'pubk'],
    ];
    $form['private_key'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Private Key'),
      '#description' => $this->t('The path to the private key file.'),
      '#config_target' => 'simple_oauth.settings:private_key',
      '#element_validate' => ['::validateExistingFile'],
      '#required' => TRUE,
      '#attributes' => ['id' => 'pk'],
    ];

    $form['actions'] = [
      'actions' => [
        '#cache' => ['max-age' => 0],
        '#weight' => 20,
      ],
    ];

    // Generate Key Modal Button if openssl extension is enabled.
    if ($this->fileSystemChecker->isExtensionEnabled('openssl')) {
      // Generate Modal Button.
      $form['actions']['generate']['keys'] = [
        '#type' => 'link',
        '#title' => $this->t('Generate keys'),
        '#url' => Url::fromRoute(
          'oauth2_token.settings.generate_key',
          [],
          ['query' => ['pubk_id' => 'pubk', 'pk_id' => 'pk']]
        ),
        '#attributes' => [
          'class' => ['use-ajax', 'button'],
        ],
      ];

      // Attach Drupal Modal Dialog library.
      $form['#attached']['library'][] = 'core/drupal.dialog.ajax';
    }
    else {
      // Generate Notice Info Message about enabling openssl extension.
      $this->messenger->addMessage(
        $this->t('Enabling the PHP OpenSSL Extension will permit you generate the keys from this form.'),
        'warning'
      );
    }

    return parent::buildForm($form, $form_state);
  }

  /**
   * Validates if the file exists.
   *
   * @param array $element
   *   The element being processed.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   * @param array $complete_form
   *   The complete form structure.
   */
  public function validateExistingFile(array &$element, FormStateInterface $form_state, array &$complete_form): void {
    if (!empty($element['#value'])) {
      $path = $element['#value'];
      // Does the file exist?
      if (!$this->fileSystemChecker->fileExist($path)) {
        $form_state->setError($element, $this->t('The %field file does not exist.', ['%field' => $element['#title']]));
      }
      // Is the file readable?
      if (!$this->fileSystemChecker->isReadable($path)) {
        $form_state->setError($element, $this->t('The %field file at the specified location is not readable.', ['%field' => $element['#title']]));
      }
    }
  }

  /**
   * Get the scope provider select options.
   *
   * @return string[]
   *   Returns the options.
   */
  protected function getScopeProviderOptions(): array {
    $options = [];
    foreach ($this->scopeProviderManager->getInstances() as $plugin) {
      $options[$plugin->getPluginId()] = $plugin->label();
    }

    return $options;
  }

  /**
   * Original scope provider has data referenced.
   *
   * @return bool
   *   Returns boolean, indicating if there is referenced data.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  protected function originalScopeProviderHasData(): bool {
    $consumer_storage = $this->entityTypeManager->getStorage('consumer');
    $query = $consumer_storage->getQuery()
      ->accessCheck()
      ->condition('scopes', NULL, 'IS NOT NULL')
      ->range(0, 1);
    $result = $query->execute();

    return !empty($result);
  }

}
