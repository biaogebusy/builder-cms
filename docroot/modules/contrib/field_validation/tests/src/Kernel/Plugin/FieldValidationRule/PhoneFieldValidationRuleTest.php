<?php

namespace Drupal\Tests\field_validation\Kernel\Plugin\FieldValidationRule;

/**
 * Tests PhoneFieldValidationRule.
 *
 * @group field_validation
 *
 * @package Drupal\Tests\field_validation\Kernel
 */
class PhoneFieldValidationRuleTest extends FieldValidationRuleBase {
  /**
   * Stores mock ruleset.
   *
   * @var \Drupal\field_validation\Entity\FieldValidationRuleSet
   */
  protected $ruleSet;

  /**
   * Field name.
   */
  const FIELD_NAME = 'field_phone_text';

  /**
   * Rule id.
   */
  const RULE_ID = 'phone_field_validation_rule';

  /**
   * Rule title.
   */
  const RULE_TITLE = 'Phone number must be correct for country';

  /**
   * Entity interface.
   *
   * @var \Drupal\Core\Entity\EntityInterface
   */
  protected $entity;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->setupTestArticle(self::FIELD_NAME);

    $this->ruleSet = $this->ruleSetStorage->create([
      'name' => 'phone',
      'entity_type' => 'node',
      'bundle' => 'article',
    ]);
    $this->ruleSet->addFieldValidationRule([
      'id' => self::RULE_ID,
      'title' => self::RULE_TITLE,
      'weight' => 1,
      'field_name' => self::FIELD_NAME,
      'column' => 'value',
      'error_message' => 'Field is not empty',
      'data' => [
        'country' => 'fr',
      ],
    ]);
    $this->ruleSet->save();

