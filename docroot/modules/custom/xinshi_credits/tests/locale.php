<?php

declare(strict_types=1);

require __DIR__ . '/bootstrap.php';
\Drupal::service('module_installer')->install(['locale']);
if (!\Drupal\language\Entity\ConfigurableLanguage::load('zh-hans')) {
  \Drupal\language\Entity\ConfigurableLanguage::createFromLangcode('zh-hans')->save();
}
foreach (['xinshi_credits', 'xinshi_rewards', 'xinshi_ai_billing'] as $module) {
  $report = \Drupal\locale\Gettext::fileToDatabase((object) ['langcode' => 'zh-hans',
    'uri' => '/app/docroot/modules/custom/' . $module . '/translations/' . $module . '.zh-hans.po'],
    ['overwrite_options' => ['not_customized' => TRUE, 'customized' => FALSE]]);
  check(empty($report['skips']) && empty($report['errors']), 'Drupal imports Chinese catalog: ' . $module);
}
$translator = \Drupal::translation(); $translator->reset();
foreach (['Credit cost budgets' => '积分成本预算', 'Credits to give' => '赠送积分数量',
  'No expiry' => '不过期', 'Receive first-visit credits' => '领取首次登录积分',
  'Authorize AI credit spending' => '允许使用积分消费 AI',
  'Trial credits' => '快捷体验积分', 'Save and enable trial credits' => '保存并启用体验积分'] as $source => $expected) {
  check((string) $translator->translate($source, [], ['langcode' => 'zh-hans']) === $expected, 'Chinese runtime label: ' . $source);
}
check((string) $translator->translate('@amount credits', ['@amount' => '12.345'], ['langcode' => 'zh-hans']) === '12.345 积分', 'Chinese gift review retains exact amount placeholder');
echo 'Locale checks: ' . $checks . PHP_EOL;
