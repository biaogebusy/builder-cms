<?php

namespace Drupal\Tests\page_manager\Traits;

/**
 * Waits for the browser to settle after submitting a form.
 *
 * \Drupal\FunctionalJavascriptTests\WebDriverTestBase overrides ::drupalGet()
 * to wait for outstanding requests, but it does not override ::submitForm().
 * \Drupal\Tests\UiHelperTrait::submitForm() presses the button and returns
 * immediately, so an assertion made on the next line can run against a
 * document that is still being replaced. That surfaces as intermittent
 * "stale element reference" and "Unable to locate element //html" errors, or
 * as assertions that read the text of the page being navigated away from.
 */
trait WebDriverFormSubmitTrait {

  /**
   * {@inheritdoc}
   */
  protected function submitForm(array $edit, $submit, $form_html_id = NULL) {
    parent::submitForm($edit, $submit, $form_html_id);
    $this->waitForPage();
  }

  /**
   * Waits for the document to finish loading and for AJAX requests to finish.
   *
   * Call this after any interaction that submits a form without going through
   * ::submitForm(), such as pressing a button on the page directly.
   *
   * @param int $timeout
   *   (optional) Timeout in milliseconds, defaults to 10000.
   */
  protected function waitForPage($timeout = 10000) {
    $session = $this->getSession();

    // A full page submit may not have started navigating yet, in which case
    // document.readyState still reports the outgoing page as 'complete' and
    // the wait below would return against the old document. Give a navigation
    // up to a second to begin. An AJAX submit never leaves the page, so for
    // those this simply runs out and costs the wait.
    $session->wait(1000, "document.readyState !== 'complete'");

    // ::assertWaitOnAjaxRequest() is not usable here because it throws when
    // there is no AJAX request in flight, which is the normal case for a full
    // page submit. Wait on the same conditions directly instead.
    $session->wait($timeout, <<<'JS'
      document.readyState === 'complete'
      && (typeof jQuery === 'undefined' || jQuery.active === 0)
      && (typeof window.drupalActiveXhrCount === 'undefined' || window.drupalActiveXhrCount === 0)
    JS);
  }

}
