<?php

namespace Drupal\Tests\tour\Functional\Taxonomy;

use Drupal\taxonomy\Entity\Vocabulary;
use Drupal\taxonomy\Entity\Term;
use Drupal\Tests\tour\Functional\TourTestBase;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests taxonomy term parameters for tour (bundle vs id).
 */
#[Group('tour')]
class TaxonomyParameterTourTest extends TourTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['taxonomy', 'tour_taxonomy_test'];

  /**
   * {@inheritdoc}
   */
  protected array $permissions = [
    'administer taxonomy',
  ];

  /**
   * Tests taxonomy term parameters for tour (term bundle).
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   * @throws \Drupal\Core\Entity\EntityMalformedException
   */
  public function testEditRouteBundle(): void {
    // Create two vocabularies.
    $tags = Vocabulary::create(['vid' => 'tags', 'name' => 'Tags']);
    $tags->save();
    $categories = Vocabulary::create(['vid' => 'categories', 'name' => 'Categories']);
    $categories->save();

    $this->drupalLogin($this->adminUser);

    // Create a term:
    $term = Term::create(['vid' => 'tags', 'name' => 'Tag 1']);
    $term->save();

    // Go to the edit route and check that the tour tips are displayed:
    $this->drupalGet($term->toUrl('edit-form')->toString());
    $this->assertTourTips();

    // A term with a different bundle should not match:
    $termOther = Term::create(['vid' => 'categories', 'name' => 'Cat 1']);
    $termOther->save();
    $this->drupalGet($termOther->toUrl('edit-form')->toString());
    $this->assertTourTips([], TRUE);
  }

}
