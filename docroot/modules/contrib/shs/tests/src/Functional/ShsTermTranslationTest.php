<?php

namespace Drupal\Tests\shs\Functional;

use Drupal\Core\Language\LanguageInterface;
use Drupal\dynamic_page_cache\EventSubscriber\DynamicPageCacheSubscriber;
use Drupal\language\Entity\ConfigurableLanguage;

/**
 * Tests caching of translated term data.
 *
 * @group shs
 */
class ShsTermTranslationTest extends ShsTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'node',
    'shs',
    'language',
    'content_translation',
    'dynamic_page_cache',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    ConfigurableLanguage::createFromLangcode('pl')->save();
    \Drupal::service('content_translation.manager')->setEnabled('taxonomy_term', $this->vocabulary->id(), TRUE);

    // Keep the interface language in English while the content language is
    // negotiated from the URL. This ensures that only the content language
    // cache context can distinguish the two responses below.
    $language_negotiator = \Drupal::service('language_negotiator');
    $language_negotiator->updateConfiguration([
      LanguageInterface::TYPE_INTERFACE,
      LanguageInterface::TYPE_CONTENT,
    ]);
    $language_negotiator->saveConfiguration(LanguageInterface::TYPE_INTERFACE, [
      'language-selected' => 1,
    ]);
    $language_negotiator->saveConfiguration(LanguageInterface::TYPE_CONTENT, [
      'language-url' => 1,
      'language-selected' => 2,
    ]);
  }

  /**
   * Tests that cached term data varies by the content language.
   */
  public function testCacheVariesByContentLanguage(): void {
    $term_storage = \Drupal::entityTypeManager()->getStorage('taxonomy_term');
    $term = $term_storage->load($this->termIds['aaa 1']);
    $term->set('langcode', 'en');
    $term->save();
    $term = $term_storage->loadUnchanged($term->id());
    $term->addTranslation('pl', ['name' => 'pl aaa 1']);
    $term->save();

    $field_name = 'shs-' . strtr($this->fieldName, ['_' => '-']);
    $request_url = "shs-term-data/{$field_name}/{$this->vocabulary->id()}/0";

    $english_data = $this->drupalGetJson($request_url);
    $this->assertSession()->responseHeaderEquals(DynamicPageCacheSubscriber::HEADER, 'MISS');
    $this->assertSame('aaa 1', $this->findTermName($english_data, $term->id()));

    $cached_english_data = $this->drupalGetJson($request_url);
    $this->assertSession()->responseHeaderEquals(DynamicPageCacheSubscriber::HEADER, 'HIT');
    $this->assertSame($english_data, $cached_english_data);

    $polish_data = $this->drupalGetJson("pl/{$request_url}");
    $this->assertSession()->responseHeaderEquals(DynamicPageCacheSubscriber::HEADER, 'MISS');
    $this->assertSame('pl aaa 1', $this->findTermName($polish_data, $term->id()));

    $cached_polish_data = $this->drupalGetJson("pl/{$request_url}");
    $this->assertSession()->responseHeaderEquals(DynamicPageCacheSubscriber::HEADER, 'HIT');
    $this->assertSame($polish_data, $cached_polish_data);
  }

  /**
   * Finds a term name in an SHS response.
   *
   * @param array $data
   *   The decoded SHS response.
   * @param int $term_id
   *   The term ID to find.
   *
   * @return string|null
   *   The term name, or NULL if the term was not returned.
   */
  private function findTermName(array $data, int $term_id): ?string {
    foreach ($data as $term) {
      if ((int) $term['tid'] === $term_id) {
        return $term['name'];
      }
    }

    return NULL;
  }

}
