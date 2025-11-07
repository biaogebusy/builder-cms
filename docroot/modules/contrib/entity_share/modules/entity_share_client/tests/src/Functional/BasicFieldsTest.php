<?php

declare(strict_types = 1);

namespace Drupal\Tests\entity_share_client\Functional;

use Drupal\Core\Language\LanguageInterface;
use Drupal\datetime\Plugin\Field\FieldType\DateTimeItemInterface;
use Drupal\entity_share_test\FakeDataGenerator;
use Drupal\node\NodeInterface;
use Drupal\user\UserInterface;

/**
 * General functional test class.
 *
 * @group entity_share
 * @group entity_share_client
 */
class BasicFieldsTest extends EntityShareClientFunctionalTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'entity_share_entity_test',
    'jsonapi_extras',
  ];

  /**
   * {@inheritdoc}
   */
  protected static $entityTypeId = 'node';

  /**
   * {@inheritdoc}
   */
  protected static $entityBundleId = 'es_test';

  /**
   * {@inheritdoc}
   */
  protected static $entityLangcode = 'en';

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->entityTypeManager->getStorage('jsonapi_resource_config')->create([
      'id' => 'node--es_test',
      'disabled' => FALSE,
      'path' => 'node/es_test',
      'resourceType' => 'node--es_test',
      'resourceFields' => [
        'title' => [
          'fieldName' => 'title',
          'publicName' => $this->randomMachineName(),
          'enhancer' => [
            'id' => '',
          ],
          'disabled' => FALSE,
        ],
        'langcode' => [
          'fieldName' => 'langcode',
          'publicName' => $this->randomMachineName(),
          'enhancer' => [
            'id' => '',
          ],
          'disabled' => FALSE,
        ],
      ],
    ])->save();

    $this->postSetupFixture();
  }

  /**
   * {@inheritdoc}
   */
  protected function getChannelUserPermissions() {
    $permissions = parent::getChannelUserPermissions();
    $permissions[] = 'view test entity';
    return $permissions;
  }

  /**
   * {@inheritdoc}
   */
  protected function getEntitiesDataArray() {
    /** @var \Drupal\Core\Datetime\DateFormatterInterface $date_formatter */
    $date_formatter = $this->container->get('date.formatter');

    return [
      'node' => [
        'en' => [
          // Default.
          'es_test' => $this->getCompleteNodeInfos([
            'status' => [
              'value' => NodeInterface::PUBLISHED,
              'checker_callback' => 'getValue',
            ],
          ]),
          // Boolean.
          'es_test_boolean_off' => $this->getCompleteNodeInfos([
            'field_es_test_boolean' => [
              'value' => 0,
              'checker_callback' => 'getValue',
            ],
          ]),
          'es_test_boolean_on' => $this->getCompleteNodeInfos([
            'field_es_test_boolean' => [
              'value' => 1,
              'checker_callback' => 'getValue',
            ],
          ]),
          // Date: date only.
          'es_test_date_only' => $this->getCompleteNodeInfos([
            'field_es_test_date_only' => [
              'value' => $date_formatter->format(FakeDataGenerator::dateTimeBetween()->getTimestamp(), 'custom', DateTimeItemInterface::DATE_STORAGE_FORMAT),
              'checker_callback' => 'getValue',
            ],
          ]),
          // Date: date and time.
          'es_test_date_and_time' => $this->getCompleteNodeInfos([
            'field_es_test_date' => [
              'value' => $date_formatter->format(FakeDataGenerator::dateTimeBetween()->getTimestamp(), 'custom', DateTimeItemInterface::DATETIME_STORAGE_FORMAT),
              'checker_callback' => 'getValue',
            ],
          ]),
          // Date range: date only.
          'es_test_date_range_date_only' => $this->getCompleteNodeInfos([
            'field_es_test_date_only_range' => [
              'value' => [
                [
                  'value' => $date_formatter->format(FakeDataGenerator::dateTimeBetween()->getTimestamp(), 'custom', DateTimeItemInterface::DATE_STORAGE_FORMAT),
                  'end_value' => $date_formatter->format(FakeDataGenerator::dateTimeBetween('now', '+30 years')->getTimestamp(), 'custom', DateTimeItemInterface::DATE_STORAGE_FORMAT),
                ],
              ],
              'checker_callback' => 'getValues',
            ],
          ]),
          // Date range: date all day.
          'es_test_date_range_all_day' => $this->getCompleteNodeInfos([
            'field_es_test_date_all_day_range' => [
              'value' => [
                [
                  'value' => $date_formatter->format(FakeDataGenerator::dateTimeBetween()->getTimestamp(), 'custom', DateTimeItemInterface::DATETIME_STORAGE_FORMAT),
                  'end_value' => $date_formatter->format(FakeDataGenerator::dateTimeBetween('now', '+30 years')->getTimestamp(), 'custom', DateTimeItemInterface::DATETIME_STORAGE_FORMAT),
                ],
              ],
              'checker_callback' => 'getValues',
            ],
          ]),
          // Date range: date and time.
          'es_test_date_range_date_and_time' => $this->getCompleteNodeInfos([
            'field_es_test_date_range' => [
              'value' => [
                [
                  'value' => $date_formatter->format(FakeDataGenerator::dateTimeBetween()->getTimestamp(), 'custom', DateTimeItemInterface::DATETIME_STORAGE_FORMAT),
                  'end_value' => $date_formatter->format(FakeDataGenerator::dateTimeBetween('now', '+30 years')->getTimestamp(), 'custom', DateTimeItemInterface::DATETIME_STORAGE_FORMAT),
                ],
              ],
              'checker_callback' => 'getValues',
            ],
          ]),
          // Email.
          'es_test_email' => $this->getCompleteNodeInfos([
            'field_es_test_email' => [
              'value' => $this->randomMachineName() . '@example.com',
              'checker_callback' => 'getValue',
            ],
          ]),
          // List: float.
          'es_test_list_float' => $this->getCompleteNodeInfos([
            'field_es_test_list_float' => [
              'value' => FakeDataGenerator::randomElement([
                1,
                1.5,
                2,
                2.5,
                3,
              ]),
              'checker_callback' => 'getValue',
            ],
          ]),
          // List: integer.
          'es_test_list_integer' => $this->getCompleteNodeInfos([
            'field_es_test_list_integer' => [
              'value' => FakeDataGenerator::randomElement([
                1,
                2,
                3,
              ]),
              'checker_callback' => 'getValue',
            ],
          ]),
          // List: text.
          'es_test_list_text' => $this->getCompleteNodeInfos([
            'field_es_test_list_text' => [
              'value' => FakeDataGenerator::randomElement([
                'choice_1',
                'choice_2',
                'choice_3',
              ]),
              'checker_callback' => 'getValue',
            ],
          ]),
          // Number: decimal.
          'es_test_number_decimal' => $this->getCompleteNodeInfos([
            'field_es_test_number_decimal' => [
              'value' => FakeDataGenerator::randomFloat(2, 0, 99999999),
              'checker_callback' => 'getValue',
            ],
          ]),
          // Number: float.
          'es_test_number_float' => $this->getCompleteNodeInfos([
            'field_es_test_number_float' => [
              // Use integer value because of random failure on precision with
              // float.
              'value' => FakeDataGenerator::randomNumber(5),
              'checker_callback' => 'getValue',
            ],
          ]),
          // Number: integer.
          'es_test_number_integer' => $this->getCompleteNodeInfos([
            'field_es_test_number_integer' => [
              'value' => FakeDataGenerator::randomNumber(),
              'checker_callback' => 'getValue',
            ],
          ]),
          // Telephone.
          'es_test_telephone_phone_number' => $this->getCompleteNodeInfos([
            'field_es_test_telephone' => [
              'value' => '+33 1 23 45 67 89',
              'checker_callback' => 'getValue',
            ],
          ]),
          'es_test_telephone_mobile_number' => $this->getCompleteNodeInfos([
            'field_es_test_telephone' => [
              'value' => '+33 (0)6 12 34 56 78',
              'checker_callback' => 'getValue',
            ],
          ]),
          'es_test_telephone_service_number' => $this->getCompleteNodeInfos([
            'field_es_test_telephone' => [
              'value' => '0812345678',
              'checker_callback' => 'getValue',
            ],
          ]),
          // Text: plain.
          'es_test_text_plain' => $this->getCompleteNodeInfos([
            'field_es_test_text_plain' => [
              'value' => FakeDataGenerator::text(255),
              'checker_callback' => 'getValue',
            ],
          ]),
          // Text: plain, long.
          'es_test_text_plain_long' => $this->getCompleteNodeInfos([
            'field_es_test_text_plain_long' => [
              'value' => FakeDataGenerator::text(1000),
              'checker_callback' => 'getValue',
            ],
          ]),
          // Text: formatted.
          'es_test_text_formatted' => $this->getCompleteNodeInfos([
            'field_es_test_text_formatted' => [
              'value' => [
                [
                  'value' => FakeDataGenerator::text(255),
                  'format' => 'restricted_html',
                ],
              ],
              'checker_callback' => 'getValues',
            ],
          ]),
          // Text: formatted, long.
          'es_test_text_formatted_long' => $this->getCompleteNodeInfos([
            'field_es_test_text_formatted_lon' => [
              'value' => [
                [
                  'value' => FakeDataGenerator::text(1000),
                  'format' => 'basic_html',
                ],
              ],
              'checker_callback' => 'getValues',
            ],
          ]),
          // Text: formatted, long, with summary.
          'es_test_text_formatted_long_summary' => $this->getCompleteNodeInfos([
            'field_es_test_body' => [
              'value' => [
                [
                  'value' => FakeDataGenerator::text(1000),
                  'summary' => FakeDataGenerator::text(1000),
                  'format' => 'full_html',
                ],
              ],
              'checker_callback' => 'getValues',
            ],
          ]),
          // Timestamp.
          'es_test_timestamp' => $this->getCompleteNodeInfos([
            'field_es_test_timestamp' => [
              'value' => FakeDataGenerator::unixTime(),
              'checker_callback' => 'getValue',
            ],
          ]),
        ],
      ],
      // Untranslatable entity.
      'entity_test_not_translatable' => [
        LanguageInterface::LANGCODE_NOT_SPECIFIED => [
          'entity_test_not_translatable' => [
            'name' => [
              'value' => $this->randomString(),
              'checker_callback' => 'getValue',
            ],
          ],
        ],
      ],
      // Untranslatable entity with empty langcode.
      'entity_test_not_translatable_el' => [
        LanguageInterface::LANGCODE_NOT_SPECIFIED => [
          'entity_test_not_translatable_el' => [
            'name' => [
              'value' => $this->randomString(),
              'checker_callback' => 'getValue',
            ],
          ],
        ],
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  protected function createChannel(UserInterface $user) {
    parent::createChannel($user);

    $channel_storage = $this->entityTypeManager->getStorage('channel');

    // Add a channel for untranslatable entities.
    $channel = $channel_storage->create([
      'id' => 'entity_test_not_translatable_entity_test_not_translatable_' . LanguageInterface::LANGCODE_NOT_SPECIFIED,
      'label' => $this->randomString(),
      'channel_maxsize' => 50,
      'channel_entity_type' => 'entity_test_not_translatable',
      'channel_bundle' => 'entity_test_not_translatable',
      'channel_langcode' => LanguageInterface::LANGCODE_NOT_SPECIFIED,
      'access_by_permission' => FALSE,
      'authorized_roles' => [],
      'authorized_users' => [
        $user->uuid(),
      ],
    ]);
    $channel->save();
    $this->channels[$channel->id()] = $channel;

    // Add a channel for untranslatable entities with empty langcode.
    $channel = $channel_storage->create([
      'id' => 'entity_test_not_translatable_el_entity_test_not_translatable_el_' . LanguageInterface::LANGCODE_NOT_SPECIFIED,
      'label' => $this->randomString(),
      'channel_maxsize' => 50,
      'channel_entity_type' => 'entity_test_not_translatable_el',
      'channel_bundle' => 'entity_test_not_translatable_el',
      'channel_langcode' => LanguageInterface::LANGCODE_NOT_SPECIFIED,
      'access_by_permission' => FALSE,
      'authorized_roles' => [],
      'authorized_users' => [
        $user->uuid(),
      ],
    ]);
    $channel->save();
    $this->channels[$channel->id()] = $channel;
  }

  /**
   * Test basic pull feature.
   */
  public function testBasicPull() {
    $this->pullEveryChannels();
    $this->checkCreatedEntities();
  }

}
