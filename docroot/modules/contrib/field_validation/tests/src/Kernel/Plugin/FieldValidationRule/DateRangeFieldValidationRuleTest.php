<?php

namespace Drupal\Tests\field_validation\Kernel\Plugin\FieldValidationRule;

/**
 * Tests DateRangeFieldValidationRule.
 *
 * @group field_validation
 *
 * @package Drupal\Tests\field_validation\Kernel
 */
class DateRangeFieldValidationRuleTest extends FieldValidationRuleBase {

  /**
   * Field name.
   */
  const FIELD_NAME = 'field_date_range_text';

  /**
   * Rule id.
   */
  const RULE_ID = 'date_range_field_validation_rule';

  /**
   * Rule title.
   */
  const RULE_TITLE = 'validation rule date range';

  /**
   * Entity interface.
   *
   * @var \Drupal\Core\Entity\EntityInterface
   */
  protected $entity;

  /**
   * Stores mock ruleset.
   *
   * @var \Drupal\field_validation\Entity\FieldValidationRuleSet
   */
  protected $ruleSet;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->setupTestArticle(self::FIELD_NAME);

    $this->ruleSet = $this->ruleSetStorage
      ->create([
        'name' => 'Date_range_test',
        'entity_type' => 'node',
        'bundle' => 'article',
      ]);
    $this->ruleSet->addFieldValidationRule([
      'id' => self::RULE_ID,
      'title' => self::RULE_TITLE,
      'weight' => 1,
      'field_name' => self::FIELD_NAME,
      'column' => 'value',
      'error_message' => 'Dates are out of range!',
      'data' => [
        'min' => '2012-01-01 08:30:00',
        'max' => '2013-01-01 08:30:00',
        'cycle' => 'global',
      ],
    ]);
    $this->ruleSet->save();

