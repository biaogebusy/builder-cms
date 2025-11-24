<?php

namespace Drupal\elasticsearch_connector\Plugin\search_api\processor;

use Drupal\Core\Language\LanguageInterface;

/**
 * Build highlight queries from the ElasticsearchHighlighter processor config.
 */
trait HighlightQueryBuilderTrait {

  /**
   * Format a field option for ElasticSearch highlighting JSON.
   *
   * @param string[] $fieldList
   *   An array of field ID strings.
   *
   * @return array<string,object>
   *   An array where the keys are field ID strings, and the values are empty
   *   objects.
   */
  protected function buildHighlightQueryFieldOption(array $fieldList): array {
    $answer = [];

    foreach ($fieldList as $fieldMachineName) {
      if (\is_int($fieldMachineName) || \is_string($fieldMachineName)) {
        $answer[$fieldMachineName] = new \stdClass();
      }
    }

    return $answer;
  }

  /**
   * Build an ElasticSearch 'highlight' query fragment.
   *
   * Note, this function does not currently support the 'fvh' highlighter,
   * because it requires 'term_vector' fields, and it is not currently possible
   * to map a field to the 'term_vector' type.
   *
   * Because we don't support the 'fvh' highlighter, this means that we also
   * don't support sending the 'chars' boundary_scanner, and therefore, we don't
   * support the 'boundary_chars', 'boundary_max_scan', 'fragment_offset', or
   * 'phrase_limit' options either.
   *
   * This function also does not support:
   * - the 'tags_schema' option, as it limits our pre- and post-tag options;
   * - the 'highlight_query' option, because there's no good universal default
   *   value, so it would require a second search form field;
   * - the 'max_analyzed_offset' option, because we don't anticipate it needing
   *   to be changed, it interacts in a non-trivial way with a similar setting
   *   in the index configuration (which we don't currently expose), and we can
   *   only control it on a per-index basis anyway, so there's no point in
   *   exposing it here;
   *
   * @return array
   *   An array intended to be JSON-encoded and included in an ElasticSearch
   *   query.
   */
  protected function buildHighlightQueryFragment(): array {
    $output = [];
    $config = $this->getConfiguration();

    $output['type'] = $config['type'];
    $output['encoder'] = $config['encoder'];
    $output['fragment_size'] = $config['fragment_size'];
    $output['no_match_size'] = $config['no_match_size'];
    $output['number_of_fragments'] = $config['number_of_fragments'];
    $output['order'] = $config['order'];
    $output['require_field_match'] = (bool) $config['require_field_match'];

    // The fields to search for highlights in. Note that we do NOT set the
    // 'matched_fields' option, because 'matched_fields' only affects 'unified'
    // and 'fvh' highlighters; we don't support 'fvh' at this time; and we would
    // have to explain the complicated rules for the 'unified' highlighter. We
    // also don't support wildcards in the field names.
    $output['fields'] = $this->buildHighlightQueryFieldOption($config['fields']);

    // Pre- and post-tags surround matches in the highlighted snippets.
    // ElasticSearch 8.15 accepts an array of strings, but by observation, only
    // uses the first string, so we only accept one value as well. This allows
    // us to derive the post-tag from the pre-tag.
    $preTag = $config['pre_tag'];
    $postTag = $this->getClosingTagFromOpeningTag($preTag);
    $output['pre_tags'] = [$preTag];
    $output['post_tags'] = [$postTag];

    // The boundary_scanner option is only valid for the 'unified' or 'fvh'
    // types (but note that we don't support 'fvh' yet).
    if ($config['type'] === 'unified') {
      $output['boundary_scanner'] = $config['boundary_scanner'];

      // The boundary_scanner_locale option is only valid for the 'sentence'
      // boundary_scanner.
      if ($config['boundary_scanner'] === 'sentence') {
        $output['boundary_scanner_locale'] = $this->buildHighlightQueryBoundaryScannerLocale($config['boundary_scanner_locale']);
      }
    }

    // The 'fragmenter' option is only valid for the 'plain' highlighter.
    if ($config['type'] === 'plain') {
      $output['fragmenter'] = $config['fragmenter'];
    }

    return $output;
  }

  /**
   * Interpret the boundary scanner locale config in a way we can send in query.
   *
   * @param string $configuredLocale
   *   The locale stored in configuration.
   *
   * @return string
   *   The locale to send in the highlight query. Usually this is the value
   *   stored in $configuredLocale, unless $configuredLocale is set to the
   *   the value of \Drupal\Core\Language\LanguageInterface::LANGCODE_SYSTEM
   *   (i.e.: the string 'system'): then it gets the current locale from
   *   Drupal's language manager.
   *
   * @see self::getBoundaryScannerLocaleOptions()
   */
  protected function buildHighlightQueryBoundaryScannerLocale(string $configuredLocale): string {
    if ($configuredLocale === LanguageInterface::LANGCODE_SYSTEM) {
      return \Drupal::languageManager()->getCurrentLanguage()->getId();
    }

    return $configuredLocale;
  }

  /**
   * Convert string with at least one HTML opening tag to the name of that tag.
   *
   * @param string $htmlString
   *   The string to interpret, containing at least one HTML opening tag.
   *
   * @return string
   *   The name of the first HTML tag found in $htmlString. For example, if
   *   $htmlString is '<strong class="placeholder">' then this will return
   *   'strong'.
   */
  protected function convertHtmlTagToTagName(string $htmlString): string {
    // This regular expression is from
    // \Drupal\filter\Plugin\Filter\FilterHtml::tips().
    preg_match('/<([a-z0-9]+)[^a-z0-9]/i', $htmlString, $out);
    return $out[1];
  }

  /**
   * Get a list of locale options for the boundary scanner.
   *
   * @return array
   *   An associative array of locale options, suitable for use in a Form API
   *   select/radios #options array; where the keys are language codes and the
   *   values are labels for that language. This function also adds an option
   *   for \Drupal\Core\Language\LanguageInterface::LANGCODE_SYSTEM with the
   *   title '- Interface language -'.
   *
   * @see self::buildHighlightQueryBoundaryScannerLocale()
   */
  protected function getBoundaryScannerLocaleOptions(): array {
    $answer = [];

    $answer[LanguageInterface::LANGCODE_SYSTEM] = $this->t('- Interface language -');

    $language_list = \Drupal::languageManager()->getLanguages();
    foreach ($language_list as $langcode => $language) {
      // Make locked languages appear special in the list.
      $answer[$langcode] = $language->isLocked() ? $this->t('- @name -', ['@name' => $language->getName()]) : $language->getName();
    }

    return $answer;
  }

  /**
   * Construct an HTML closing tag string, given an HTML opening tag.
   *
   * @param string $htmlString
   *   The string to interpret, containing at least one HTML opening tag.
   *
   * @return string
   *   A closing tag for the first HTML tag found in $htmlString. For example,
   *   if $htmlString is '<em class="placeholder">', then this will return
   *   '</em>'.
   */
  protected function getClosingTagFromOpeningTag(string $htmlString): string {
    $tagName = $this->convertHtmlTagToTagName($htmlString);
    return \sprintf('</%s>', $tagName);
  }

}
