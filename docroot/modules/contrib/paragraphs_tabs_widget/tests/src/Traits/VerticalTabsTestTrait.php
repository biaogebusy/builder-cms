<?php

namespace Drupal\Tests\paragraphs_tabs_widget\Traits;

use Drupal\field\Entity\FieldConfig;
use Drupal\node\Entity\NodeType;
use Drupal\paragraphs\Entity\Paragraph;
use Drupal\paragraphs\ParagraphInterface;

/**
 * Defines methods common to functional-testing Paragraph Tab Widgets.
 */
trait VerticalTabsTestTrait {

  /**
   * A node entity type for the system under test.
   *
   * @var \Drupal\node\NodeTypeInterface
   */
  protected $sutNodeType;

  /**
   * A paragraph field title in a node for the system under test.
   *
   * @var string
   */
  protected $sutParagraphNodeFieldLabel;

  /**
   * A paragraph field in a node for the system under test.
   *
   * @var string
   */
  protected $sutParagraphNodeFieldName;

  /**
   * A text field in a paragraph for the system under test.
   *
   * @var string
   */
  protected $sutParagraphParagraphFieldName;

  /**
   * A paragraph entity type for the system under test.
   *
   * @var string
   */
  protected $sutParagraphTypeName;

  /**
   * Creates a paragraph based on default settings.
   *
   * @param array $values
   *   An associative array of values for the paragraph, as used in creation of
   *   entity. Notably, you must specify a 'type' key, which determines the
   *   paragraph entity bundle. See \Drupal\Core\Entity\EntityBase::create() for
   *   more information.
   *
   * @return \Drupal\paragraphs\ParagraphInterface
   *   The created paragraph entity.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   *   Throws an Entity Storage Exception if the new paragraph cannot be stored.
   */
  protected function drupalCreateParagraph(array $values = []) {
    $paragraph = Paragraph::create($values);
    assert($paragraph instanceof ParagraphInterface);
    $paragraph->save();

    return $paragraph;
  }

  /**
   * Create a paragraph type with a random name and a randomly-named text field.
   */
  protected function setUpRandomParagraphTypeWithRandomTextField() {
    // Create a paragraph type.
    $this->sutParagraphTypeName = strtolower($this->getRandomGenerator()->name());
    $this->addParagraphsType($this->sutParagraphTypeName);

    // Create a field in that paragraph type.
    $this->sutParagraphParagraphFieldName = strtolower($this->getRandomGenerator()->name());
    $this->addFieldtoParagraphType($this->sutParagraphTypeName, $this->sutParagraphParagraphFieldName, 'string');
  }

  /**
   * Create a node type with a randomly-named paragraph reference field in it.
   *
   * Note the paragraph field can reference any type of paragraph.
   */
  protected function setUpRandomNodeTypeWithRandomParagraphRefField() {
    // Create a paragraphs reference field.
    $sutNodeTypeName = strtolower($this->getRandomGenerator()->name());
    $this->sutParagraphNodeFieldName = strtolower($this->getRandomGenerator()->name());

    // Create a node type that uses the paragraphs reference field.
    $this->addParagraphedContentType($sutNodeTypeName, $this->sutParagraphNodeFieldName, 'paragraphs_tabs_widget_vertical_tabs');
    $this->sutNodeType = NodeType::load($sutNodeTypeName);
    $this->sutParagraphNodeFieldLabel = (FieldConfig::loadByName('node', $this->sutNodeType->id(), $this->sutParagraphNodeFieldName))
      ->getLabel();
  }

}
