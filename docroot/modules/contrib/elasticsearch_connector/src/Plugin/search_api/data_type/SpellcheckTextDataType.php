<?php

namespace Drupal\elasticsearch_connector\Plugin\search_api\data_type;

use Drupal\search_api\Plugin\search_api\data_type\TextDataType;

/**
 * Provides data type to feed the suggester component.
 *
 * @SearchApiDataType(
 *   id = "elasticsearch_connector_text_spellcheck",
 *   label = @Translation("Elasticsearch Spellcheck"),
 *   description = @Translation("Full text field to feed the spellcheck component."),
 *   fallback_type = "text"
 * )
 */
class SpellcheckTextDataType extends TextDataType {}
