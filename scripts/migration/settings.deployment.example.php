<?php

/**
 * Include in the deployment settings.php after importing the verified SQL.
 * The two environment variables must point to the server's existing signing
 * pair outside docroot. Do not copy the local migration signing files online.
 * Keep the deployment database, files paths and other settings already in use.
 */
$deploymentSigning = [
  'private_key' => getenv('BUILDER_OAUTH_PRIVATE_KEY'),
  'public_key' => getenv('BUILDER_OAUTH_PUBLIC_KEY'),
];
$deploymentDocroot = realpath($app_root);
foreach ($deploymentSigning as $deploymentKey => $deploymentFilename) {
  $deploymentResolved = is_string($deploymentFilename) ? realpath($deploymentFilename) : FALSE;
  if (!$deploymentDocroot || !$deploymentResolved || !is_file($deploymentResolved)
    || !is_readable($deploymentResolved) || str_starts_with($deploymentResolved, $deploymentDocroot . '/')) {
    throw new RuntimeException('Configure readable deployment OAuth signing files outside docroot.');
  }
  $deploymentSigning[$deploymentKey] = $deploymentResolved;
}
$deploymentPrivate = openssl_pkey_get_private(file_get_contents($deploymentSigning['private_key']));
$deploymentPublic = openssl_pkey_get_public(file_get_contents($deploymentSigning['public_key']));
if (!$deploymentPrivate || !$deploymentPublic
  || openssl_pkey_get_details($deploymentPrivate)['key'] !== openssl_pkey_get_details($deploymentPublic)['key']) {
  throw new RuntimeException('Deployment OAuth signing files must be a matching pair.');
}
foreach ($deploymentSigning as $deploymentKey => $deploymentFilename) {
  $config['simple_oauth.settings'][$deploymentKey] = $deploymentFilename;
}
unset($deploymentSigning, $deploymentDocroot, $deploymentKey, $deploymentFilename, $deploymentResolved, $deploymentPrivate, $deploymentPublic);
