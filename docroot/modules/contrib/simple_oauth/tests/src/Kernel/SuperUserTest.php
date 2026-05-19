<?php

declare(strict_types=1);

namespace Drupal\Tests\simple_oauth\Kernel;

use Drupal\Core\Url;
use Drupal\entity_test\Entity\EntityTest;
use Drupal\user\Entity\Role;
use Drupal\user\RoleInterface;
use GuzzleHttp\Psr7\Query;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Test case for getting all permissions as a super user with auth code.
 *
 * @group simple_oauth
 */
class SuperUserTest extends AuthorizedRequestBase {

  /**
   * {@inheritdoc}
   */
  protected bool $usesSuperUserAccessPolicy = TRUE;

  /**
   * The test entity.
   *
   * @var \Drupal\Core\Entity\EntityInterface
   */
  protected $entity;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->grantPermissions(Role::load(RoleInterface::AUTHENTICATED_ID), [
      'grant simple_oauth codes',
    ]);

    $this->client->set('automatic_authorization', TRUE);
    $this->client->save();
    $current_user = $this->container->get('current_user');
    $current_user->setAccount($this->user);

    $this->entity = EntityTest::create();
    $this->entity->save();
  }

  /**
   * Tests the super user access policy grants all permissions.
   */
  public function testSuperUser(): void {
    // Check if we are dealing with the super user.
    $this->assertEquals('1', $this->user->id());

    $response = $this->getAuthenticatedEntityResponse();
    $this->assertEquals(200, $response->getStatusCode());

    // Turn off the super user access policy and try again.
    $this->usesSuperUserAccessPolicy = FALSE;
    $this->bootKernel();
    $this->setUp();

    $response = $this->getAuthenticatedEntityResponse();
    $this->assertEquals(403, $response->getStatusCode());
  }

  /**
   * Get the authenticated entity request response.
   *
   * @return \Symfony\Component\HttpFoundation\Response
   *   Returns the response.
   *
   * @throws \Drupal\Core\Entity\EntityMalformedException
   */
  private function getAuthenticatedEntityResponse(): Response {
    $parameters = [
      'response_type' => 'code',
      'client_id' => $this->client->getClientId(),
      'client_secret' => $this->clientSecret,
      'scope' => $this->scope,
      'redirect_uri' => $this->redirectUri,
    ];
    $authorize_url = Url::fromRoute('oauth2_token.authorize')->toString();
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
    $request = Request::create($this->entity->toUrl()->toString());
    $request->headers->add(['Authorization' => "Bearer {$access_token}"]);

    return $this->httpKernel->handle($request);
  }

}
