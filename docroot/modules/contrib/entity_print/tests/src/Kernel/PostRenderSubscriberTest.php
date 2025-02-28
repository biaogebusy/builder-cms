<?php

namespace Drupal\Tests\entity_print\Kernel;

use Drupal\entity_print\EventSubscriber\PostRenderSubscriber;
use Drupal\entity_print\PrintEngineException;
use Drupal\KernelTests\KernelTestBase;

/**
 * @coversDefaultClass \Drupal\entity_print\EventSubscriber\PostRenderSubscriber
 * @group entity_print
 */
class PostRenderSubscriberTest extends KernelTestBase {

  /**
   * Modules to enable.
   *
   * @var array
   */
  protected static $modules = ['entity_print'];

  /**
   * Test the event subscriber.
   */
  public function testEventSubscriber() {
    /** @var \Drupal\Core\Config\ConfigFactoryInterface $configFactory */
    $configFactory = $this->container->get('config.factory');
    $event = new PrintHtmlAlterTestEvent();
    $event->setPhpSapi('none-cli');
    $subscriber = new PostRenderSubscriber($configFactory, $this->container->get('request_stack'));
    $subscriber->postRender($event);

    // Only change the PHP SAPI to CLI and expect an exception.
    $event->setPhpSapi('cli');
    $this->expectException(PrintEngineException::class);
    $subscriber->postRender($event);

    // Now change the select PDF engine to phpwkhtmltopdf so we get the
    // exception.
    $config = $configFactory->getEditable('entity_print.settings');
    $data = $config->get('print_engines');
    $data['pdf_engine'] = 'phpwkhtmltopdf';
    $config->set('print_engines', $data);
    $config->save();

    // Try render again and we should get the exception.
    $this->expectException(PrintEngineException::class);
    $subscriber->postRender($event);

    // Change PHP SAPI back to none-cli and leave phpwkhtmltopdf as the
    // pdf_engine.
    $event->setPhpSapi('none-cli');
    $this->expectException(PrintEngineException::class);
    $subscriber->postRender($event);
  }

}
