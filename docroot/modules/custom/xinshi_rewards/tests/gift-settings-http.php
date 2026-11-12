<?php

declare(strict_types=1);

// Share the isolated trial server, synthetic administrator and Form API helpers.
$giftSettingsPath = '/admin/config/people/credits/gifts';
$giftFormId = 'xinshi-rewards-gift-settings';
$giftJar = $fixture . '/gift-settings-operator.cookie';
check(trialHttp('GET', $giftSettingsPath)['status'] === 403, 'Gift settings reject anonymous access');
check(trialHttp('POST', '/user/login?_format=json', $giftJar, ['name' => 'reward_admin', 'pass' => 'synthetic-pass'], TRUE)['status'] === 200, 'Gift operator logs in');
check(trialHttp('GET', $giftSettingsPath, $giftJar)['status'] === 403, 'Gift permission cannot configure its own limits');
$giftSettings = trialHttp('GET', $giftSettingsPath, $jar);
check($giftSettings['status'] === 200 && str_contains($giftSettings['body'], 'Not configured'), 'Configuration administrator sees a missing rule and explicit choices');
file_put_contents($fixture . '/ui/gift-settings-empty.html', $giftSettings['body']);
$giftValues = ['cap_mode' => 'limited', 'cap' => '10', 'grant_limit_mode' => 'unlimited',
  'grant_limit' => '', 'scope' => 'chat, image', 'period_end' => gmdate('Y-m-d H:i', time() + 86400), 'expires_at' => '',
  'review' => 'Review administrator gift settings'];
$giftBefore = snapshot();
$invalidGift = trialHttp('POST', $giftSettingsPath, $jar, trialForm($giftSettings['body'], $giftFormId) + array_replace($giftValues, ['cap' => '0']));
check(!str_contains($invalidGift['body'], 'name="publish"') && snapshot() === $giftBefore, 'Zero finite limit cannot reach publication');
$giftReview = trialHttp('POST', $giftSettingsPath, $jar, trialForm($giftSettings['body'], $giftFormId) + $giftValues);
check(str_contains($giftReview['body'], 'name="publish"') && snapshot() === $giftBefore, 'Mixed limit settings reach review without any financial writes');
file_put_contents($fixture . '/ui/gift-settings-review.html', $giftReview['body']);
$giftConfirm = trialForm($giftReview['body'], $giftFormId) + ['publish' => 'Publish and enable administrator gifts'];
trialHttp('POST', $giftSettingsPath, $jar, array_replace($giftConfirm, ['form_token' => 'invalid']));
check(snapshot() === $giftBefore, 'Invalid CSRF cannot activate an administrator rule');
check(trialHttp('POST', $giftSettingsPath, $jar, $giftConfirm + ['cap' => '999'])['status'] === 303, 'Serialized settings form publishes and redirects');
\Drupal::configFactory()->reset();
$giftPoolId = \Drupal::config('xinshi_rewards.settings')->get('admin_gift_pool_id');
$giftPool = $db->select('credit_reward_pool', 'p')->fields('p')->condition('id', $giftPoolId)->execute()->fetchAssoc();
check((string) $giftPool['cap_atoms'] === '10000' && json_decode($giftPool['grant_policy_json'], TRUE)['grant_limit_atoms'] === NULL,
  'Publish ignores unreviewed values and persists a finite total with unlimited single gifts');
$giftPublished = snapshot();
trialHttp('POST', $giftSettingsPath, $jar, $giftConfirm);
check(snapshot() === $giftPublished, 'Repeated settings confirmation cannot reset the allocation');
\Drupal::configFactory()->reset();
check(\Drupal::config('xinshi_rewards.settings')->get('admin_gifts_enabled') === TRUE, 'Stale publish submission cannot trigger the default pause button');
$giftSettings = trialHttp('GET', $giftSettingsPath, $jar);
check(str_contains($giftSettings['body'], 'Current allocation') && str_contains($giftSettings['body'], 'Unlimited'), 'Saved settings show exact limits');
file_put_contents($fixture . '/ui/gift-settings-active.html', $giftSettings['body']);
$giftRecipientPath = '/admin/config/people/credits/users/' . user_load_by_name('trial_participant')->id() . '/gift';
$giftScreen = trialHttp('GET', $giftRecipientPath, $giftJar);
file_put_contents($fixture . '/ui/gift-settings-recipient.html', $giftScreen['body']);
check(str_contains($giftScreen['body'], 'name="amount"') && str_contains($giftScreen['body'], 'Unlimited'), 'Configured G12 restores the actual gift form');
$giftSubmission = trialForm($giftScreen['body'], 'xinshi-rewards-gift') + ['amount' => '0.125', 'public_reason' => 'Synthetic optional-limit gift', 'internal_note' => '', 'expiry' => '', 'op' => 'Review gift'];
$giftReview = trialHttp('POST', $giftRecipientPath, $giftJar, $giftSubmission);
check(str_contains($giftReview['body'], 'Confirm gift'), 'Unlimited single gift validates and reaches review');
trialHttp('POST', $giftRecipientPath, $giftJar, trialForm($giftReview['body'], 'xinshi-rewards-gift') + ['op' => 'Confirm gift']);
check((string) $db->select('credit_reward_pool', 'p')->fields('p', ['issued_atoms'])->condition('id', $giftPoolId)->execute()->fetchField() === '125', 'Actual gift uses the selected pool and increments the issued total');
foreach (['pause', 'resume'] as $action) {
  $giftSettings = trialHttp('GET', $giftSettingsPath, $jar);
  $giftBefore = snapshot();
  check(trialHttp('POST', $giftSettingsPath, $jar, trialForm($giftSettings['body'], $giftFormId) + [$action => $action])['status'] === 303, 'Existing rule can ' . $action);
  \Drupal::configFactory()->reset();
  check(\Drupal::config('xinshi_rewards.settings')->get('admin_gifts_enabled') === ($action === 'resume') && array_diff_key(snapshot(), ['credit_publication_guard' => TRUE]) === array_diff_key($giftBefore, ['credit_publication_guard' => TRUE]), 'Pause and resume preserve all ledger and pool data');
}
$giftTrialPage = trialHttp('GET', $path, $jar);
$giftTrialReview = trialHttp('POST', $path, $jar, trialForm($giftTrialPage['body']) + $input);
check(trialHttp('POST', $path, $jar, trialForm($giftTrialReview['body']) + ['apply' => 'Save and enable trial credits'])['status'] === 303,
  'Trial configuration can publish through its existing form after G12 activation');
\Drupal::configFactory()->reset();
check(\Drupal::config('xinshi_rewards.settings')->get('admin_gift_pool_id') === $giftPoolId
  && \Drupal::config('xinshi_rewards.settings')->get('admin_gifts_enabled'), 'Saving trial configuration preserves the separate G12 and its switch');
