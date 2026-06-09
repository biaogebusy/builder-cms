<?php

namespace Drupal\Tests\ai_translate\Functional;

use Drupal\ai\Entity\AiPromptInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Url;
use Drupal\language\Entity\ConfigurableLanguage;
use Drupal\Tests\BrowserTestBase;

/**
 * Tests the AI Translate settings form.
 *
 * @group ai_translate
 * @covers \Drupal\ai_translate\Form\AiTranslateSettingsForm
 */
class AiTranslateSettingsFormTest extends BrowserTestBase {

  use StringTranslationTrait;

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'claro';

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'ai_translate',
    'ai',
    'node',
    'language',
    'content_translation',
    'help',
  ];

  /**
   * An administrative user with extra permissions.
   *
   * @var \Drupal\user\UserInterface
   */
  protected $adminUser;

  /**
   * The default AI prompt.
   *
   * @var \Drupal\ai\Entity\AiPromptInterface|null
   */
  protected ?AiPromptInterface $shortPrompt = NULL;

  /**
   * The default AI prompt.
   *
   * @var \Drupal\ai\Entity\AiPromptInterface|null
   */
  protected ?AiPromptInterface $defaultPrompt = NULL;

  /**
   * The French AI prompt.
   *
   * @var \Drupal\ai\Entity\AiPromptInterface|null
   */
  protected ?AiPromptInterface $frenchPrompt = NULL;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    // Add a second language to test language-specific settings.
    ConfigurableLanguage::createFromLangcode('fr')->save();

    // Add AI prompts for testing.
    /** @var \Drupal\ai\Service\AiPromptManager $prompt_manager */
    $promptManager = \Drupal::service('ai.prompt_manager');
    // Add the short prompt.
    $this->shortPrompt = $promptManager->upsertPrompt([
      'id' => 'ai_translate__ai_translate_test_short',
      'label' => $this->t('AI Translation prompt: Too short'),
      'prompt' => '{destLangName} {inputText}',
      'type' => 'ai_translate',
    ]);

    // Add the default prompt.
    $this->defaultPrompt = $promptManager->upsertPrompt([
      'id' => 'ai_translate__ai_translate_test_default',
      'label' => $this->t('AI Translation prompt: Default'),
      'prompt' => 'This is the new default prompt for translation. It must be over 50 characters long to pass validation. Source: {sourceLangName}, Destination: {destLangName}. Text: {inputText}',
      'type' => 'ai_translate',
    ]);

    // Add the French-specific prompt.
    $this->frenchPrompt = $promptManager->upsertPrompt([
      'id' => 'ai_translate__ai_translate_test_fr',
      'label' => $this->t('AI Translation prompt: French'),
      'prompt' => 'Ceci est le prompt spécifique pour le français. Il doit également faire plus de 50 caractères. Source: {sourceLangName}, Destination: {destLangName}. Texte: {inputText}',
      'type' => 'ai_translate',
    ]);

    // Create a user with permission to manage ai translation prompts.
    $this->adminUser = $this->drupalCreateUser(['manage ai translation prompts']);
    $this->drupalLogin($this->adminUser);
  }

  /**
   * Tests the settings form functionality.
   */
  public function testSettingsForm() {
    $session = $this->assertSession();
    $page = $this->getSession()->getPage();
    $form_url = Url::fromRoute('ai_translate.settings_form');

    // 1. Visit the form and check for default values.
    $this->drupalGet($form_url);
    $session->statusCodeEquals(200);
    $session->pageTextContains('AI Translate Settings');

    $session->fieldExists('use_ai_translate');
    $session->fieldExists('prompt[table]');
    $session->fieldExists('reference_defaults[node]');
    $session->fieldExists('entity_reference_depth');
    $session->pageTextContains('Translate to English');
    $session->fieldExists('language_settings[en][prompt][table]');
    $session->pageTextContains('Translate to French');
    $session->fieldExists('language_settings[fr][prompt][table]');
    $session->checkboxChecked('use_ai_translate');

    // 2. Test form validation.
    // 2a. Test default prompt is too short.
    $this->submitForm(['prompt[table]' => 'ai_translate__ai_translate_test_short'], 'Save configuration');
    $session->pageTextContains('Prompt cannot be shorter than 50 characters');

    // 2b. Test language-specific prompt is too short.
    $edit = [
      'prompt[table]' => 'ai_translate__ai_translate_test_default',
      'language_settings[fr][prompt][table]' => 'ai_translate__ai_translate_test_short',
    ];
    $this->submitForm($edit, 'Save configuration');
    $session->pageTextContains('Prompt cannot be shorter than 50 characters');
    $this->assertNotNull($page->find('css', '#edit-language-settings-fr-prompt-table-ai-translate-ai-translate-test-fr.error'));

    // 3. Test successful form submission and config saving.
    $edit = [
      'use_ai_translate' => FALSE,
      'prompt[table]' => 'ai_translate__ai_translate_test_default',
      'language_settings[fr][prompt][table]' => 'ai_translate__ai_translate_test_fr',
      'language_settings[en][prompt][table]' => 'ai_translate__ai_translate_test_default',
      'reference_defaults[node]' => TRUE,
      'reference_defaults[user]' => FALSE,
      'entity_reference_depth' => '5',
    ];
    $this->submitForm($edit, 'Save configuration');
    $session->pageTextContains('The configuration options have been saved.');

    // 4. Verify that the configuration was saved correctly.
    $config = $this->config('ai_translate.settings');
    $this->assertFalse($config->get('use_ai_translate'), 'The "use_ai_translate" setting was saved correctly.');
    $this->assertEquals('ai_translate__ai_translate_test_default', $config->get('prompt'), 'The default prompt was saved correctly.');
    $this->assertEquals('ai_translate__ai_translate_test_fr', $config->get('language_settings.fr.prompt'), 'The French-specific prompt was saved correctly.');
    $this->assertEquals('ai_translate__ai_translate_test_default', $config->get('language_settings.en.prompt'), 'The English-specific prompt was saved correctly to the default value.');
    $this->assertEquals(['node'], $config->get('reference_defaults'), 'The entity reference defaults were saved correctly.');
    $this->assertEquals('5', $config->get('entity_reference_depth'), 'The entity reference depth was saved correctly.');
  }

}
