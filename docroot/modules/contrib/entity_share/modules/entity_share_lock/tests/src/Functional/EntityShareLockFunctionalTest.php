<?php

declare(strict_types = 1);

namespace Drupal\Tests\entity_share_lock\Functional;

use Drupal\Core\Url;
use Drupal\entity_share_lock\HookHandler\FormAlterHookHandler;
use Drupal\layout_builder\Entity\LayoutBuilderEntityViewDisplay;
use Drupal\node\NodeInterface;
use Drupal\Tests\entity_share_client\Functional\EntityShareClientFunctionalTestBase;
use Drupal\user\UserInterface;

/**
 * Test lock feature.
 *
 * @group entity_share
 * @group entity_share_lock
 */
class EntityShareLockFunctionalTest extends EntityShareClientFunctionalTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'entity_share_lock',
    'layout_builder',
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

    // Enable Layout Builder on Media.
    $layout_builder_view_display = LayoutBuilderEntityViewDisplay::load('media.es_test_remote_video.default');
    if ($layout_builder_view_display != NULL) {
      $layout_builder_view_display->enableLayoutBuilder()
        ->setOverridable()
        ->save();
    }
    // Enable Layout Builder on Node.
    $layout_builder_view_display = LayoutBuilderEntityViewDisplay::load('node.es_test.default');
    if ($layout_builder_view_display != NULL) {
      $layout_builder_view_display->enableLayoutBuilder()
        ->setOverridable()
        ->save();
    }
    // Enable Layout Builder on Taxonomy term.
    $layout_builder_view_display = LayoutBuilderEntityViewDisplay::load('taxonomy_term.es_test.default');
    if ($layout_builder_view_display != NULL) {
      $layout_builder_view_display->enableLayoutBuilder()
        ->setOverridable()
        ->save();
    }

    $this->postSetupFixture();
  }

  /**
   * {@inheritdoc}
   */
  protected function getAdministratorPermissions() {
    $permissions = parent::getAdministratorPermissions();
    $permissions[] = 'bypass node access';
    $permissions[] = 'configure any layout';
    $permissions[] = 'administer media';
    $permissions[] = 'administer taxonomy';
    return $permissions;
  }

  /**
   * {@inheritdoc}
   */
  protected function getImportConfigProcessorSettings() {
    $processors = parent::getImportConfigProcessorSettings();
    $processors['default_data_processor']['policy'] = FormAlterHookHandler::LOCKED_POLICY;
    return $processors;
  }

  /**
   * {@inheritdoc}
   */
  protected function getEntitiesDataArray() {
    return [
      'media' => [
        'en' => [
          'es_test_media' => $this->getCompleteMediaInfos([
            'field_es_test_oembed_video' => [
              'value' => 'https://www.youtube.com/watch?v=Apqd4ff0NRI',
              'checker_callback' => 'getValue',
            ],
            'bundle' => [
              'value' => 'es_test_remote_video',
              'checker_callback' => 'getTargetId',
            ],
          ]),
        ],
      ],
      'node' => [
        'en' => [
          'es_test_node' => $this->getCompleteNodeInfos([
            'status' => [
              'value' => NodeInterface::PUBLISHED,
              'checker_callback' => 'getValue',
            ],
          ]),
        ],
      ],
      'taxonomy_term' => [
        'en' => [
          'es_test_term' => $this->getCompleteTaxonomyTermInfos([
            'vid' => [
              'value' => 'es_test',
              'checker_callback' => 'getTargetId',
            ],
          ]),
        ],
      ],
    ];
  }

  /**
   * Test lock feature.
   */
  public function testLock() {
    $this->pullEveryChannels();
    $this->checkCreatedEntities();
    $this->drupalLogin($this->adminUser);

    $media = $this->loadEntity('media', 'es_test_media');
    // Edit form.
    $this->drupalGet($media->toUrl('edit-form'));
    $this->checkFormIsDisabled('field_es_test_oembed_video[0][value]');
    // Layout Builder form.
    $this->drupalGet(Url::fromRoute('layout_builder.overrides.media.view', [
      'media' => $media->id(),
    ]));
    $this->checkFormIsDisabled('toggle_content_preview');

    $node = $this->loadEntity('node', 'es_test_node');
    // Edit form.
    $this->drupalGet($node->toUrl('edit-form'));
    $this->checkFormIsDisabled('title[0][value]');
    // Layout Builder form.
    $this->drupalGet(Url::fromRoute('layout_builder.overrides.node.view', [
      'node' => $node->id(),
    ]));
    $this->checkFormIsDisabled('toggle_content_preview');

    $taxonomy_term = $this->loadEntity('taxonomy_term', 'es_test_term');
    // Edit form.
    $this->drupalGet($taxonomy_term->toUrl('edit-form'));
    $this->checkFormIsDisabled('name[0][value]');
    // Layout Builder form.
    $this->drupalGet(Url::fromRoute('layout_builder.overrides.taxonomy_term.view', [
      'taxonomy_term' => $taxonomy_term->id(),
    ]));
    $this->checkFormIsDisabled('toggle_content_preview');
  }

  /**
   * Check that a form is disabled.
   *
   * @param string $formField
   *   The form field used to check that the form is disabled.
   */
  protected function checkFormIsDisabled(string $formField): void {
    // Test that the form is disabled.
    $this->assertSession()->fieldDisabled($formField);
    // Test that a message is displayed.
    $this->assertSession()->responseContains('The entity had been locked from edition because of an import policy.');
  }

  /**
   * {@inheritdoc}
   */
  protected function createChannel(UserInterface $user) {
    parent::createChannel($user);

    // Add a channel for the media.
    $channel_storage = $this->entityTypeManager->getStorage('channel');
    $channel = $channel_storage->create([
      'id' => 'media_es_test_en',
      'label' => $this->randomString(),
      'channel_maxsize' => 50,
      'channel_entity_type' => 'media',
      'channel_bundle' => 'es_test_remote_video',
      'channel_langcode' => static::$entityLangcode,
      'access_by_permission' => FALSE,
      'authorized_roles' => [],
      'authorized_users' => [
        $user->uuid(),
      ],
    ]);
    $channel->save();
    $this->channels[$channel->id()] = $channel;

    // Add a channel for the taxonomy term.
    $channel_storage = $this->entityTypeManager->getStorage('channel');
    $channel = $channel_storage->create([
      'id' => 'taxonomy_es_test_en',
      'label' => $this->randomString(),
      'channel_maxsize' => 50,
      'channel_entity_type' => 'taxonomy_term',
      'channel_bundle' => 'es_test',
      'channel_langcode' => static::$entityLangcode,
      'access_by_permission' => FALSE,
      'authorized_roles' => [],
      'authorized_users' => [
        $user->uuid(),
      ],
    ]);
    $channel->save();
    $this->channels[$channel->id()] = $channel;
  }

}
