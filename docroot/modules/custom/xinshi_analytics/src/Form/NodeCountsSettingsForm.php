<?php

declare(strict_types=1);

namespace Drupal\xinshi_analytics\Form;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\TypedConfigManagerInterface;
use Drupal\Core\Datetime\TimeZoneFormHelper;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\ConfigTarget;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\xinshi_analytics\CountQuery;
use Symfony\Component\DependencyInjection\ContainerInterface;

/** Edits the site-owned automatic count policy without installing defaults. */
final class NodeCountsSettingsForm extends ConfigFormBase {

  private const CONFIG_NAME = 'xinshi_analytics.node_counts';

  /** Suggested initial values and the existing source protocol ceilings. */
  private const LIMITS = [
    'max_calendar_months' => [12, 120],
    'max_scanned_entities' => [5000, CountQuery::MAX_INTEGER],
    'max_groups' => [100, 100],
    'max_seconds' => [10, 60],
  ];

  /** Constructs the form with site configuration and installed languages. */
  public function __construct(
    ConfigFactoryInterface $config_factory,
    TypedConfigManagerInterface $typed_config_manager,
    private readonly LanguageManagerInterface $languageManager,
  ) {
    parent::__construct($config_factory, $typed_config_manager);
  }

  /** {@inheritdoc} */
  public static function create(ContainerInterface $container) {
    return new static($container->get('config.factory'), $container->get('config.typed'),
      $container->get('language_manager'));
  }

  /** {@inheritdoc} */
  public function getFormId(): string {
    return 'xinshi_analytics_node_counts_settings';
  }

  /** {@inheritdoc} */
  protected function getEditableConfigNames(): array {
    return [self::CONFIG_NAME];
  }

  /** {@inheritdoc} */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $config = $this->config(self::CONFIG_NAME);
    $policy = $config->get('query_policy') ?? [];
    $languages = [];
    foreach ($this->languageManager->getLanguages() as $id => $language) {
      $languages[$id] = $language->getName();
    }
    $timezone = $config->isNew()
      ? $this->config('system.date')->get('timezone.default') : ($policy['timezone'] ?? NULL);
    $timezones = TimeZoneFormHelper::getOptionsList();
    // Preserve valid aliases already accepted by the source or the site settings.
    if (CountQuery::timezone($timezone) && !isset($timezones[$timezone])) {
      $timezones[$timezone] = $timezone;
    }
    $form['enabled'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('启用内容类型数量统计'),
      '#description' => $this->t('自动提供站点现有内容类型的数量统计。查询仍需统计权限，并按当前账号的内容、翻译及字段访问权限计数。'),
      '#default_value' => $config->get('enabled') === TRUE,
      '#config_target' => self::CONFIG_NAME . ':enabled',
    ];
    $form['query_policy'] = [
      '#type' => 'details', '#title' => $this->t('统计范围与查询限额'),
      '#open' => TRUE, '#tree' => TRUE,
    ];
    $form['query_policy']['timezone'] = [
      '#type' => 'select', '#title' => $this->t('统计时区'),
      '#description' => $this->t('用于解释日历月份范围。首次配置建议使用站点默认时区。'),
      '#options' => $timezones, '#empty_option' => $this->t('- 请选择 -'),
      '#required' => TRUE, '#default_value' => $timezone,
      '#config_target' => self::CONFIG_NAME . ':query_policy.timezone',
    ];
    $form['query_policy']['languages'] = [
      '#type' => 'checkboxes', '#title' => $this->t('允许的内容语言'),
      '#description' => $this->t('每次查询明确选择一种语言，不合并各语言翻译，也不自动回退到其他语言。'),
      '#options' => $languages, '#required' => TRUE,
      '#default_value' => $config->isNew() ? array_keys($languages) : ($policy['languages'] ?? []),
      '#config_target' => new ConfigTarget(self::CONFIG_NAME, 'query_policy.languages',
        toConfig: [self::class, 'selectedLanguages']),
    ];
    $labels = [
      'max_calendar_months' => $this->t('范围查询最多涉及月份数'),
      'max_scanned_entities' => $this->t('单次最多扫描候选实体数'),
      'max_groups' => $this->t('单次最多结果分组数'),
      'max_seconds' => $this->t('扫描时限（秒）'),
    ];
    $descriptions = [
      'max_calendar_months' => $this->t('仅限制指定时间范围的查询，全部总数不受月份上限限制。'),
      'max_scanned_entities' => $this->t('全部和范围查询都受此限制。超限会报错，不返回部分统计结果。'),
      'max_groups' => $this->t('限制结果分组数量。当前自动内容类型统计只返回一组总数。'),
      'max_seconds' => $this->t('扫描过程中检查的时间上限，不能中止已经执行中的数据库语句。'),
    ];
    foreach (self::LIMITS as $key => [$suggested, $maximum]) {
      $form['query_policy'][$key] = [
        '#type' => 'number', '#title' => $labels[$key],
        '#description' => $descriptions[$key],
        '#min' => 1, '#max' => $maximum, '#step' => 1, '#required' => TRUE,
        '#default_value' => $config->isNew() ? $suggested : ($policy[$key] ?? NULL),
        '#config_target' => self::CONFIG_NAME . ':query_policy.' . $key,
      ];
    }
    $form['notice'] = [
      '#markup' => '<p>' . $this->t('保存配置会使既有统计证据失效，需要重新分析。此处不会授予查询权限；应用中的统计入口还需单独接入。') . '</p>',
    ];
    return parent::buildForm($form, $form_state);
  }

  /** Converts Form API checkbox values to the source contract list. */
  public static function selectedLanguages(array $values): array {
    return array_values(array_filter($values));
  }

  /** {@inheritdoc} */
  public function validateForm(array &$form, FormStateInterface $form_state): void {
    $form_state->setValue('enabled', (bool) $form_state->getValue('enabled'));
    $policy = $form_state->getValue('query_policy');
    if (!CountQuery::timezone($policy['timezone'] ?? NULL)) {
      $form_state->setError($form['query_policy']['timezone'], $this->t('请选择有效的统计时区。'));
    }
    $languages = self::selectedLanguages($policy['languages'] ?? []);
    if (!CountQuery::strings($languages, 100, 1)
      || count(array_filter($languages, CountQuery::language(...))) !== count($languages)
      || array_diff($languages, array_keys($this->languageManager->getLanguages()))) {
      $form_state->setError($form['query_policy']['languages'], $this->t('请选择至少一种站点已安装的有效语言，最多 100 种。'));
    }
    foreach (self::LIMITS as $key => [, $maximum]) {
      $value = filter_var($policy[$key] ?? NULL, FILTER_VALIDATE_INT);
      if (!CountQuery::integer($value, $maximum)) {
        $form_state->setError($form['query_policy'][$key], $this->t('请输入 1 至 @maximum 之间的整数。', ['@maximum' => $maximum]));
      }
      else {
        $form_state->setValue(['query_policy', $key], $value);
      }
    }
    // Core validates config targets and saves them only after successful Form API validation.
    parent::validateForm($form, $form_state);
  }

}
