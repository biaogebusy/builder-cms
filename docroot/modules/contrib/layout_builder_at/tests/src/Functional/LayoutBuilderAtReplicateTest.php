<?php

namespace Drupal\Tests\layout_builder_at\Functional;

use Drupal\Core\Url;

/**
 * Layout Builder Asymmetric Translations tests.
 *
 * @group layout_builder_at
 */
class LayoutBuilderAtReplicateTest extends LayoutBuilderAtBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'block',
    'block_content',
    'content_translation',
    'contextual',
    'entity_test',
    'field_ui',
    'layout_builder',
    'layout_builder_at',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->setUpViewDisplay();
    $this->setUpFormDisplay();
    $this->setUpEntities();
    $this->setupBlockType('Text');
  }

  /**
   * Tests that layout overrides have copied blocks.
   *
   * @throws \Behat\Mink\Exception\ElementNotFoundException
   * @throws \Behat\Mink\Exception\ExpectationException
   * @throws \Behat\Mink\Exception\ResponseTextException
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   * @throws \Drupal\Core\Entity\EntityMalformedException
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function testAsymmetricTranslationWithReplicate() {
    $assert_session = $this->assertSession();

    $this->drupalGet('entity_test_mul/structure/entity_test_mul/display/default/layout');

    // Make layout builder field translatable.
    $this->drupalGet('admin/config/regional/content-language');
    $edit = [
      'settings[entity_test_mul][entity_test_mul][fields][layout_builder__layout]' => TRUE,
    ];
    $assert_session->pageTextNotContains('Layout Builder does not support translating layouts.');
    $this->submitForm($edit, 'Save configuration');

    // Create default entity.
    $user = $this->loggedInUser;
    $this->drupalLogin($this->fullAdmin);
    $url = Url::fromRoute("entity.$this->entityTypeId.add_form", ['type' => 'entity_test_mul'])->toString();
    $this->drupalGet($url);
    $assert_session->pageTextNotContains('Copy blocks into translation');
    $this->drupalLogin($user);
    $this->createDefaultTranslationEntity();

    // Add layout override.
    $this->addLayoutOverride(TRUE);

    // Now translate.
    $this->addEntityTranslation(TRUE);

    $entity_url = $this->entity->toUrl()->toString();
    $language = \Drupal::languageManager()->getLanguage($this->langcodes[2]);
    $translated_entity_url = $this->entity->toUrl('canonical', ['language' => $language])->toString();

    $this->drupalGet($entity_url);
    $assert_session->pageTextNotContains(self::TRANSLATED_TEXTFIELD_TEXT);
    $assert_session->pageTextContains(self::DEFAULT_TEXTFIELD_TEXT);
    $assert_session->pageTextContains(self::DEFAULT_CONTENTBLOCK_BODY);
    $assert_session->linkExists('Layout');
    $assert_session->pageTextContains('Powered by Drupal');

    $this->drupalGet($translated_entity_url);
    $assert_session->pageTextNotContains(self::DEFAULT_TEXTFIELD_TEXT);

    // Translated field is visible as the field block is copied over from the
    // default.
    $assert_session->pageTextContains(self::TRANSLATED_TEXTFIELD_TEXT);

    // Layout should be accessible.
    $assert_session->linkExists('Layout');

    // Powered by Drupal block is copied over.
    $assert_session->pageTextContains('Powered by Drupal');

    // Block content is copied over.
    $assert_session->pageTextContains(self::DEFAULT_CONTENTBLOCK_BODY);

    // Translate the block content.
    $this->updateLayoutOverride($translated_entity_url, TRUE);

    $total = \Drupal::entityTypeManager()->getStorage('block_content')->loadMultiple();
    $this->assertEquals(count($total), 2);

    // Compare now.
    $this->drupalGet($entity_url);
    $assert_session->pageTextContains(self::DEFAULT_CONTENTBLOCK_BODY);

    $this->drupalGet($translated_entity_url);
    $assert_session->pageTextContains(self::TRANSLATED_CONTENTBLOCK_BODY);

    // Update original. Make sure the blocks remain.
    $this->updateNode($this->langcodes[1]);
    $this->drupalGet($entity_url);
    $assert_session->pageTextContains(self::DEFAULT_CONTENTBLOCK_BODY);
    $assert_session->pageTextContains('Powered by Drupal');

    // Update translation. Make sure the blocks remain.
    $this->updateNode($this->langcodes[2]);
    $this->drupalGet($translated_entity_url);
    $assert_session->pageTextContains(self::TRANSLATED_CONTENTBLOCK_BODY);
    $assert_session->pageTextContains('Powered by Drupal');

    // Create a new entity, set the copy widget to checked and hidden.
    $this->updateFormDisplay();
    $this->entity->delete();
    $this->createDefaultTranslationEntity();

    // Add layout override.
    $this->addLayoutOverride(TRUE);

    // Now translate.
    $this->addEntityTranslation();

    $entity_url = $this->entity->toUrl()->toString();
    $language = \Drupal::languageManager()->getLanguage($this->langcodes[2]);
    $translated_entity_url = $this->entity->toUrl('canonical', ['language' => $language])->toString();

    $this->drupalGet($entity_url);
    $assert_session->pageTextNotContains(self::TRANSLATED_TEXTFIELD_TEXT);
    $assert_session->pageTextContains(self::DEFAULT_TEXTFIELD_TEXT);
    $assert_session->pageTextContains(self::DEFAULT_CONTENTBLOCK_BODY);
    $assert_session->linkExists('Layout');
    $assert_session->pageTextContains('Powered by Drupal');

    $this->drupalGet($translated_entity_url);
    $assert_session->pageTextNotContains(self::DEFAULT_TEXTFIELD_TEXT);

    // Translate the block content.
    $this->updateLayoutOverride($translated_entity_url, TRUE);

    $total = \Drupal::entityTypeManager()->getStorage('block_content')->loadMultiple();
    $this->assertEquals(count($total), 4);

    // Compare now.
    $this->drupalGet($entity_url);
    $assert_session->pageTextContains(self::DEFAULT_CONTENTBLOCK_BODY);

    $this->drupalGet($translated_entity_url);
    $assert_session->pageTextContains(self::TRANSLATED_CONTENTBLOCK_BODY);
  }

}
