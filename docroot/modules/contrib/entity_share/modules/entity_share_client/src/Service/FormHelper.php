<?php

declare(strict_types = 1);

namespace Drupal\entity_share_client\Service;

use Drupal\Component\Render\FormattableMarkup;
use Drupal\Component\Serialization\Json;
use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Language\LanguageManager;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Link;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Url;
use Drupal\entity_share\EntityShareUtility;
use Drupal\entity_share_client\Entity\RemoteInterface;
use Drupal\entity_share_client\Exception\ResourceTypeNotFoundException;
use Drupal\jsonapi\ResourceType\ResourceTypeRepositoryInterface;

/**
 * Service to extract code out of the PullForm.
 *
 * @package Drupal\entity_share_client\Service
 */
class FormHelper implements FormHelperInterface {

  use StringTranslationTrait;

  /**
   * The format for the remote changed time.
   *
   * Long format.
   */
  const CHANGED_FORMAT = 'l, F j, Y - H:i';

  /**
   * The resource type repository.
   *
   * @var \Drupal\jsonapi\ResourceType\ResourceTypeRepositoryInterface
   */
  protected $resourceTypeRepository;

  /**
   * The bundle infos from the website.
   *
   * @var array
   */
  protected $bundleInfos;

  /**
   * The entity type definitions.
   *
   * @var \Drupal\Core\Entity\EntityTypeInterface[]
   */
  protected $entityDefinitions;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The language manager.
   *
   * @var \Drupal\Core\Language\LanguageManagerInterface
   */
  protected $languageManager;

  /**
   * The state information service.
   *
   * @var \Drupal\entity_share_client\Service\StateInformationInterface
   */
  protected $stateInformation;

  /**
   * The module handler service.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $moduleHandler;

  /**
   * JsonapiHelper constructor.
   *
   * @param \Drupal\jsonapi\ResourceType\ResourceTypeRepositoryInterface $resource_type_repository
   *   The resource type repository.
   * @param \Drupal\Core\Entity\EntityTypeBundleInfoInterface $entity_type_bundle_info
   *   The entity type bundle info service.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\Language\LanguageManagerInterface $language_manager
   *   The language manager.
   * @param \Drupal\entity_share_client\Service\StateInformationInterface $state_information
   *   The state information service.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler service.
   */
  public function __construct(
    ResourceTypeRepositoryInterface $resource_type_repository,
    EntityTypeBundleInfoInterface $entity_type_bundle_info,
    EntityTypeManagerInterface $entity_type_manager,
    LanguageManagerInterface $language_manager,
    StateInformationInterface $state_information,
    ModuleHandlerInterface $module_handler
  ) {
    $this->resourceTypeRepository = $resource_type_repository;
    $this->bundleInfos = $entity_type_bundle_info->getAllBundleInfo();
    $this->entityDefinitions = $entity_type_manager->getDefinitions();
    $this->entityTypeManager = $entity_type_manager;
    $this->languageManager = $language_manager;
    $this->stateInformation = $state_information;
    $this->moduleHandler = $module_handler;
  }

  /**
   * {@inheritdoc}
   */
  public function buildEntitiesOptions(array $json_data, RemoteInterface $remote, $channel_id) {
    $options = [];
    foreach (EntityShareUtility::prepareData($json_data) as $data) {
      $this->addOptionFromJson($options, $data, $remote, $channel_id);
    }
    return $options;
  }

