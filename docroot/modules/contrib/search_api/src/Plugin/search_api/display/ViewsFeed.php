<?php

namespace Drupal\search_api\Plugin\search_api\display;

/**
 * Represents a Views feed display.
 *
 * @SearchApiDisplay(
 *   id = "views_feed",
 *   views_display_type = "feed",
 *   deriver = "Drupal\search_api\Plugin\search_api\display\ViewsDisplayDeriver"
 * )
 */
class ViewsFeed extends ViewsDisplayBase {}
