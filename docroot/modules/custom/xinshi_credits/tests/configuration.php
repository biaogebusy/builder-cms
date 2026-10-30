<?php

declare(strict_types=1);

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\user\Entity\User;
use Symfony\Component\HttpFoundation\Request;

require __DIR__ . '/bootstrap.php';

$configs = \Drupal::configFactory();
$settings = $configs->getEditable('xinshi_credits.settings');
$original = $settings->getRawData();
$rewardsInstalled = \Drupal::moduleHandler()->moduleExists('xinshi_rewards');
$rewards = $rewardsInstalled ? $configs->getEditable('xinshi_rewards.settings') : NULL;
$originalRewards = $rewards?->getRawData();
$stack = \Drupal::service('request_stack');
$entity = User::load(1);

// Exercise the registered optional hook without installing the page-rendering module.
$project = function (mixed $content = '/core/base') use ($stack, $entity): array {
  $stack->push(Request::create('/api/v3/landingPage', 'GET', ['content' => $content]));
  try {
    $data = ['theme' => ['fixture'], 'credits' => ['enabled' => TRUE, 'daily_enabled' => TRUE]];
    $metadata = new CacheableMetadata();
    \Drupal::moduleHandler()->alter('xinshi_api_data', $data, $entity, $metadata);
    return [$data, $metadata];
  }
  finally {
    $stack->pop();
  }
};

try {
  $settings->set('grants_enabled', FALSE)->set('spending_enabled', FALSE)->save();
  $rewards?->set('daily_enabled', TRUE)->set('welcome_enabled', FALSE)->save();
  [$data, $metadata] = $project();
  check($data['credits'] === ['enabled' => FALSE, 'daily_enabled' => FALSE, 'welcome_enabled' => FALSE], 'Closed site overrides authored credit flags');
  check($data['theme'] === ['fixture'], 'Credit projection preserves other core configuration');
  check(in_array('config:xinshi_credits.settings', $metadata->getCacheTags(), TRUE), 'Core configuration depends on credit switches');
  check(in_array('config:core.extension', $metadata->getCacheTags(), TRUE), 'Core configuration depends on optional module installation');
  check(in_array('config:xinshi_rewards.settings', $metadata->getCacheTags(), TRUE) === $rewardsInstalled, 'Reward cache dependency follows module availability');
  \Drupal::cache()->set('credits-feature-projection', $data, -1, $metadata->getCacheTags());
  check((bool) \Drupal::cache()->get('credits-feature-projection'), 'Feature projection can be cached with dependencies');

  $settings->set('grants_enabled', TRUE)->save();
  check(!\Drupal::cache()->get('credits-feature-projection'), 'Changing a credit switch invalidates the cached projection');
  [$data] = $project();
  check($data['credits'] === ['enabled' => TRUE, 'daily_enabled' => $rewardsInstalled, 'welcome_enabled' => FALSE], 'Gift admission enables credits and only installed daily rewards');
  $settings->set('grants_enabled', FALSE)->set('spending_enabled', TRUE)->save();
  [$data] = $project();
  check($data['credits'] === ['enabled' => TRUE, 'daily_enabled' => FALSE, 'welcome_enabled' => FALSE], 'Spending alone never enables daily rewards');
  $settings->set('grants_enabled', TRUE)->save();
  $rewards?->set('daily_enabled', FALSE)->save();
  [$data] = $project();
  check($data['credits'] === ['enabled' => TRUE, 'daily_enabled' => FALSE, 'welcome_enabled' => FALSE], 'Daily pause keeps enabled credit reads available');

  if ($rewards) {
    [, $metadata] = $project();
    \Drupal::cache()->set('credits-feature-projection', $data, -1, $metadata->getCacheTags());
    $rewards->set('daily_enabled', TRUE)->save();
    check(!\Drupal::cache()->get('credits-feature-projection'), 'Changing a reward switch invalidates the cached projection');
    $rewards->set('welcome_enabled', TRUE)->save();
    [$data] = $project();
    check($data['credits']['welcome_enabled'] === TRUE, 'Welcome projects only its explicit enabled switch');
    $settings->set('grants_enabled', FALSE)->save();
    [$data] = $project();
    check($data['credits']['welcome_enabled'] === FALSE, 'Grant pause hides the welcome capability');
  }
  foreach (['/home', ['/core/base'], NULL] as $content) {
    [, $metadata] = $project($content);
    check($metadata->getCacheTags() === [], 'Unrelated or malformed content does not receive credit configuration');
  }
  check(!\Drupal::moduleHandler()->moduleExists('xinshi_api'), 'Credit module retains no required page API dependency');
}
finally {
  $settings->setData($original)->save();
  if ($rewards) $rewards->setData($originalRewards)->save();
}
echo 'Credit configuration checks: ' . $checks . PHP_EOL;