  /**
   * Helper function to add an option.
   *
   * @param array $options
   *   The array of options for the tableselect form type element.
   * @param array $data
   *   An array of data.
   * @param \Drupal\entity_share_client\Entity\RemoteInterface $remote
   *   The selected remote.
   * @param string $channel_id
   *   The selected channel id.
   * @param int $level
   *   The level of indentation.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   * @throws \InvalidArgumentException
   * @throws \Drupal\entity_share_client\Exception\ResourceTypeNotFoundException
   */
  protected function addOptionFromJson(array &$options, array $data, RemoteInterface $remote, $channel_id, $level = 0) {
    $parsed_type = explode('--', $data['type']);
    $entity_type_id = $parsed_type[0];
    $bundle_id = $parsed_type[1];

    $entity_type = $this->entityTypeManager->getStorage($entity_type_id)->getEntityType();
    $entity_keys = $entity_type->getKeys();

    $resource_type = $this->resourceTypeRepository->get(
      $entity_type_id,
      $bundle_id
    );

    if ($resource_type == NULL) {
      throw new ResourceTypeNotFoundException("Trying to import an entity type '{$entity_type_id}' of bundle '{$bundle_id}' which does not exist on the website.");
    }

    $status_info = $this->stateInformation->getStatusInfo($data);

    // Prepare remote changed info.
    $remote_changed_info = '';
    if ($resource_type->hasField('changed')) {
      $changed_public_name = $resource_type->getPublicName('changed');
      if (!empty($data['attributes'][$changed_public_name])) {
        if (is_numeric($data['attributes'][$changed_public_name])) {
          $remote_changed_date = DrupalDateTime::createFromTimestamp($data['attributes'][$changed_public_name]);
          $remote_changed_info = $remote_changed_date->format(self::CHANGED_FORMAT, [
            'timezone' => date_default_timezone_get(),
          ]);
        }
        else {
          $remote_changed_date = DrupalDateTime::createFromFormat(\DateTime::RFC3339, $data['attributes'][$changed_public_name]);
          if ($remote_changed_date) {
            $remote_changed_info = $remote_changed_date->format(self::CHANGED_FORMAT, [
              'timezone' => date_default_timezone_get(),
            ]);
          }
        }
      }
    }

    $options[$data['id']] = [
      'label' => $this->getOptionLabel($data, $status_info, $entity_keys, $remote->get('url'), $level),
      'type' => $entity_type->getLabel(),
      'bundle' => $this->bundleInfos[$entity_type_id][$bundle_id]['label'],
      'language' => $this->getEntityLanguageLabel($data, $entity_keys),
      'changed' => $remote_changed_info,
      'status' => [
        'data' => $status_info['label'],
        'class' => $status_info['class'],
      ],
      'policy' => $status_info['policy'],
    ];

    if ($this->moduleHandler->moduleExists('entity_share_diff')) {
      $id_public_name = $resource_type->getPublicName($entity_keys['id']);
      if (
        in_array($status_info['info_id'], [
          StateInformationInterface::INFO_ID_CHANGED,
          StateInformationInterface::INFO_ID_NEW_TRANSLATION,
        ]) &&
        !is_null($status_info['local_revision_id']) &&
        isset($data['attributes'][$id_public_name])
      ) {
        $options[$data['id']]['status']['data'] = new FormattableMarkup('@label: @diff_link', [
          '@label' => $options[$data['id']]['status']['data'],
          '@diff_link' => Link::createFromRoute($this->t('Diff'), 'entity_share_diff.comparison', [
            'left_revision_id' => $status_info['local_revision_id'],
            'remote' => $remote->id(),
            'channel_id' => $channel_id,
            'uuid' => $data['id'],
          ], [
            'attributes' => [
              'class' => [
                'use-ajax',
              ],
              'data-dialog-type' => 'modal',
              'data-dialog-options' => Json::encode(['width' => '90%']),
            ],
          ])->toString(),
        ]);
      }
    }
  }