    $this->entity = $this->nodeStorage->create([
      'type' => 'article',
      'title' => 'test',
      self::FIELD_NAME => '',
    ]);
    $this->entity->get(self::FIELD_NAME)
      ->getFieldDefinition()
      ->addConstraint(
        'FieldValidationConstraint',
        ['ruleset_name' => $this->ruleSet->getName()]
      );
  }

  /**
   * Tests valid date inputs.
   *
   * @param string $cycle
   *   Cycle setting.
   * @param string $date_string
   *   Input.
   * @param string $min_date
   *   Minimum date.
   * @param string $max_date
   *   Maximal date.
   *
   * @dataProvider dateValidProvider
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function testValidDateRangeRule(
    string $cycle,
    string $date_string,
    string $min_date,
    string $max_date
  ) {
    $this->ruleSet = $this->updateSettings(
      [
        'min' => $min_date,
        'max' => $max_date,
        'cycle' => $cycle,
      ],
      self::RULE_ID,
      self::RULE_TITLE,
      $this->ruleSet,
      self::FIELD_NAME
    );
    $this->assertConstraintPass(
      $this->entity,
      self::FIELD_NAME,
      $date_string
    );

  }

  /**
   * Tests invalid date inputs.
   *
   * @param string $cycle
   *   Cycle setting.
   * @param string $date_string
   *   Input.
   * @param string $min_date
   *   Minimum date.
   * @param string $max_date
   *   Maximal date.
   *
   * @dataProvider dateInvalidProvider
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function testInvalidDateRangeRule(
    string $cycle,
    string $date_string,
    string $min_date,
    string $max_date
  ) {
    $this->ruleSet = $this->updateSettings(
      [
        'min' => $min_date,
        'max' => $max_date,
        'cycle' => $cycle,
      ],
      self::RULE_ID,
      self::RULE_TITLE,
      $this->ruleSet,
      self::FIELD_NAME
    );
    $this->assertConstraintFail(
      $this->entity,
      self::FIELD_NAME,
      $date_string,
      $this->ruleSet
    );

  }

  /**
   * Data provider with valid dates for each cycle.
   *
   * @return array
   *   Returns dataset.
   */
  public function dateValidProvider() {
    return [
      'global-upper-border' => [
        'global',
        '2018-01-01 08:30:00',
        '2012-01-01 08:30:00',
        '2018-01-01 08:30:00',
      ],
      'global-lower-border' => [
        'global',
        '2012-01-01 08:30:00',
        '2012-01-01 08:30:00',
        '2018-01-01 08:30:00',
      ],
      'year-upper-border' => [
        'year',
        '2011-07-01 08:30:00',
        '2012-03-01 08:30:00',
        '2018-08-01 08:30:00',
      ],
      'year-lower-border' => [
        'year',
        '2011-04-01 08:30:00',
        '2012-03-01 08:30:00',
        '2018-08-01 08:30:00',
      ],
      'month-upper-border' => [
        'month',
        '2014-07-09 08:30:00',
        '2014-03-01 08:30:00',
        '2014-09-09 08:30:00',
      ],
      'month-lower-border' => [
        'month',
        '2014-04-03 08:30:00',
        '2014-03-03 08:30:00',
        '2018-09-09 08:30:00',
      ],
      'day-upper-border' => [
        'day',
        '2013-01-06 08:30:00',
        '2012-01-07 06:30:00',
        '2018-01-07 08:30:00',
      ],
      'day-lower-border' => [
        'day',
        '2012-01-06 08:30:00',
        '2012-01-05 08:30:00',
        '2018-01-01 09:30:00',
      ],
      'hour-upper-border' => [
        'hour',
        '2013-01-01 08:40:00',
        '2012-01-01 08:30:00',
        '2018-01-01 08:40:00',
      ],
      'hour-lower-border' => [
        'hour',
        '2012-01-01 08:30:00',
        '2012-01-01 08:30:00',
        '2018-01-01 08:40:00',
      ],
      'minute-upper-border' => [
        'minute',
        '2012-01-01 08:30:10',
        '2012-01-01 08:00:00',
        '2018-01-01 08:00:10',
      ],
      'minute-lower-border' => [
        'minute',
        '2012-01-01 08:30:05',
        '2012-01-01 08:30:05',
        '2018-01-01 08:30:06',
      ],
    ];
  }

  /**
   * Data provider with invalid dates for each cycle.
   *
   * @return array
   *   Returns dataset.
   */
  public function dateInvalidProvider() {
    return [
      'global-upper-border' => [
        'global',
        '2017-01-01 08:30:00',
        '2012-01-01 08:30:00',
        '2016-01-01 08:30:00',
      ],
      'global-lower-border' => [
        'global',
        '2011-01-01 08:30:00',
        '2012-01-01 08:30:00',
        '2018-01-01 08:30:00',
      ],
      'year-upper-border' => [
        'year',
        '2011-09-01 08:30:00',
        '2012-03-01 08:30:00',
        '2018-08-01 08:30:00',
      ],
      'year-lower-border' => [
        'year',
        '2011-02-01 08:30:00',
        '2012-03-01 08:30:00',
        '2018-08-01 08:30:00',
      ],
      'month-upper-border' => [
        'month',
        '2014-07-10 08:30:00',
        '2014-03-01 08:30:00',
        '2014-09-09 08:30:00',
      ],
      'month-lower-border' => [
        'month',
        '2014-04-02 08:30:00',
        '2014-03-03 08:30:00',
        '2018-09-09 08:30:00',
      ],
      'day-upper-border' => [
        'day',
        '2013-01-06 09:30:00',
        '2012-01-07 06:30:00',
        '2018-01-07 08:30:00',
      ],
      'day-lower-border' => [
        'day',
        '2012-01-06 07:30:00',
        '2012-01-05 08:30:00',
        '2018-01-01 09:30:00',
      ],
      'hour-upper-border' => [
        'hour',
        '2013-01-01 08:45:00',
        '2012-01-01 08:30:00',
        '2018-01-01 08:40:00',
      ],
      'hour-lower-border' => [
        'hour',
        '2012-01-01 08:25:00',
        '2012-01-01 08:30:00',
        '2018-01-01 08:40:00',
      ],
      'minute-upper-border' => [
        'minute',
        '2012-01-01 08:30:15',
        '2012-01-01 08:00:00',
        '2018-01-01 08:00:10',
      ],
      'minute-lower-border' => [
        'minute',
        '2012-01-01 08:30:00',
        '2012-01-01 08:30:05',
        '2018-01-01 08:30:06',
      ],
    ];
  }

}
