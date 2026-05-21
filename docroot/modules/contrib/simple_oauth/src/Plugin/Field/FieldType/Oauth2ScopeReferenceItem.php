<?php

namespace Drupal\simple_oauth\Plugin\Field\FieldType;

use Drupal\Core\Field\FieldItemBase;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\TypedData\DataDefinition;
use Drupal\Core\TypedData\OptionsProviderInterface;
use Drupal\simple_oauth\Oauth2ScopeInterface;
use Drupal\simple_oauth\Plugin\Oauth2GrantManager;

/**
 * Plugin implementation of the 'oauth2_scope_reference' field type.
 *
 * @FieldType(
 *   id = "oauth2_scope_reference",
 *   label = @Translation("OAuth2 scope reference"),
 *   description = @Translation("An entity field containing a oauth2_scope reference."),
 *   category = "reference",
 *   no_ui = TRUE,
 *   default_widget = "oauth2_scope_reference",
 *   list_class = "\Drupal\simple_oauth\Plugin\Field\FieldType\Oauth2ScopeReferenceItemList",
 * )
 */
class Oauth2ScopeReferenceItem extends FieldItemBase implements Oauth2ScopeReferenceItemInterface, OptionsProviderInterface {

  /**
   * {@inheritdoc}
   */
  public static function mainPropertyName() {
    return 'scope_id';
  }

  /**
   * {@inheritdoc}
   */
  public static function propertyDefinitions(FieldStorageDefinitionInterface $field_definition) {
    $properties['scope_id'] = DataDefinition::create('string')
      ->setLabel(t('Scope ID'))
      ->setRequired(TRUE);

    return $properties;
  }

  /**
   * {@inheritdoc}
   */
  public static function defaultFieldSettings() {
    return [
      // When set, only show scopes enabled for this specific grant type.
      // This filtering is independent of the entity's grant_types field.
      'filter_grant_type' => '',
    ] + parent::defaultFieldSettings();
  }

  /**
   * {@inheritdoc}
   */
  public function fieldSettingsForm(array $form, FormStateInterface $form_state) {
    $element = parent::fieldSettingsForm($form, $form_state);

    $element['filter_grant_type'] = [
      '#type' => 'select',
      '#title' => $this->t('Filter by grant type'),
      '#description' => $this->t('Optionally filter available scopes to only those enabled for a specific grant type.'),
      '#default_value' => $this->getSetting('filter_grant_type'),
      '#options' => Oauth2GrantManager::getAvailablePluginsAsOptions(),
    ];

    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public static function schema(FieldStorageDefinitionInterface $field_definition) {
    return [
      'columns' => [
        'scope_id' => [
          'description' => 'The scope id',
          'type' => 'varchar',
          'length' => 255,
        ],
      ],
      'indexes' => ['scope_id' => ['scope_id']],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function isEmpty() {
    $value = $this->get('scope_id')->getValue();
    return $value === NULL || $value === '';
  }

  /**
   * {@inheritdoc}
   */
  public function getScope(): ?Oauth2ScopeInterface {
    if (empty($this->scope_id)) {
      return NULL;
    }

    /** @var \Drupal\simple_oauth\Oauth2ScopeAdapterInterface $scope_provider */
    $scope_provider = \Drupal::service('simple_oauth.oauth2_scope.provider');

    return $scope_provider->load($this->scope_id);
  }

  /**
   * {@inheritdoc}
   */
  public function getPossibleValues(?AccountInterface $account = NULL) {
    return array_keys($this->getPossibleOptions($account));
  }

  /**
   * {@inheritdoc}
   *
   * Returns available scopes filtered by the field's filter_grant_type setting.
   *
   * IMPORTANT: Scope visibility is controlled exclusively by the field-level
   * filter_grant_type setting. The parent entity's (e.g., consumer's) enabled
   * grant types do NOT affect which scopes are available. This allows fields
   * to show scopes for grant types not currently enabled on the entity.
   *
   * Examples:
   * - If filter_grant_type is empty: returns ALL scopes
   * - If filter_grant_type is 'client_credentials': returns only scopes
   *   enabled for the client_credentials grant type, regardless of which
   *   grant types are enabled on the consumer entity
   */
  public function getPossibleOptions(?AccountInterface $account = NULL) {
    /** @var \Drupal\simple_oauth\Oauth2ScopeAdapterInterface $scope_provider */
    $scope_provider = \Drupal::service('simple_oauth.oauth2_scope.provider');

    $filter_grant_type = $this->getSetting('filter_grant_type');
    $scopes = empty($filter_grant_type)
      ? $scope_provider->loadMultiple()
      : $scope_provider->loadByGrantType($filter_grant_type);

    return array_map(function (Oauth2ScopeInterface $scope) {
      return $scope->getName();
    }, $scopes);
  }

  /**
   * {@inheritdoc}
   */
  public function getSettableValues(?AccountInterface $account = NULL) {
    return array_keys($this->getPossibleOptions($account));
  }

  /**
   * {@inheritdoc}
   */
  public function getSettableOptions(?AccountInterface $account = NULL) {
    return $this->getPossibleOptions($account);
  }

}
