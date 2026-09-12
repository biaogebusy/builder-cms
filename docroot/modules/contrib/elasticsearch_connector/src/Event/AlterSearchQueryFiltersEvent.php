<?php

declare(strict_types=1);

namespace Drupal\elasticsearch_connector\Event;

/**
 * Alter a search query's filters before a search query is built.
 */
class AlterSearchQueryFiltersEvent extends BaseParamsEvent {}
