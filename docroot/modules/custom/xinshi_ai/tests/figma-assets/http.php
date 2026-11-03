<?php

/**
 * @file
 * Exercise the imported production route using real scoped OAuth requests.
 */

use Drupal\consumers\Entity\Consumer;
use Drupal\simple_oauth\Entity\Oauth2Scope;
use League\OAuth2\Server\CryptKey;

Oauth2Scope::create(['name' => 'figma_reader', 'granularity_id' => 'role',
  'granularity_configuration' => ['role' => 'figma_reader'],
  'grant_types' => ['authorization_code' => ['status' => TRUE]]])->save();
Consumer::create(['label' => 'Figma tests', 'client_id' => 'figma-test'])->save();
$key = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
openssl_pkey_export($key, $private);
file_put_contents('/tmp/chat-test/private.pem', $private);
file_put_contents('/tmp/chat-test/public.pem', openssl_pkey_get_details($key)['key']);
chmod('/tmp/chat-test/private.pem', 0600);
\Drupal::configFactory()->getEditable('simple_oauth.settings')->set('private_key', '/tmp/chat-test/private.pem')
  ->set('public_key', '/tmp/chat-test/public.pem')->save();
$repo = \Drupal::service('simple_oauth.repositories.access_token');
$client = \Drupal::service('simple_oauth.repositories.client')->getClientEntity('figma-test');
$scope = \Drupal::service('simple_oauth.repositories.scope')->getScopeEntityByIdentifier('figma_reader');
$tokens = [];
foreach (['reader', 'limited'] as $name) {
  $token = $repo->getNewToken($client, $name === 'reader' ? [$scope] : [], (string) $users['bob']->id());
  $token->setIdentifier(bin2hex(random_bytes(24)));
  $token->setExpiryDateTime(new DateTimeImmutable('+1 hour'));
  $token->setPrivateKey(new CryptKey('/tmp/chat-test/private.pem'));
  $repo->persistNewAccessToken($token);
  $tokens[$name] = $token->convertToJWT()->toString();
}
\Drupal::service('router.builder')->rebuild();
$server = proc_open([PHP_BINARY, '-S', '127.0.0.1:8080', __DIR__ . '/integration.php'],
  [0 => ['file', '/dev/null', 'r'], 1 => ['file', '/tmp/chat-test/http.log', 'a'],
    2 => ['file', '/tmp/chat-test/http.log', 'a']], $pipes);
function http(?string $token, string $method = 'PUT', ?string $path = NULL): array {
  $binary = png(0x112233);
  $path ??= '/api/v3/ai/figma/assets/11111111-1111-4111-8111-111111111111/' . hash('sha256', $binary);
  $curl = curl_init('http://127.0.0.1:8080' . $path);
  $headers = ['Accept: application/json', 'Content-Type: application/json'];
  if ($token) { $headers[] = 'Authorization: Bearer ' . $token; }
  $cache = '';
  curl_setopt_array($curl, [CURLOPT_HTTPHEADER => $headers, CURLOPT_CUSTOMREQUEST => $method,
    CURLOPT_RETURNTRANSFER => TRUE, CURLOPT_TIMEOUT => 20, CURLOPT_FOLLOWLOCATION => FALSE,
    CURLOPT_POSTFIELDS => json_encode(['filename' => 'figma.png', 'mimeType' => 'image/png', 'data' => base64_encode($binary)]),
    CURLOPT_HEADERFUNCTION => static function ($curl, string $line) use (&$cache): int {
      if (str_starts_with(strtolower($line), 'cache-control:')) { $cache = strtolower($line); }
      return strlen($line);
    }]);
  $body = curl_exec($curl);
  $status = curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
  curl_close($curl);
  if ($body === FALSE || $status >= 500) {
    throw new RuntimeException('HTTP failure: ' . $body . file_get_contents('/tmp/chat-test/http.log'));
  }
  return ['status' => $status, 'data' => json_decode($body, TRUE), 'cache' => $cache];
}
try {
  for ($attempt = 0; $attempt < 60; $attempt++) {
    if ($socket = @fsockopen('127.0.0.1', 8080, $errno, $error, 0.1)) { fclose($socket); break; }
    usleep(50000);
  }
  $before = counts();
  $result = http($tokens['reader']);
  check($result['status'] === 200 && counts() === [$before[0] + 1, $before[1] + 1], 'Real scoped OAuth creates the import');
  check(str_contains($result['cache'], 'no-store'), 'Real HTTP success cannot be cached');
  check(http($tokens['reader'])['data'] === $result['data'] && counts() === [$before[0] + 1, $before[1] + 1],
    'Repeated HTTP PUT reuses the same receipt and entities');
  check(http($tokens['limited'])['status'] === 409, 'Reduced OAuth scope cannot reuse a receipt from the same account');
  check(in_array(http(NULL)['status'], [401, 403], TRUE), 'Anonymous HTTP upload is rejected');
  check(http($tokens['reader'], 'POST')['status'] === 405, 'The receipt route rejects non-idempotent POST');
  check(http($tokens['reader'], 'POST', '/api/v3/ai/figma/assets')['status'] === 404, 'The retired upload route cannot create an untracked asset');
}
finally {
  proc_terminate($server);
  proc_close($server);
}
