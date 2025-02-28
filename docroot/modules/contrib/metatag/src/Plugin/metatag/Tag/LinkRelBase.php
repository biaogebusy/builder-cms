<?php

namespace Drupal\metatag\Plugin\metatag\Tag;

/**
 * This base plugin allows "link rel" tags to be further customized.
 */
abstract class LinkRelBase extends MetaNameBase {

  /**
   * The string this tag uses for the tag itself.
   *
   * @var string
   */
  protected $htmlTag = 'link';

  /**
   * The attribute this tag uses for the name.
   *
   * @var string
   */
  protected $htmlNameAttribute = 'rel';

  /**
   * The attribute this tag uses for the contents.
   *
   * @var string
   */
  protected $htmlValueAttribute = 'href';

}