  /**
   * Helper function to calculate the label to display in the table.
   *
   * @param array $data
   *   An array of data.
   * @param array $status_info
   *   An array of status info as returned by
   *   StateInformationInterface::getStatusInfo().
   * @param array $entity_keys
   *   The entity keys.
   * @param string $remote_url
   *   The remote url.
   * @param int $level
   *   The level of indentation.
   *
   * @return \Drupal\Component\Render\FormattableMarkup|string
   *   The prepared label.
   */
  protected function getOptionLabel(array $data, array $status_info, array $entity_keys, $remote_url, $level) {
    $indentation = '';
    for ($i = 1; $i <= $level; $i++) {
      $indentation .= '<div class="indentation">&nbsp;</div>';
    }

    $parsed_type = explode('--', $data['type']);
    $entity_type_id = $parsed_type[0];
    $bundle_id = $parsed_type[1];

    $resource_type = $this->resourceTypeRepository->get(
      $entity_type_id,
      $bundle_id
    );
    $label_public_name = FALSE;
    if (isset($entity_keys['label']) && $resource_type->hasField($entity_keys['label'])) {
      $label_public_name = $resource_type->getPublicName($entity_keys['label']);
    }

    // Some entity type may not have a label key and the label is calculated
    // using the label() method on the entity but at this step the entity is not
    // denormalized and also as we are not on the server website, we would not
    // have the data required to calculate the entity's label.
    if (isset($data['attributes'][$label_public_name])) {
      $label = $data['attributes'][$label_public_name];
    }
    elseif (isset($entity_keys['id']) && $resource_type->hasField($entity_keys['id'])) {
      $label = $data['attributes'][$resource_type->getPublicName($entity_keys['id'])];
    }
    else {
      $label = $data['id'];
    }

    // Get link to remote entity. Need to manually create the link to avoid
    // getting alias from local website.
    if (isset($entity_keys['id']) && $resource_type->hasField($entity_keys['id'])) {
      $remote_entity_id = (string) $data['attributes'][$resource_type->getPublicName($entity_keys['id'])];
      $entity_definition = $this->entityDefinitions[$entity_type_id];

      if ($entity_definition->hasLinkTemplate('canonical')) {
        $canonical_path = $entity_definition->getLinkTemplate('canonical');
        $remote_entity_path = str_replace('{' . $entity_type_id . '}', $remote_entity_id, $canonical_path);
        $remote_entity_url = Url::fromUri($remote_url . $remote_entity_path);

        $label = Link::fromTextAndUrl($label, $remote_entity_url)->toString();
      }
    }

    // Prepare link to local entity if it exists.
    $local_link = '';
    if (!is_null($status_info['local_entity_link'])) {
      $local_link = new Link($this->t('(View local)'), $status_info['local_entity_link']);
      $local_link = $local_link->toString();
    }

    $label = new FormattableMarkup($indentation . '@label ' . $local_link, [
      '@label' => $label,
    ]);

    return $label;
  }

  /**
   * Helper function to get the language from an extracted entity.
   *
   * We can't use $entity->language() because if the entity is in a language not
   * enabled, it is the site default language that is returned.
   *
   * @param array $data
   *   The data from the JSON:API payload.
   * @param array $entity_keys
   *   The entity keys from the entity definition.
   *
   * @return string
   *   The language of the entity.
   */
  protected function getEntityLanguageLabel(array $data, array $entity_keys) {
    if (!isset($entity_keys['langcode']) || empty($entity_keys['langcode'])) {
      return $this->t('Untranslatable entity');
    }

    $parsed_type = explode('--', $data['type']);
    $resource_type = $this->resourceTypeRepository->get(
      $parsed_type[0],
      $parsed_type[1]
    );
    $langcode = $data['attributes'][$resource_type->getPublicName($entity_keys['langcode'])];
    $language = $this->languageManager->getLanguage($langcode);
    // Check if the entity is in an enabled language.
    if (is_null($language)) {
      $language_list = LanguageManager::getStandardLanguageList();
      if (isset($language_list[$langcode])) {
        $entity_language = $language_list[$langcode][0] . ' ' . $this->t('(not enabled)', [], ['context' => 'language']);
      }
      else {
        $entity_language = $this->t('Entity in an unsupported language.');
      }
    }
    else {
      $entity_language = $language->getName();
    }

    return $entity_language;
  }

}