    $this->entity = $this->nodeStorage->create([
      'type' => 'article',
      'title' => 'test',
      self::FIELD_NAME => '123 123 invalid',
    ]);
    $this->entity->get(self::FIELD_NAME)
      ->getFieldDefinition()
      ->addConstraint(
        'FieldValidationConstraint',
        ['ruleset_name' => $this->ruleSet->getName()]
      );
  }

  /**
   * Tests valid phone numbers by country.
   *
   * @param string $country
   *   Country to test.
   * @param string $value
   *   Value to test.
   *
   * @dataProvider phoneNumbersProviderValid
   *   Data provider with valid phone numbers.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function testPhoneFieldValid(string $country, string $value) {
    $data = [
      'country' => $country,
    ];
    $this->ruleSet = $this->updateSettings(
      $data,
      self::RULE_ID,
      self::RULE_TITLE,
      $this->ruleSet,
      self::FIELD_NAME
    );
    $this->assertConstraintPass($this->entity, self::FIELD_NAME, $value);
  }

  /**
   * Tests invalid phone numbers.
   *
   * @param string $country
   *   Country to test.
   * @param string $value
   *   Value to test.
   *
   * @dataProvider phoneNumbersProviderInvalid
   *    Data provider with invalid phone numbers.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function testPhoneFieldInvalid(string $country, string $value) {
    $data = [
      'country' => $country,
    ];
    $this->ruleSet = $this->updateSettings(
      $data,
      self::RULE_ID,
      self::RULE_TITLE,
      $this->ruleSet,
      self::FIELD_NAME
    );
    $this->assertConstraintFail(
      $this->entity,
      self::FIELD_NAME,
      $value,
      $this->ruleSet
    );
  }

  /**
   * Tests malformed and empty phone input.
   *
   * @param string $country
   *   Country to test.
   *
   * @dataProvider phoneNumbersProviderMalformed
   *    Data provider for malformed inputs.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function testPhoneFieldMalformed(string $country) {
    $this->ruleSet = $this->updateSettings(
      ['country' => $country],
      self::RULE_ID,
      self::RULE_TITLE,
      $this->ruleSet,
      self::FIELD_NAME
    );
    $this->emptyAndMalformed(
      self::FIELD_NAME,
      $this->entity,
      $this->ruleSet
    );
  }

  /**
   * Data provider for valid phone numbers.
   */
  public function phoneNumbersProviderValid() {
    return [
      'Fr-1' => ['fr', '+33495035197'],
      'Fr-2' => ['fr', '0751934400'],
      'Fr-3' => ['fr', '+330901260122'],
      'Be-1' => ['be', '069346426'],
      'Be-2' => ['be', '+3225541437'],
      'It-1' => ['it', '+39368775417'],
      'El/Gr-1' => ['el', '+302589166966'],
      'El/Gr-2' => ['el', '+30 2589166966'],
      'El/Gr-3' => ['el', '+30 258 9166966'],
      'El/Gr-4' => ['el', '+30 2589 166966'],
      'El/Gr-5' => ['el', '6949707903'],
      'El/Gr-6' => ['el', '694 9707903'],
      'El/Gr-7' => ['el', '6949 707903'],
      'Ch-1' => ['ch', '+41366802896'],
      'Ch-2' => ['ch', '+410366802896'],
      'Ch-3' => ['ch', '0041366802896'],
      'Us/Ca-1' => ['ca', '16048924417'],
      'Us/Ca-2' => ['ca', '(890)659-63885608'],
      'Us/Ca-3' => ['ca', '+1-604-892-4417'],
      'Cr-1' => ['cr', '00-61-13-31-23'],
      'Cr-2' => ['cr', '0061133123'],
      'Cr-3' => ['cr', '61133123'],
      'Pa-1' => ['pa', '+666 132 1234'],
      'Pa-2' => ['pa', '00666 132 1234'],
      'Ru-1' => ['ru', '+7-1234-123-12'],
      'Ru-2' => ['ru', '+8 1234 123 12'],
      'Es-1' => ['es', '090-1223564'],
      'Es-2' => ['es', '090-122356'],
      'Cz-1' => ['cs', '00420 123 123 123'],
      'Cz-2' => ['cs', '+420 123 123 123'],
      'Cz-3' => ['cs', '00420123123123'],
      'Cz-4' => ['cs', '+420123123123'],
      'Pl-mobile-1' => ['hu', '+06123123123'],
      'Pl-mobile-2' => ['hu', '+36123123123'],
      'Pl-1' => ['pl', '+48 123-123-123'],
      'Pl-2' => ['pl', '+48 123 123 123'],
      'Pl-3' => ['pl', '+48 123123123'],
      'Nl-1' => ['nl', '06-59989008'],
      'Se-1' => ['se', '+46 55 12 12 12'],
      'Se-2' => ['se', '+46 55 123 123 12'],
      'Za-1' => ['za', '+27 12 123 1234'],
      'Za-2' => ['za', '+27121231234'],
      'Za-3' => ['za', '27121231234'],
      'Br-1' => ['br', '+55 012 100 1234'],
      'Br-2' => ['br', '+5501210001234'],
      'Br-3' => ['br', '+55-012-1000-1234'],
      'Cn-1' => ['cn', '+86 12345678911'],
      'Cn-2' => ['cn', '86 123 45678911'],
      'Ph-1' => ['ph', '+63 2835556870'],
      'Sg-1' => ['sg', '+65 61234567'],
      'Sg-2' => ['sg', '+6581234567'],
      'Sg-3' => ['sg', '+65 91234567'],
      'Jo-1' => ['jo', '+962-781234567'],
      'Jo-2' => ['jo', '962-781234567'],
      'Jo-3' => ['jo', '0-781234567'],
      'Pk-1' => ['pk', '+92-(01234)-1234'],
      'In-1' => ['in', '+091123456789'],
    ];
  }

  /**
   * Data provider with invalid phone numbers by country.
   *
   * @return array
   *   Returns dataset.
   */
  public function phoneNumbersProviderInvalid() {
    return [
      'Fr-1' => ['fr', '+33495035197a'],
      'Be-1' => ['be', '069346426a'],
      'It-1' => ['it', '+39368775417a'],
      'El/Gr-1' => ['el', '+302589166966a'],
      'Ch-1' => ['ch', '+41366802896a'],
      'Us/Ca-1' => ['ca', '160a48123a924417'],
      'Cr-1' => ['cr', '00-61a-13-31-23a'],
      'Pa-1' => ['pa', '+666 13a2 1234a'],
      'Ru-1' => ['ru', '+1-1234-123-12'],
      'Es-1' => ['es', '090-1223564a'],
      'Cz-1' => ['cs', '00420 123 123 123a'],
      'Pl-mobile-1' => ['hu', '+071a23123123'],
      'Pl-1' => ['pl', '+48 123-123-123a'],
      'Nl-1' => ['nl', '06-599a89008a'],
      'Se-1' => ['se', '+46 55 12 12 12a'],
      'Za-1' => ['za', '+27 12 123 1234a'],
      'Br-1' => ['br', '+55 012 100 1234a'],
      'Cn-1' => ['cn', '+86 12345678911a'],
      'Ph-1' => ['ph', 'a63 2835556870a'],
      'Sg-1' => ['sg', '+65 61234567a'],
      'Jo-1' => ['jo', '+962-781234567a'],
      'Pk-1' => ['pk', '+92-(01234)-1234a'],
      'In-1' => ['in', '92 123456789a'],
    ];
  }

  /**
   * Data provider for malformed and empty input.
   *
   * @return array
   *   Returns dataset.
   */
  public function phoneNumbersProviderMalformed() {
    return [
      ['fr'],
      ['be'],
      ['it'],
      ['el'],
      ['ch'],
      ['ca'],
      ['pa'],
      ['gb'],
      ['ru'],
      ['es'],
      ['cs'],
      ['hu'],
      ['pl'],
      ['nl'],
      ['se'],
      ['za'],
      ['br'],
      ['cl'],
      ['cn'],
      ['ph'],
      ['sg'],
      ['jo'],
      ['pk'],
      ['in'],
      ['dk'],
    ];
  }

}
