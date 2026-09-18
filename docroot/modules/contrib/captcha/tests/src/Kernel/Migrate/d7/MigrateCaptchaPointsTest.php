<?php

namespace Drupal\Tests\captcha\Kernel\Migrate\d7;

use Drupal\Tests\migrate_drupal\Kernel\d7\MigrateDrupal7TestBase;
use Drupal\captcha\CaptchaPointInterface;
use Drupal\captcha\Constants\CaptchaConstants;
use Drupal\captcha\Entity\CaptchaPoint;

/**
 * Migrates various configuration objects owned by the captcha module.
 *
 * @group captcha
 */
class MigrateCaptchaPointsTest extends MigrateDrupal7TestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['captcha'];

  /**
   * The expected captcha points from the Captcha fixture.
   *
   * @var array[]
   */
  protected $captchaPoints = [
    [
      'form_id' => 'comment_node_article_form',
      'captcha_type' => CaptchaConstants::CAPTCHA_MATH_CAPTCHA_TYPE,
      'status' => TRUE,
    ],
    [
      'form_id' => 'user_pass',
      'captcha_type' => CaptchaConstants::CAPTCHA_MATH_CAPTCHA_TYPE,
      'status' => TRUE,
    ],
  ];

  /**
   * {@inheritdoc}
   */
  protected $captchaStorage;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->loadFixture(implode(DIRECTORY_SEPARATOR, [
      DRUPAL_ROOT,
      \Drupal::service('extension.list.module')->getPath('captcha'),
      'tests',
      'fixtures',
      'drupal7.php',
    ]));

    $this->installEntitySchema('captcha_point');
    $this->installSchema('captcha', ['captcha_sessions']);
    $this->installConfig('captcha');

    $migrations = [
      'd7_captcha_points',
    ];
    $this->executeMigrations($migrations);
  }

  /**
   * Tests a single captcha point type.
   *
   * @param string $form_id
   *   The captcha point form id.
   * @param string $captcha_type
   *   The expected captcha type for the config.
   * @param bool $status
   *   The expected status for a captcha type.
   */
  protected function assertEntity(string $form_id, string $captcha_type, $status) {
    /** @var \Drupal\captcha\CaptchaPointInterface $entity */
    $entity = CaptchaPoint::load($form_id);
    $this->assertInstanceOf(CaptchaPointInterface::class, $entity);
    $this->assertSame($form_id, $entity->getFormId());
    $this->assertSame($captcha_type, $entity->getCaptchaType());
    $this->assertSame($status, $entity->status());
  }

  /**
   * Tests that all expected configuration gets migrated.
   */
  public function testCaptchaPointsMigration() {
    // Test captcha points.
    foreach ($this->captchaPoints as $captcha_point) {
      $this->assertEntity($captcha_point['form_id'], $captcha_point['captcha_type'], $captcha_point['status']);
    }
  }

}
