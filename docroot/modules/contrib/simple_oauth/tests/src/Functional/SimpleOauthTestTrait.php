<?php

namespace Drupal\Tests\simple_oauth\Functional;

/**
 * Trait with methods needed by tests.
 */
trait SimpleOauthTestTrait {

  /**
   * The private key.
   *
   * @var string
   */
  protected $privateKey = '-----BEGIN RSA PRIVATE KEY-----
MIIEowIBAAKCAQEAvPQBbfIu1fZ9Oq/af+KAxnhMRi3BJA9qBqsXLtNUgtkf68wn
8z484j/yj9wLRP49b0K41yoExQ8KUD1D2mSh9C45GCmeBD4dM8KNMs2flSAXFgIV
twABuu+7k+75RIndJo33heADIYf6BKT1Q4nAgDi4pyfvDYjYp5iDyeLNcWiNUo/Y
Y4aKoDH36plUPA+kP1ekjCCPw7jsnV50zvCPbutvO7TZAEve/3SUIqxs0L6eG6Zv
PV2hWAqItXpXiy/WMbtkCjlwGTb60yKmjkAUNyAppSPnclH3h6HdtOzVjXfWkO9H
x3C4OAL7QET3/arRt1GDiWKwfc+Dv04lXDT0AwIDAQABAoIBAGOHgA1C6YrI2LQG
F2kPjVd93GeHCFqPSAEVNBP1O2nlJtxU4KJPIVDn8EP423LPHNszYRvtRS/ruToE
2235Xhm6E1b37QU9FrLCAxBEoY+ypJZyKLAJb9/hEYRd960zlWsOkthQ5DVQY9D4
dzzJHb4soo9iCJivgbfeLWU1c5QNXztUoHZA0zYHbVlfNCvD7cTGJpnnPdZJLd93
lZ1abkBAz6/WMavHnNNKBxPH/8hE8wLaaOZpwqce/RpcJlKM91db6OfrWnpj/Hrd
XJIKbQErrJTXOlBm27+9xoX+btg1GR9JowlUZ+BGoSmO+j0wqVWzHb+NSkbMPf10
uLyE8QkCgYEA7IDz5AYlHvafjwzWWYvU4WHl/wJ0ZbdJfBxT2QVxu37nOzGAJJck
EYIWtXPSOUE/eTJHzyBhycjkuQtt+/Rprxj+Sn4dpFpCDxKs+gNyI6A+MzdHdEYJ
YarBC2M43j8psgUiYkMpfoIgiZac/qprmgmB39u9tD+4vjZKdauw2B8CgYEAzIeS
NjXYTKaUIJYP0y1oN0eyoNfbs4h8fXRjAQUzEj1mSs0ureosTLF4lCnOKzkkVf+C
kGpTTZ6EDn7bXxsz6/2QvnubRwzIJx+kb0UkA64623vbKnL/xM7BMt/P1Avph82r
SS11XWesjOCRpYLGf+YE8rQJXdVf1Vr1CAJ2d50CgYBq4BtXAC/mPiz8yCBVdwtM
jqERDFrtXFao72Q0vnEW+dIkvcnavzJddxwsA5sMpJ+6dS5eO5P1TAOQW8noAhuA
NRs1LqjWjLMtfJMOqF/8GX4CRwjTUpMKv89dBgm85W5CNG/FV/R4ZvWtN5Lawsi9
Y259ax/fRKyHyKD9bAkOoQKBgQDJ76i6gVs4AtgJfF/PfuuAePeyuq0ei0lujDUb
0shj399ZR1ApQiXO6wJENyppnpdzmTyN3Yy1/CYiMbniIveWrtn0WBItij8r8Z/m
hHtUbveJsLXpKXXCGOjDlBqcH87I2JWfQJS6ThwdU7Q5l+7oZHDKOFtvG7bs7ksz
R0s0OQKBgH6NaykzVBPZfHrJRwdk7gfXiaLk7PfIIiiK/OyjVBbtYwaXQkUAaHEl
Oz9H1wukAZQtqf0LEGg0qIA0UuKvtvm9Iei0KpGrz21ExbPEyFhEgp5Utmw+Oon3
Rk3L4fDUGqyKyamiZNZSRnC6gZm87EWHQNFFqU0yZ6a/QKbpOB1W
-----END RSA PRIVATE KEY-----';

  /**
   * The public key.
   *
   * @var string
   */
  protected $publicKey = '-----BEGIN PUBLIC KEY-----
MIIBIjANBgkqhkiG9w0BAQEFAAOCAQ8AMIIBCgKCAQEAvPQBbfIu1fZ9Oq/af+KA
xnhMRi3BJA9qBqsXLtNUgtkf68wn8z484j/yj9wLRP49b0K41yoExQ8KUD1D2mSh
9C45GCmeBD4dM8KNMs2flSAXFgIVtwABuu+7k+75RIndJo33heADIYf6BKT1Q4nA
gDi4pyfvDYjYp5iDyeLNcWiNUo/YY4aKoDH36plUPA+kP1ekjCCPw7jsnV50zvCP
butvO7TZAEve/3SUIqxs0L6eG6ZvPV2hWAqItXpXiy/WMbtkCjlwGTb60yKmjkAU
NyAppSPnclH3h6HdtOzVjXfWkO9Hx3C4OAL7QET3/arRt1GDiWKwfc+Dv04lXDT0
AwIDAQAB
-----END PUBLIC KEY-----';

  /**
   * Set up public and private keys.
   */
  public function setUpKeys() {
    $public_key_path = 'private://public.key';
    $private_key_path = 'private://private.key';

    file_put_contents($public_key_path, $this->publicKey);
    file_put_contents($private_key_path, $this->privateKey);
    chmod($public_key_path, 0660);
    chmod($private_key_path, 0660);

    $settings = $this->config('simple_oauth.settings');
    $settings->set('public_key', $public_key_path);
    $settings->set('private_key', $private_key_path);
    $settings->save();
  }

  /**
   * Base64 url encode.
   *
   * @param string $string
   *   The string to encode.
   *
   * @return string
   *   The encoded string.
   */
  public static function base64urlencode(string $string): string {
    $base64 = base64_encode($string);
    $base64 = rtrim($base64, "=");
    return strtr($base64, '+/', '-_');
  }

}
