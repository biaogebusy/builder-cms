<?php

declare(strict_types=1);

namespace Drupal\Tests\diff\Functional;

use Drupal\Component\Utility\Random;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\RevisionLogInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Url;

/**
 * Defines a trait to easily add diff tests for a given content-type.
 */
trait DiffTestTrait {

  /**
   * Gets the entity to perform tests with.
   *
   * @return \Drupal\Core\Entity\RevisionLogInterface&\Drupal\Core\Entity\ContentEntityInterface
   *   Test entity.
   */
  abstract protected function getTestEntity(): ContentEntityInterface & RevisionLogInterface;

  /**
   * Gets the user to perform tests with.
   *
   * Consuming classes are encouraged to make use of ::drupalCreateUser to
   * return a user with the appropriate permissions.
   *
   * @return \Drupal\Core\Session\AccountInterface
   *   Test user.
   */
  abstract protected function getTestUser(): AccountInterface;

  /**
   * Tests the revision diff overview.
   */
  public function testRevisionDiffOverview(): void {
    $this->drupalLogin($this->getTestUser());

    $random = new Random();

    $entity = $this->getTestEntity();
    $log1 = $random->sentences(10);
    $log2 = $random->sentences(10);
    $log3 = $random->sentences(10);

    $entity->setRevisionLogMessage($log1);
    $entity->setRevisionTranslationAffected(TRUE);
    $entity->setNewRevision(FALSE);
    $entity->save();
    $left_revision = $entity->getRevisionId();

    $entity->setNewRevision();
    $entity->setRevisionTranslationAffected(TRUE);
    $entity->setRevisionLogMessage($log2);
    $entity->save();
    $right_revision = $entity->getRevisionId();

    $entity->setNewRevision();
    $entity->setRevisionTranslationAffected(TRUE);
    $entity->isDefaultRevision(FALSE);
    $entity->setRevisionLogMessage($log3);
    $entity->save();
    $rev3 = $entity->getRevisionId();

    $this->drupalGet($entity->toUrl('version-history'));
    $this->assertSession()->statusCodeEquals(200);
    $rows = $this->xpath('//tbody/tr');
    // Make sure only two revisions available.
    self::assertCount(3, $rows);
    // Assert the revision summary.
    $this->assertSession()->pageTextContains($log1);
    $this->assertSession()->pageTextContains($log2);
    $this->assertSession()->pageTextContains($log3);

    // Assert revert links render correctly.
    $revertLink = $this->getSession()->getPage()->find('named', ['link', 'Revert']);
    self::assertNotEmpty($revertLink);
    $rev1RevertUrl = Url::fromRoute(\sprintf('entity.%s.revision_revert_form', $entity->getEntityTypeId()), [
      $entity->getEntityTypeId() => $entity->id(),
      $entity->getEntityTypeId() . '_revision' => $left_revision,
    ]);
    self::assertStringContainsString($rev1RevertUrl->toString(), $revertLink->getAttribute('href'));
    $setAsCurrentLink = $this->getSession()->getPage()->find('named', ['link', 'Set as current revision']);
    self::assertNotEmpty($setAsCurrentLink);
    $rev3RevertUrl = Url::fromRoute(\sprintf('entity.%s.revision_revert_form', $entity->getEntityTypeId()), [
      $entity->getEntityTypeId() => $entity->id(),
      $entity->getEntityTypeId() . '_revision' => $rev3,
    ]);
    self::assertStringContainsString($rev3RevertUrl->toString(), $setAsCurrentLink->getAttribute('href'));

    // Assert the submit button.
    $this->assertSession()
      ->elementExists('xpath', '//input[@type="submit" and @id="edit-submit" and @value="Compare selected revisions"]');
    $this->assertSession()
      ->elementNotExists('xpath', '//input[@type="submit" and @id="edit-submit-top" and @value="Compare selected revisions"]');

    // Compare the revisions in standard mode.
    $this->submitForm([
      'radios_left' => $left_revision,
      'radios_right' => $right_revision,
    ], 'Compare selected revisions');
    $diffUrl = Url::fromRoute(\sprintf('entity.%s.revisions_diff', $entity->getEntityTypeId()), [
      $entity->getEntityTypeId() => $entity->id(),
      'left_revision' => $left_revision,
      'right_revision' => $right_revision,
      'filter' => 'visual_inline',
    ]);
    $this->assertSession()->addressEquals($diffUrl);
    $this->assertSession()->statusCodeEquals(200);
    // Assert the revision summary.
    $this->assertSession()->pageTextContains($log1);
    $this->assertSession()->pageTextContains($log2);

    $this->drupalLogout();
    $this->drupalGet($diffUrl);
    $this->assertSession()->statusCodeEquals(403);
  }

  /**
   * Tests revisions overview with revisions lacking creation timestamps.
   */
  public function testMissingRevisionCreationTimestamp(): void {
    $this->drupalLogin($this->getTestUser());

    $entity = $this->getTestEntity();

    // Simulate a piece of content added prior to Drupal 10.2.
    // @phpstan-ignore-next-line
    $entity->setRevisionCreationTime(NULL);
    $entity->save();
    $left_revision = $entity->getRevisionId();

    $entity->setNewRevision();
    $entity->setRevisionTranslationAffected(TRUE);
    $entity->save();
    $right_revision = $entity->getRevisionId();

    $this->drupalGet($entity->toUrl('version-history'));
    $this->assertSession()->statusCodeEquals(200);

    $this->submitForm([
      'radios_left' => $left_revision,
      'radios_right' => $right_revision,
    ], 'Compare selected revisions');
    $this->assertSession()->statusCodeEquals(200);
  }

  /**
   * Tests revisions overview with uncommon multiple revisions.
   */
  public function testMultipleRevisionsNoChanges(): void {
    $this->drupalLogin($this->getTestUser());

    $entity = $this->getTestEntity();

    // Revision 1 will have revision_translation_affected set to 1.
    $entity->save();

    $this->drupalGet($entity->toUrl('edit-form'));

    // Re-saving this entity will create a revision, but the
    // revision_translated_affected flag will be null.
    $this->submitForm(['revision' => '1'], 'Save');

    $this->drupalGet($entity->toUrl('version-history'));
    $rows = $this->xpath('//tbody/tr');

    // Make sure only one revision is available.
    self::assertCount(1, $rows);

    // Make sure no radios appear sine there's only one table row.
    $this->assertSession()->elementNotExists('css', '[name="radios_left"]');
  }

}
