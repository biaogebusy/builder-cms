<?php

namespace Acquia\LightningExtension\Context;

use Drupal\DrupalExtension\Context\DrupalSubContextBase;

/**
 * Contains step definitions to assist with debugging tests.
 *
 * @internal
 *   This is an internal part of Lightning Core's testing system and may be
 *   changed or removed at any time without warning. It should not be extended,
 *   instantiated, or used in any way by external code! If you need to use this
 *   functionality, you should copy the relevant code into your own project.
 */
final class DebugContext extends DrupalSubContextBase {

  /**
   * The number of screenshots that have been taken during the scenario.
   *
   * @var int
   */
  private $screenshotCount = 0;

  /**
   * Waits a while, for debugging.
   *
   * @param int $seconds
   *   How long to wait.
   *
   * @When I wait :seconds second(s)
   */
  public function wait($seconds) {
    sleep($seconds);
  }

  /**
   * Dumps the current page HTML.
   *
   * @When I dump the HTML
   */
  public function dumpHtml() {
    print_r($this->getSession()->getPage()->getContent());
  }

  /**
   * Takes a screenshot.
   *
   * @param string $filename
   *   (optional) Ignored. The filename is based on a counter and prefixed with
   *   the name of the Mink browser.
   * @param mixed $filepath
   *   (optional) Ignored. The screenshot is saved in the directory above the
   *   Drupal root.
   *
   * @When I take a screenshot
   */
  public function saveScreenshot($filename = NULL, $filepath = NULL) {
    $filename = sprintf('%s_%d.png', $this->getMinkParameter('browser_name'), ++$this->screenshotCount);
    $filepath = \Drupal::root() . '/../';
    parent::saveScreenshot($filename, $filepath);
  }

}
