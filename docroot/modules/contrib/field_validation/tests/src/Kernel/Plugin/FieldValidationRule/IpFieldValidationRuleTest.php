<?php

namespace Drupal\Tests\field_validation\Kernel\Plugin\FieldValidationRule;

/**
 * Tests IpFieldValidationRule.
 *
 * @group field_validation
 *
 * @package Drupal\Tests\field_validation\Kernel
 */
class IpFieldValidationRuleTest extends FieldValidationRuleBase {

  /**
   * Field name.
   */
  const FIELD_NAME = 'field_ip_text';

  /**
   * Rule id.
   */
  const RULE_ID = 'ip_field_validation_rule';

  /**
   * Rule title.
   */
  const RULE_TITLE = 'validation rule ip';

  /**
   * Stores mock ruleset.
   *
   * @var \Drupal\field_validation\Entity\FieldValidationRuleSet
   */
  protected $ruleSet;

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
      'name' => 'ip_rule_test',
      'entity_type' => 'node',
      'bundle' => 'article',
    ]);
    $this->ruleSet->addFieldValidationRule([
      'id' => self::RULE_ID,
      'title' => self::RULE_TITLE,
      'weight' => 1,
      'field_name' => self::FIELD_NAME,
      'column' => 'value',
      'error_message' => 'IP is malformed',
      'data' => [
        'version' => 4,
      ],
    ]);
    $this->ruleSet->save();

    $this->entity = $this->nodeStorage->create([
      'type' => 'article',
      'title' => 'test',
      self::FIELD_NAME => '192.168..1.1',
    ]);
    $this->entity->get(self::FIELD_NAME)
      ->getFieldDefinition()
      ->addConstraint(
        'FieldValidationConstraint',
        ['ruleset_name' => $this->ruleSet->getName()]
      );
  }

  /**
   * Tests valid ip inputs.
   *
   * @param string $version
   *   IP version.
   * @param string $value
   *   Value to test.
   *
   * @dataProvider ipValidProvider
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function testValidIpRule(string $version, string $value) {
    $this->ruleSet = $this->updateSettings(
      ['version' => $version],
      self::RULE_ID,
      self::RULE_TITLE,
      $this->ruleSet,
      self::FIELD_NAME
    );
    $this->assertConstraintPass($this->entity, self::FIELD_NAME, $value);
  }

  /**
   * Tests Invalid ip inputs.
   *
   * @param string $version
   *   IP version.
   * @param string $value
   *   Value to test.
   *
   * @dataProvider ipInvalidProvider
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function testInvalidIpRule(string $version, string $value) {
    $this->ruleSet = $this->updateSettings(
      ['version' => $version],
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
   * Tests empty and malformed input.
   *
   * @param string $version
   *   Version of IP.
   *
   * @dataProvider ipMalformedAndEmptyProvider
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function testMaloformedAndEmptyIp(string $version) {
    $this->ruleSet = $this->updateSettings(
      ['version' => $version],
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
   * Data provider for valid ip by version.
   *
   * @return array
   *   Returns the dataset.
   */
  public function ipValidProvider() {
    return [
      'ipv4-1' => ['4', '192.168.1.1'],
      'ipv6-1' => ['6', '1200:0000:AB00:1234:0000:2552:7777:1313'],
      'ipv6-2' => ['6', '21DA:D3:0:2F3B:2AA:FF:FE28:9C5A'],
      'ipv6-3' => ['6', '21DA:D3::2F3B:2AA:FF:FE28:9C5A'],
      'ipv6-4' => ['6', '2001:3452:4952:2837::'],
      'ipv4-no-priv-1' => ['4_no_priv', '171.0.1.10'],
      'ipv6-no-priv-1' => ['6_no_priv', '3731:54:65fe:2::a7'],
      'all-1' => ['all', '192.168.1.1'],
      'all-2' => ['all', 'fd57:5e8d:962e:5ee5::'],
      'all-3' => ['all', '3731:54:65fe:2::a7'],
      'all-1-no-private' => ['all_no_priv', '171.0.1.10'],
      'all-2-no-private' => ['all_no_priv', '3731:54:65fe:2::a7'],
      'all-1-no-reserved' => ['all_no_res', '3731:54:65fe:2::a7'],
      'all-2-no-reserved' => ['all_no_res', '171.0.1.10'],
      'all-1-public' => ['all_public', '171.0.1.10'],
      'all-2-public' => ['all_public', '3731:54:65fe:2::a7'],
      'ipv4-no-reserved' => ['4_no_res', '171.0.1.10'],
      'ipv6-no-reserved' => ['6_no_res', '3731:54:65fe:2::a7'],
      'ipv4-public' => ['4_public', '171.0.1.10'],
      'ipv6-public' => ['6_public', '3731:54:65fe:2::a7'],
    ];
  }

  /**
   * Data provider for invalid ip by version.
   *
   * @return array
   *   Returns the dataset.
   */
  public function ipInvalidProvider() {
    return [
      'ipv4-1' => ['4', '1200:0000:AB00:1234:0000:2552:7777:1313'],
      'ipv6-1' => ['6', '192.168.1.1'],
      'ipv6-2' => ['6', '[2001:db8:0:1]:80'],
      'ipv6-3' => ['6', 'http://[2001:db8:0:1]:80'],
      'ipv4-no-priv-1' => ['4_no_priv', '192.168.0.0'],
      'ipv4-no-priv-2' => ['4_no_priv', '172.16.0.0'],
      'ipv4-no-priv-3' => ['4_no_priv', '10.0.0.0'],
      'ipv4-no-priv-4' => ['4_no_priv', '21DA:D3:0:2F3B:2AA:FF:FE28:9C5A'],
      'ipv6-no-priv-1' => ['6_no_priv', 'fd57:5e8d:962e:5ee5::'],
      'ipv6-no-priv-2' => ['6_no_priv', '192.168.1.1'],
      'all-1-no-private' => ['all_no_priv', '192.168.0.0'],
      'all-2-no-private' => ['all_no_priv', 'fd57:5e8d:962e:5ee5::'],
      'all-1-no-reserved' => ['all_no_res', '::1'],
      'all-2-no-reserved' => ['all_no_res', '169.254.255.255'],
      'all-1-public' => ['all_public', '192.168.0.0'],
      'all-2-public' => ['all_public', 'fd57:5e8d:962e:5ee5::'],
      'ipv4-no-reserved' => ['4_no_res', '169.254.255.255'],
      'ipv6-no-reserved' => ['6_no_res', '::1'],
      'ipv4-public' => ['4_public', '192.168.0.0'],
      'ipv6-public' => ['6_public', 'fd57:5e8d:962e:5ee5::'],
    ];
  }

  /**
   * Data provider for malformed and empty input.
   *
   * @return array
   *   Returns the dataset.
   */
  public function ipMalformedAndEmptyProvider() {
    return [
      ['4'],
      ['4_no_priv'],
      ['4_no_res'],
      ['4_public'],
      ['6'],
      ['6_no_priv'],
      ['6_no_res'],
      ['6_public'],
      ['all'],
      ['all_no_priv'],
      ['all_no_res'],
      ['all_public'],
    ];
  }

}
