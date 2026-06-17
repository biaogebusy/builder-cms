<?php

namespace Drupal\Tests\layout_builder_at\Functional;

use Drupal\Core\Entity\Entity\EntityFormDisplay;
use Drupal\Core\Entity\Entity\EntityViewDisplay;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Url;
use Drupal\Tests\content_translation\Functional\ContentTranslationTestBase;
use Drupal\block_content\Entity\BlockContentType;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\layout_builder\Plugin\SectionStorage\OverridesSectionStorage;
use Drupal\user\UserInterface;

/**
 * Base class for Layout Builder Asymmetric Translations.
 */
abstract class LayoutBuilderAtBase extends ContentTranslationTestBase {

  const DEFAULT_TEXTFIELD_TEXT = 'The untranslated field value';
  const TRANSLATED_TEXTFIELD_TEXT = 'The translated field value';
  const DEFAULT_CONTENTBLOCK_BODY = 'Custom block content body';
  const TRANSLATED_CONTENTBLOCK_BODY = 'Translated block content body';

  /**
   * User with all permissions.
   *
   * @var \Drupal\user\UserInterface|null
   */
  protected ?UserInterface $fullAdmin = NULL;

  /**
   * The entity used for testing.
   *
   * @var \Drupal\Core\Entity\EntityInterface|null
   */
  protected ?EntityInterface $entity = NULL;

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->doSetup();
    $this->fullAdmin = $this->drupalCreateUser([], NULL, TRUE);
  }

  /**
   * {@inheritdoc}
   */
  protected function getAdministratorPermissions(): array {
    $permissions = parent::getAdministratorPermissions();
    $permissions[] = 'administer entity_test_mul display';
    $permissions[] = 'create and edit custom blocks';
    return $permissions;
  }

  /**
   * {@inheritdoc}
   */
  protected function getTranslatorPermissions(): array {
    $permissions = parent::getTranslatorPermissions();
    $permissions[] = 'view test entity translations';
    $permissions[] = 'view test entity';
    $permissions[] = 'configure any layout';
    $permissions[] = 'create and edit custom blocks';
    return $permissions;
  }

  /**
   * Set up the View Display.
   */
  protected function setUpViewDisplay(): void {
    EntityViewDisplay::create([
      'targetEntityType' => $this->entityTypeId,
      'bundle' => $this->bundle,
      'mode' => 'default',
      'status' => TRUE,
    ])->setComponent($this->fieldName, ['type' => 'string'])->save();
  }

  /**
   * Set up the Form Display.
   */
  protected function setUpFormDisplay(): void {
    EntityFormDisplay::load($this->entityTypeId . '.' . $this->bundle . '.default')
      ->setComponent(OverridesSectionStorage::FIELD_NAME, ['type' => 'layout_builder_at_copy', 'region' => 'content'])
      ->save();
  }

  /**
   * Update the Form Display.
   */
  protected function updateFormDisplay(): void {
    EntityFormDisplay::load($this->entityTypeId . '.' . $this->bundle . '.default')
      ->setComponent(
      OverridesSectionStorage::FIELD_NAME,
      [
        'type' => 'layout_builder_at_copy',
        'settings' => [
          'appearance' => 'checked_hidden',
        ],
        'region' => 'content',
      ]
    )
      ->save();
  }

  /**
   * Creates a block content type.
   *
   * @param string $label
   *   The machine name and label of the block content type.
   * @param bool $create_body
   *   Whether to create a body field for the block.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   *   Thrown when the entity storage operation fails.
   */
  protected function setupBlockType(string $label, bool $create_body = TRUE): void {
    $bundle = BlockContentType::create([
      'id' => $label,
      'label' => $label,
      'revision' => FALSE,
    ]);
    $bundle->save();
    if ($create_body) {
      // Create the body field storage if it doesn't exist yet.
      if (!FieldStorageConfig::loadByName('block_content', 'body')) {
        FieldStorageConfig::create([
          'field_name' => 'body',
          'entity_type' => 'block_content',
          'type' => 'text_with_summary',
        ])->save();
      }

      FieldConfig::create([
        'field_storage' => FieldStorageConfig::loadByName('block_content', 'body'),
        'bundle' => $bundle->id(),
        'label' => 'Body',
        'settings' => [
          'display_summary' => FALSE,
          'allowed_formats' => [],
        ],
      ])->save();

      /** @var \Drupal\Core\Entity\EntityDisplayRepositoryInterface $display_repository */
      $display_repository = \Drupal::service('entity_display.repository');

      $display_repository->getFormDisplay('block_content', $bundle->id())
        ->setComponent('body', [
          'type' => 'text_textarea_with_summary',
        ])
        ->save();

      $display_repository->getViewDisplay('block_content', $bundle->id())
        ->setComponent('body', [
          'label' => 'hidden',
          'type' => 'text_default',
        ])
        ->save();
    }
  }

  /**
   * Setup translated entity with layouts.
   */
  protected function setUpEntities(): void {
    $this->drupalLogin($this->administrator);

    $field_ui_prefix = 'entity_test_mul/structure/entity_test_mul';
    // Allow overrides for the layout.
    $this->drupalGet("$field_ui_prefix/display/default");
    $this->submitForm(['layout[enabled]' => TRUE], 'Save');

    $this->drupalGet("$field_ui_prefix/display/default");
    $this->submitForm(['layout[allow_custom]' => TRUE], 'Save');

    // @todo The Layout Builder UI relies on local tasks; fix in
    //   https://www.drupal.org/project/drupal/issues/2917777.
    $this->drupalPlaceBlock('local_tasks_block');
  }

  /**
   * Create entity.
   */
  protected function createDefaultTranslationEntity(): void {
    // Create a test entity.
    $id = $this->createEntity([
      'name' => 'Label',
      $this->fieldName => [['value' => self::DEFAULT_TEXTFIELD_TEXT]],
    ], $this->langcodes[0]);
    $storage = $this->container->get('entity_type.manager')
      ->getStorage($this->entityTypeId);
    $storage->resetCache([$id]);
    $this->entity = $storage->load($id);
  }

  /**
   * Adds a layout override to the entity.
   *
   * @param bool $add_text_block
   *   Whether to add a text block to the layout.
   * @param string $custom_block_content_body
   *   The content to be added to the custom block.
   *
   * @throws \Behat\Mink\Exception\ElementNotFoundException
   *   Thrown when an expected element is not found on the page.
   * @throws \Behat\Mink\Exception\ExpectationException
   *   Thrown when an expectation is not met.
   * @throws \Behat\Mink\Exception\ResponseTextException
   *   Thrown when a specific response text is not found.
   * @throws \Drupal\Core\Entity\EntityMalformedException
   *   Thrown when the entity is malformed.
   */
  protected function addLayoutOverride(bool $add_text_block = FALSE, string $custom_block_content_body = self::DEFAULT_CONTENTBLOCK_BODY): void {
    $assert_session = $this->assertSession();
    $page = $this->getSession()->getPage();
    $entity_url = $this->entity->toUrl()->toString();
    $layout_url = $entity_url . '/layout';
    $this->drupalGet($layout_url);
    $assert_session->pageTextNotContains(self::TRANSLATED_TEXTFIELD_TEXT);
    $assert_session->pageTextContains(self::DEFAULT_TEXTFIELD_TEXT);

    // Adjust the layout.
    $this->click('.layout-builder__add-block .layout-builder__link');
    $assert_session->linkExists('Powered by Drupal');
    $this->clickLink('Powered by Drupal');
    $button = $assert_session->elementExists('css', '#layout-builder-add-block .button--primary');
    $button->press();

    $assert_session->pageTextContains('Powered by Drupal');

    if ($add_text_block) {
      $this->click('.layout-builder__add-block .layout-builder__link');
      $this->clickLink('Create content block');
      $edit = [
        'settings[label]' => 'Label',
        'settings[label_display]' => FALSE,
        'settings[block_form][body][0][value]' => $custom_block_content_body,
      ];
      $this->submitForm($edit, $button->getValue());
    }

    $assert_session->buttonExists('Save layout');
    $page->pressButton('Save layout');
  }

  /**
   * Updates the layout override for a given page.
   *
   * @param string $url
   *   The base URL of the page whose layout needs to be updated.
   * @param bool $update_text_block
   *   Whether to update the text block content.
   * @param string $custom_block_content_body
   *   The new content for the custom block.
   *
   * @throws \Behat\Mink\Exception\ExpectationException
   *   If the expected elements are not found.
   */
  protected function updateLayoutOverride(string $url, bool $update_text_block = FALSE, string $custom_block_content_body = self::TRANSLATED_CONTENTBLOCK_BODY): void {
    $user = $this->loggedInUser;
    $this->drupalLogin($this->fullAdmin);
    $assert_session = $this->assertSession();
    $layout_url = $url . '/layout';

    $this->drupalGet($layout_url);
    $assert_session->statusCodeEquals(200);
    $page = $this->getSession()->getPage();

    if ($update_text_block) {
      $id = $assert_session->elementExists('css', '.layout-builder__region > div:nth-child(4) > div');

      $groups = _contextual_id_to_links($id->getAttribute('data-contextual-id'));
      $contextual_links_manager = \Drupal::service('plugin.manager.menu.contextual_link');

      $items = [];
      foreach ($groups as $group => $args) {
        $args += [
          'route_parameters' => [],
          'metadata' => [],
        ];
        $items += $contextual_links_manager->getContextualLinksArrayByGroup($group, $args['route_parameters'], $args['metadata']);
      }

      $item = $items['layout_builder_block_update'];
      $item['localized_options']['language'] = \Drupal::languageManager()->getLanguage($item['metadata']['langcode']);
      $update_url = Url::fromRoute($item['route_name'] ?? '', $item['route_parameters'] ?? [], $item['localized_options'])->toString();
      $this->drupalGet($update_url);
      $button = $assert_session->elementExists('css', '.button--primary');
      $edit = [
        'settings[block_form][body][0][value]' => $custom_block_content_body,
      ];
      $this->submitForm($edit, $button->getValue());
    }

    $page->pressButton('Save layout');

    $this->drupalLogin($user);
  }

  /**
   * Adds an entity translation.
   *
   * @param bool|null $copy
   *   Whether to copy the blocks or not.
   * @param int $target
   *   The target of the langcode.
   * @param string|null $source_language
   *   Which source language to use, if applicable.
   */
  protected function addEntityTranslation(?bool $copy = FALSE, int $target = 2, ?string $source_language = NULL): void {
    $assert_session = $this->assertSession();
    $user = $this->loggedInUser;
    $this->drupalLogin($this->translator);
    $add_translation_url = Url::fromRoute("entity.$this->entityTypeId.content_translation_add", [
      $this->entityTypeId => $this->entity->id(),
      'source' => $this->langcodes[0],
      'target' => $this->langcodes[$target],
    ]);

    $this->drupalGet($add_translation_url);

    if ($source_language) {
      $this->submitForm(['source_langcode[source]' => $source_language], 'Change');
    }

    $edit = ["{$this->fieldName}[0][value]" => 'The translated field value'];
    if ($copy) {
      $assert_session->pageTextContains('Copy blocks into translation');
      $edit['layout_builder__layout[value]'] = TRUE;
    }
    elseif (isset($copy)) {
      $assert_session->pageTextNotContains('Copy blocks into translation');
    }

    $this->submitForm($edit, 'Save');
    $this->drupalLogin($user);
  }

  /**
   * Updates a node.
   *
   * @param string $langcode
   *   The language code for the update.
   */
  protected function updateNode(string $langcode): void {
    $user = $this->loggedInUser;
    $this->drupalLogin($this->fullAdmin);
    $update_url = Url::fromRoute("entity.$this->entityTypeId.edit_form", [
      $this->entityTypeId => $this->entity->id(),
    ], ['language' => \Drupal::languageManager()->getLanguage($langcode)]);

    $edit = [];
    $this->drupalGet($update_url);
    $this->submitForm($edit, 'Save');
    $this->drupalLogin($user);
  }

}
