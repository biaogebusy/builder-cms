<?php

namespace Drupal\entity_print\Event;

/**
 * The events related to Print Engines.
 */
final class PrintEvents {

  /**
   * Name of the event fired when retrieving a Print engine configuration.
   *
   * This event allows you to change the configuration of a Print Engine
   * implementation right before the plugin manager creates the plugin instance.
   *
   * @Event
   *
   * @see \Symfony\Component\EventDispatcher\GenericEvent
   */
  const CONFIGURATION_ALTER = 'entity_print.print_engine.configuration_alter';

  /**
   * Name of the event fired right before the Print is sent to the page.
   *
   * At this point, the HTML has been rendered and added as a page on the Print
   * engine. The only thing left to happen is generate the filename and stream
   * the Print data to the page.
   *
   * @Event
   *
   * @see \Drupal\entity_print\Event\PreSendPrintEvent
   */
  const PRE_SEND = 'entity_print.print_engine.pre_send';

  /**
   * Name of the event fired when building CSS assets.
   *
   * This event allows custom code to add their own CSS assets. Note the
   * recommended way is to manage CSS from your theme.
   *
   * @link https://www.drupal.org/node/2430561#from-your-theme @endlink
   *
   * @code
   * $event->getBuild()['#attached']['library'][] = 'module/library';
   * @endcode
   *
   * @Event
   *
   * @see \Drupal\entity_print\Event\PrintCssAlterEvent
   */
  const CSS_ALTER = 'entity_print.print.css_alter';

  /**
   * This event is fired right after the HTML has been generated.
   *
   * Any manipulations to the HTML string can happen here. You should normally
   * avoid using this event and try and use the appropriate theme templates. We
   * currently use this event to fix a core bug with absolute URLs.
   *
   * @Event
   *
   * @see \Drupal\entity_print\Event\PrintHtmlAlterEvent
   */
  const POST_RENDER = 'entity_print.print.html_alter';

  /**
   * This event is fired after the HTML has been generated.
   *
   * The Filename is an array of strings, which will be imploded
   * and passed to the renderer.
   *
   * @Event
   *
   * @see \Drupal\entity_print\Event\FilenameAlterEvent
   */
  const FILENAME_ALTER = 'entity_print.print.filename_alter';

}
