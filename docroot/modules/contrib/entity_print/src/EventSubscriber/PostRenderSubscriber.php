<?php

namespace Drupal\entity_print\EventSubscriber;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\entity_print\Event\PrintEvents;
use Drupal\entity_print\Event\PrintHtmlAlterEvent;
use Masterminds\HTML5;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * The PostRenderSubscriber class.
 */
class PostRenderSubscriber implements EventSubscriberInterface {

  /**
   * The Config Factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * The request stack.
   *
   * @var \Symfony\Component\HttpFoundation\RequestStack
   */
  protected $requestStack;

  /**
   * PostRenderSubscriber constructor.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The configuration factory.
   * @param \Symfony\Component\HttpFoundation\RequestStack $request_stack
   *   The request stack.
   */
  public function __construct(ConfigFactoryInterface $config_factory, RequestStack $request_stack) {
    $this->configFactory = $config_factory;
    $this->requestStack = $request_stack;
  }

  /**
   * Alter the HTML after it has been rendered.
   *
   * @param \Drupal\entity_print\Event\PrintHtmlAlterEvent $event
   *   The event object.
   *
   *   This is a temporary workaround for a core issue.
   *
   * @see https://drupal.org/node/1494670
   */
  public function postRender(PrintHtmlAlterEvent $event) {
    // We apply the fix to PHP Wkhtmltopdf and any engine when run in CLI.
    $config = $this->configFactory->get('entity_print.settings');
    if (
      $config->get('print_engines.pdf_engine') !== 'phpwkhtmltopdf' &&
      $event->getPhpSapi() !== 'cli'
    ) {
      return;
    }

    $html_string = &$event->getHtml();
    $html5 = new HTML5();
    $document = $html5->loadHTML($html_string);
    $request_base_url = $this->requestStack->getCurrentRequest()->getSchemeAndHttpHost();
    $base_url = $config->get('base_url') ?: $request_base_url;

    // Only add a base element if there is none set in the html.
    if ($document->getElementsByTagName('base')->count() === 0) {
      $base = $document->createElement('base');
      $base->setAttribute('href', $base_url);

      // Add new base element to the head element or ...
      if ($document->getElementsByTagName('head')->count() !== 0) {
        /** @var \DOMNode $head */
        foreach ($document->getElementsByTagName('head') as $head) {
          $head->appendChild($base);
        }
      }
      // (edge-case) create a head element to add the base element to.
      else {
        $head = $document->createElement('head');
        $document->appendChild($head);
        $head->appendChild($base);
      }
    }

    // Define a function that will convert root relative uris into absolute
    // urls.
    $transform = function ($tag, $attribute) use ($document, $base_url) {
      foreach ($document->getElementsByTagName($tag) as $node) {
        $attribute_value = $node->getAttribute($attribute);

        // Handle protocol agnostic URLs as well.
        if (mb_substr($attribute_value, 0, 2) === '//') {
          $node->setAttribute($attribute, $base_url . mb_substr($attribute_value, 1));
        }
        elseif (mb_substr($attribute_value, 0, 1) === '/') {
          $node->setAttribute($attribute, $base_url . $attribute_value);
        }
      }
    };

    // Transform stylesheets, links and images.
    $transform('link', 'href');
    $transform('a', 'href');
    $transform('img', 'src');

    // Overwrite the HTML.
    $html_string = $html5->saveHTML($document);
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [PrintEvents::POST_RENDER => 'postRender'];
  }

}
