<?php

namespace Drupal\Tests\queue_ui\Functional;

use Drupal\Core\Url;
use Drupal\Tests\BrowserTestBase;

/**
 * Class WeightChangeTest declaration.
 *
 * @package Drupal\Tests\queue_ui\Functional
 * @group queue_ui
 */
class WeightChangeTest extends BrowserTestBase {

  /**
   * The theme to install as the default for testing.
   *
   * Defaults to the install profile's default theme, if it specifies any.
   *
   * @var string
   */
  protected $defaultTheme = 'stark';

  /**
   * Modules to enable.
   *
   * The test runner will merge the $modules lists from this class, the class
   * it extends, and so on up the class hierarchy. It is not necessary to
   * include modules in your list that a parent class has already declared.
   *
   * @var string[]
   *
   * @see \Drupal\Tests\BrowserTestBase::installDrupal()
   */
  protected static $modules = ['queue_ui_order_fixtures'];

  /**
   * Test reordering defined workers.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   * @throws \Behat\Mink\Exception\ExpectationException
   */
  public function testWeightReordering() {
    $this->drupalLogin($this->createUser(['admin queue_ui']));
    $form_url = Url::fromRoute('queue_ui.overview_form');
    $session = $this->assertSession();
    $this->drupalGet($form_url);
    $session->fieldValueEquals('weight[queue_order_worker_A]', '30');
    $session->fieldValueEquals('weight[queue_order_worker_B]', '20');
    $session->fieldValueEquals('weight[queue_order_worker_C]', '10');
    $session->fieldValueEquals('weight[queue_order_worker_D]', '0');
    $session->fieldValueEquals('weight[queue_order_worker_E]', '-10');
    $session->fieldValueEquals('weight[queue_order_worker_F]', '-20');
    $this->submitForm(
      [
        'weight[queue_order_worker_A]' => '-10',
        'weight[queue_order_worker_B]' => '-8',
        'weight[queue_order_worker_C]' => '-6',
        'weight[queue_order_worker_D]' => '-2',
        'weight[queue_order_worker_E]' => '0',
        'weight[queue_order_worker_F]' => '10',
      ],
      'Save changes'
    );
    $session->fieldValueEquals('weight[queue_order_worker_A]', '-10');
    $session->fieldValueEquals('weight[queue_order_worker_B]', '-8');
    $session->fieldValueEquals('weight[queue_order_worker_C]', '-6');
    $session->fieldValueEquals('weight[queue_order_worker_D]', '-2');
    $session->fieldValueEquals('weight[queue_order_worker_E]', '0');
    $session->fieldValueEquals('weight[queue_order_worker_F]', '10');
  }

}
