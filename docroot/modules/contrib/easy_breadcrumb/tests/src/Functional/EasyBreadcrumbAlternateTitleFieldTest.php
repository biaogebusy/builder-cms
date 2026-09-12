<?php

declare(strict_types=1);

namespace Drupal\Tests\easy_breadcrumb\Functional;

use Drupal\easy_breadcrumb\EasyBreadcrumbConstants;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\node\Entity\Node;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests the ALTERNATIVE_TITLE_FIELD configuration.
 */
#[Group('easy_breadcrumb')]
class EasyBreadcrumbAlternateTitleFieldTest extends EasyBreadcrumbBrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'easy_breadcrumb',
    'block',
    'node',
    'path',
  ];

  /**
   * The testing node.
   */
  protected Node $node;

  /**
   * The testing node with HTML character references in the page title.
   */
  protected Node $nodeWithHtmlCharacterReference;

  /**
   * The name of the alternate field.
   */
  protected string $fieldName;

  /**
   * The value of the alternate field.
   */
  protected string $fieldValue;

  /**
   * The value of the alternate field with HTML character references.
   */
  protected string $fieldValueWithHtmlCharacterReference;

  /**
   * The title of the page.
   */
  protected string $pageTitle;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->easyBreadcrumbCreateAndLoginAdminUser();

    // Creates a content type with a plain text field "field_breadcrumb_title"
    // and a node with that field data populated.
    $this->drupalCreateContentType(['type' => 'page']);
    $this->fieldName = 'field_breadcrumb_title';
    $this->fieldValue = 'Test Breadcrumb Alternate Title Field';
    $this->pageTitle = 'Test Page';
    $this->fieldValueWithHtmlCharacterReference = 'Page & Test';

    FieldStorageConfig::create([
      'field_name' => $this->fieldName,
      'entity_type' => 'node',
      'type' => 'string',
    ])->save();

    FieldConfig::create([
      'field_name' => $this->fieldName,
      'entity_type' => 'node',
      'bundle' => 'page',
      'label' => 'Breadcrumb Title',
    ])->save();

    $this->node = $this->drupalCreateNode([
      'type' => 'page',
      'title' => $this->pageTitle,
      'path' => [
        'alias' => '/alternate-title-field-test-page',
      ],
      $this->fieldName => $this->fieldValue,
    ]);

    $this->nodeWithHtmlCharacterReference = $this->drupalCreateNode([
      'type' => 'page',
      'title' => $this->pageTitle,
      'path' => [
        'alias' => '/alternate-title-field-test-page-with-html-character-reference',
      ],
      $this->fieldName => $this->fieldValueWithHtmlCharacterReference,
    ]);
  }

  /**
   * Tests the ALTERNATIVE_TITLE_FIELD configuration.
   */
  public function testAlternateTitleField() {
    // Asserts that the title from the alternate field is used when the config
    // is set.
    $this->easyBreadcrumbSetConfig(EasyBreadcrumbConstants::ALTERNATIVE_TITLE_FIELD, $this->fieldName);
    $this->drupalGet($this->node->toUrl());
    $this->easyBreadcrumbAssertSegmentTextEquals(2, $this->fieldValue);

    // Asserts that the title from the page is used when the config is not set.
    $this->easyBreadcrumbSetConfig(EasyBreadcrumbConstants::ALTERNATIVE_TITLE_FIELD, '');
    $this->drupalGet($this->node->toUrl());
    $this->easyBreadcrumbAssertSegmentTextEquals(2, $this->pageTitle);
  }

  /**
   * Tests an alternate field value with an HTML character reference.
   */
  public function testWithHtmlCharacterReference() {
    $this->easyBreadcrumbSetConfig(EasyBreadcrumbConstants::ALTERNATIVE_TITLE_FIELD, $this->fieldName);
    $this->drupalGet($this->nodeWithHtmlCharacterReference->toUrl());
    $this->easyBreadcrumbAssertSegmentTextEquals(2, $this->fieldValueWithHtmlCharacterReference);
  }

}
