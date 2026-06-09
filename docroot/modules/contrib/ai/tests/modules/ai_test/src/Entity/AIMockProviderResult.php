<?php

declare(strict_types=1);

namespace Drupal\ai_test\Entity;

use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\ai_test\AIMockProviderResultInterface;

/**
 * Defines the ai mock provider result entity class.
 *
 * @ContentEntityType(
 *   id = "ai_mock_provider_result",
 *   label = @Translation("AI Mock Provider Result"),
 *   label_collection = @Translation("AI Mock Provider Results"),
 *   label_singular = @Translation("ai mock provider result"),
 *   label_plural = @Translation("ai mock provider results"),
 *   label_count = @PluralTranslation(
 *     singular = "@count ai mock provider results",
 *     plural = "@count ai mock provider results",
 *   ),
 *   handlers = {
 *     "list_builder" = "Drupal\ai_test\AIMockProviderResultListBuilder",
 *     "views_data" = "Drupal\views\EntityViewsData",
 *     "form" = {
 *       "add" = "Drupal\ai_test\Form\AIMockProviderResultForm",
 *       "edit" = "Drupal\ai_test\Form\AIMockProviderResultForm",
 *       "delete" = "Drupal\Core\Entity\ContentEntityDeleteForm",
 *       "delete-multiple-confirm" = "Drupal\Core\Entity\Form\DeleteMultipleForm",
 *     },
 *     "route_provider" = {
 *       "html" = "Drupal\ai_test\Routing\AIMockProviderResultHtmlRouteProvider",
 *     },
 *   },
 *   base_table = "ai_mock_provider_result",
 *   admin_permission = "administer ai_mock_provider_result",
 *   entity_keys = {
 *     "id" = "id",
 *     "label" = "label",
 *     "uuid" = "uuid",
 *   },
 *   links = {
 *     "collection" = "/admin/content/ai-mock-provider-result",
 *     "add-form" = "/admin/ai-mock-provider-result/add",
 *     "canonical" = "/admin/ai-mock-provider-result/{ai_mock_provider_result}",
 *     "edit-form" = "/admin/ai-mock-provider-result/{ai_mock_provider_result}",
 *     "delete-form" = "/admin/ai-mock-provider-result/{ai_mock_provider_result}/delete",
 *     "delete-multiple-form" = "/admin/content/ai-mock-provider-result/delete-multiple",
 *   },
 * )
 */
final class AIMockProviderResult extends ContentEntityBase implements AIMockProviderResultInterface {

  /**
   * {@inheritdoc}
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type): array {

    $fields = parent::baseFieldDefinitions($entity_type);

    $fields['label'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Label'))
      ->setRequired(TRUE)
      ->setSetting('max_length', 255)
      ->setDisplayOptions('form', [
        'type' => 'string_textfield',
        'weight' => -5,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayOptions('view', [
        'label' => 'hidden',
        'type' => 'string',
        'weight' => -5,
      ])
      ->setDisplayConfigurable('view', TRUE);

    $fields['request'] = BaseFieldDefinition::create('string_long')
      ->setLabel(new TranslatableMarkup('Request'))
      ->setDescription(new TranslatableMarkup('A YAML dump of the request object.'))
      ->setRequired(TRUE)
      ->setDisplayOptions('form', [
        'type' => 'text_format',
        'label' => 'hidden',
        'weight' => 0,
      ]);

    $fields['response'] = BaseFieldDefinition::create('string_long')
      ->setLabel(new TranslatableMarkup('Response'))
      ->setDescription(new TranslatableMarkup('A YAML dump of the response object.'))
      ->setRequired(TRUE)
      ->setDisplayOptions('form', [
        'type' => 'text_format',
        'label' => 'hidden',
        'weight' => 1,
      ]);

    $fields['sleep_time'] = BaseFieldDefinition::create('integer')
      ->setLabel(new TranslatableMarkup('Sleep Time'))
      ->setDescription(new TranslatableMarkup('The sleep time in milliseconds for the mock provider.'))
      ->setRequired(TRUE)
      ->setDefaultValue(0)
      ->setDisplayOptions('form', [
        'type' => 'number',
        'label' => 'hidden',
        'weight' => 3,
      ]);

    $fields['operation_type'] = BaseFieldDefinition::create('string')
      ->setLabel(new TranslatableMarkup('Operation Type'))
      ->setDescription(new TranslatableMarkup('The type of operation performed by the AI provider.'))
      ->setRequired(TRUE)
      ->setDisplayOptions('form', [
        'type' => 'string_textfield',
        'label' => 'hidden',
        'weight' => 5,
      ]);

    $fields['mock_enabled'] = BaseFieldDefinition::create('boolean')
      ->setLabel(new TranslatableMarkup('Mock Enabled'))
      ->setDescription(new TranslatableMarkup('Whether the mock provider is enabled.'))
      ->setRequired(TRUE)
      ->setDefaultValue(FALSE)
      ->setDisplayOptions('form', [
        'type' => 'boolean_checkbox',
        'label' => 'hidden',
        'weight' => 10,
      ]);

    $fields['tags'] = BaseFieldDefinition::create('string')
      ->setLabel(new TranslatableMarkup('Tags'))
      ->setDescription(new TranslatableMarkup('Tags for the AI mock provider result.'))
      ->setRequired(FALSE)
      ->setCardinality(BaseFieldDefinition::CARDINALITY_UNLIMITED)
      ->setDisplayOptions('form', [
        'type' => 'string_textfield',
        'label' => 'hidden',
        'weight' => 15,
      ]);

    return $fields;
  }

}
