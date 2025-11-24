<?php

declare(strict_types=1);

namespace Drupal\Tests\diff\Functional;

use Drupal\Core\Url;
use Drupal\entity_test\Entity\EntityTestRev;
use Drupal\entity_test_revlog\Entity\EntityTestWithRevisionLog;
use Drupal\Tests\BrowserTestBase;

/**
 * Tests the diff controller.
 *
 * @group diff
 */
class DiffControllerTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'diff',
    'entity_test',
    'entity_test_revlog',
    'diff_test',
    'system',
    'user',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->config('diff.settings')
      ->set('entity.entity_test_rev.name', TRUE)
      ->set('entity.entity_test_revlog.name', TRUE)
      ->save();
  }

  /**
   * Tests the Controller.
   */
  public function testController(): void {
    $assert_session = $this->assertSession();

    $entity = EntityTestRev::create([
      'name' => 'test entity 1',
      'type' => 'entity_test_rev',
    ]);
    $entity->save();
    $vid1 = $entity->getRevisionId();

    $entity->name->value = 'test entity 2';
    $entity->setNewRevision(TRUE);
    $entity->save();
    $vi2 = $entity->getRevisionId();

    $url = Url::fromRoute('entity.entity_test_rev.revisions_diff', [
      'entity_test_rev' => $entity->id(),
      'left_revision' => $vid1,
      'right_revision' => $vi2,
    ]);
    $this->drupalGet($url);
    $assert_session->statusCodeEquals(403);

    $account = $this->drupalCreateUser([
      'view test entity',
    ]);
    $this->drupalLogin($account);
    $this->drupalGet($url);
    $assert_session->statusCodeEquals(200);
    $assert_session->responseContains('<td class="diff-context diff-deletedline">test entity <span class="diffchange">1</span></td>');
    $assert_session->responseContains('<td class="diff-context diff-addedline">test entity <span class="diffchange">2</span></td>');
  }

  /**
   * Test comparing revisions when one has a null revision author.
   */
  public function testControllerNullRevisionAuthor(): void {
    $entity = EntityTestWithRevisionLog::create([
      'name' => 'view,test entity 1',
      'type' => 'entity_test_revlog',
    ]);
    $entity->save();
    $vid1 = $entity->getRevisionId();

    $entity->name->value = 'view,test entity 2';
    $entity->setNewRevision(TRUE);
    $entity->set('revision_user', NULL);
    $entity->save();
    $vi2 = $entity->getRevisionId();

    $url = Url::fromRoute('entity.entity_test_revlog.revisions_diff', [
      'entity_test_revlog' => $entity->id(),
      'left_revision' => $vid1,
      'right_revision' => $vi2,
    ]);
    $this->drupalLogin($this->drupalCreateUser([
      'view test entity',
    ]));
    $this->drupalGet($url);
    $this->assertSession()->statusCodeEquals(200);
  }

}
