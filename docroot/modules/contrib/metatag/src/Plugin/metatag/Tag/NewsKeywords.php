<?php

namespace Drupal\metatag\Plugin\metatag\Tag;

/**
 * The basic "NewsKeywords" meta tag.
 *
 * @MetatagTag(
 *   id = "news_keywords",
 *   label = @Translation("News Keywords"),
 *   description = @Translation("A comma-separated list of keywords about the page. This meta tag is used as an indicator in <a href=':google_news'>Google News</a>.", arguments = { ":google_news" = "https://support.google.com/news/publisher/bin/answer.py?hl=en&answer=68297" }),
 *   name = "news_keywords",
 *   group = "advanced",
 *   weight = 2,
 *   type = "label",
 *   secure = FALSE,
 *   multiple = FALSE
 * )
 *
 * @deprecated in metatag:8.x-1.22 and is removed from metatag:2.0.0. No replacement is provided.
 *
 * @see https://www.drupal.org/project/metatag/issues/2973351
 */
class NewsKeywords extends MetaNameBase {
  // Nothing here yet. Just a placeholder class for a plugin.
}
