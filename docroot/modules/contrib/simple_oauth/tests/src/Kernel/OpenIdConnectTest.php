<?php

namespace Drupal\Tests\simple_oauth\Kernel;

use Drupal\Component\Serialization\Json;
use Drupal\Core\Language\LanguageInterface;
use Drupal\Core\Url;
use Drupal\language\Entity\ConfigurableLanguage;
use Drupal\language\Plugin\LanguageNegotiation\LanguageNegotiationUrl;
use Drupal\user\Entity\Role;
use Drupal\user\RoleInterface;
use GuzzleHttp\Psr7\Query;
use Lcobucci\JWT\Encoding\JoseEncoder;
use Lcobucci\JWT\Token\Parser;
use Lcobucci\JWT\Token\RegisteredClaims;
use Lcobucci\JWT\UnencryptedToken;
use Symfony\Component\HttpFoundation\Request;

/**
 * The Open ID Connect test.
 *
 * @group simple_oauth
 */
class OpenIdConnectTest extends AuthorizedRequestBase {

  /**
   * {@inheritDoc}
   */
  protected static $modules = ['language'];

  /**
   * {@inheritDoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->scope = 'openid';
    $_SERVER['HTTP_HOST'] = 'localhost';

    $this->installConfig(['language']);
    $this->installEntitySchema('configurable_language');

    ConfigurableLanguage::createFromLangcode('fr')->save();
    $this->config('language.negotiation')->set('url.prefixes', ['en' => 'en', 'fr' => 'fr'])->save();
    $this->config('language.types')->set('negotiation.language_interface.enabled', [
      LanguageNegotiationUrl::METHOD_ID => 1,
    ])->save();
    \Drupal::service('kernel')->rebuildContainer();

    $language_none = \Drupal::languageManager()->getLanguage(LanguageInterface::LANGCODE_NOT_APPLICABLE);
    $this->url->setOption('language', $language_none);
  }

  /**
   * Test the valid Refresh grant.
   */
  public function testOpenIdConnect(): void {
    $this->grantPermissions(Role::load(RoleInterface::AUTHENTICATED_ID), [
      'grant simple_oauth codes',
    ]);
    $this->client->set('automatic_authorization', TRUE);
    $this->client->save();
    $current_user = $this->container->get('current_user');
    $current_user->setAccount($this->user);

    $language_none = \Drupal::languageManager()->getLanguage(LanguageInterface::LANGCODE_NOT_APPLICABLE);
    $authorize_url = Url::fromRoute('oauth2_token.authorize')->setOption('language', $language_none)->toString();

    $parameters = [
      'response_type' => 'code',
      'client_id' => $this->client->getClientId(),
      'client_secret' => $this->clientSecret,
      'scope' => $this->scope,
      'redirect_uri' => $this->redirectUri,
    ];
    $request = Request::create($authorize_url, 'GET', $parameters);
    $response = $this->httpKernel->handle($request);
    $parsed_url = parse_url($response->headers->get('location'));
    $parsed_query = Query::parse($parsed_url['query']);
    $code = $parsed_query['code'];
    $parameters = [
      'grant_type' => 'authorization_code',
      'client_id' => $this->client->getClientId(),
      'client_secret' => $this->clientSecret,
      'code' => $code,
      'scope' => $this->scope,
      'redirect_uri' => $this->redirectUri,
    ];
    $request = Request::create($this->url->toString(), 'POST', $parameters);
    $response = $this->httpKernel->handle($request);
    $parsed_response = $this->assertValidTokenResponse($response, TRUE);
    $access_token = $parsed_response['access_token'];

    $parser = new Parser(new JoseEncoder());
    $token = $parser->parse($parsed_response['id_token']);
    assert($token instanceof UnencryptedToken);
    $this->assertEquals('https://localhost/', $token->claims()->get(RegisteredClaims::ISSUER));

    $user_info_url = Url::fromRoute('simple_oauth.userinfo')->setOption('language', $language_none)->toString();
    $parameters = [
      'response_type' => 'code',
      'client_id' => $this->client->getClientId(),
      'client_secret' => $this->clientSecret,
      'scope' => $this->scope,
      'redirect_uri' => $this->redirectUri,
    ];
    $request = Request::create($user_info_url, 'GET', $parameters);
    $request->headers->add(['Authorization' => "Bearer {$access_token}"]);
    $response = $this->httpKernel->handle($request);
    $parsed_response = Json::decode((string) $response->getContent());
    $this->assertSame($parsed_response['email'], $this->user->getEmail());
  }

}
