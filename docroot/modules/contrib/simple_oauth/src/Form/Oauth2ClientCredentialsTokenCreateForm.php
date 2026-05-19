<?php

namespace Drupal\simple_oauth\Form;

use Drupal\consumers\Entity\ConsumerInterface;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\Query\QueryInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Password\PasswordInterface;
use Drupal\Core\Url;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\RequestOptions;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a form to create client credentials tokens.
 */
class Oauth2ClientCredentialsTokenCreateForm extends FormBase {

  /**
   * Constructs an OAuth2 client credentials token create form.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   * @param \Drupal\Core\Password\PasswordInterface $passwordChecker
   *   The password checker.
   * @param \GuzzleHttp\ClientInterface $httpClient
   *   The HTTP client.
   */
  public function __construct(
    protected EntityTypeManagerInterface $entityTypeManager,
    protected PasswordInterface $passwordChecker,
    protected ClientInterface $httpClient,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('password'),
      $container->get('http_client'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'simple_oauth_oauth2_token_create_client_credentials';
  }

  /**
   * Checks access to this form.
   *
   * This is used for the 'simple_oauth.oauth2_token.create_client_credentials'
   * route.
   *
   * @return \Drupal\Core\Access\AccessResultInterface
   *   The access result for this form.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function access(): AccessResultInterface {
    $consumersExist = (bool) $this->getConsumerQuery()->count()->execute();
    return AccessResult::allowedIf($consumersExist)
      ->cachePerPermissions()
      ->addCacheTags(['consumer_list']);
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form['client'] = [
      '#type' => 'select',
      '#title' => $this->t('Client'),
      '#description' => $this->t('Only clients that have enabled the <em>Client Credentials</em> grant type, have scopes configured and are confidential are available here.'),
      '#options' => [],
      '#required' => TRUE,
    ];

    $consumerIds = $this->getConsumerQuery()->execute();
    $consumerStorage = $this->entityTypeManager->getStorage('consumer');
    foreach ($consumerStorage->loadMultiple($consumerIds) as $consumer) {
      $form['client']['#options'][$consumer->get('client_id')->value] = $consumer->label();
    }
    natcasesort($form['client']['#options']);

    $form['client_secret'] = [
      '#type' => 'password',
      '#title' => $this->t('Client secret'),
      '#description' => $this->t('Enter the secret for the client selected above.'),
      '#required' => TRUE,
    ];

    $form['actions'] = [
      '#type' => 'actions',
    ];
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Create token'),
      '#button_type' => 'primary',
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    if ($form_state->hasValue('client') && $form_state->hasValue('client_secret')) {
      $clientId = $form_state->getValue('client');
      $clients = $this->entityTypeManager->getStorage('consumer')->loadByProperties([
        'client_id' => $clientId,
      ]);
      if ($clients) {
        $client = reset($clients);
        assert($client instanceof ConsumerInterface);
        if (!$this->passwordChecker->check($form_state->getValue('client_secret'), $client->get('secret')->value)) {
          $form_state->setErrorByName('client_secret', $this->t('Invalid secret for the %client client', [
            '%client' => $client->label(),
          ]));
        }
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $url = Url::fromRoute('oauth2_token.token', [], ['absolute' => TRUE]);
    $authorization =
      $form_state->getValue('client')
      . ':'
      . $form_state->getValue('client_secret');

    try {
      $response = $this->httpClient->request('POST', $url->toString(), [
        RequestOptions::HEADERS => [
          'Authorization' => 'Basic ' . base64_encode($authorization),
        ],
        RequestOptions::FORM_PARAMS => [
          'grant_type' => 'client_credentials',
        ],
      ]);
      $body = (string) $response->getBody();
      $payload = json_decode($body, flags: JSON_THROW_ON_ERROR);
      $jwt = ($payload->access_token ?? '');
      [, $token] = explode('.', "$jwt.", 3);
      $claim = json_decode(base64_decode($token ?? '', TRUE), flags: JSON_THROW_ON_ERROR);
      if (is_object($claim) && isset($claim->jti)) {
        $this->messenger()->addStatus($this->t('Successfully created token %token', [
          '%token' => $claim->jti,
        ]));
        $form_state->setRedirect('entity.oauth2_token.collection');
      }
      else {
        $this->messenger()->addError($this->t('The token could not be created'));
      }
    }
    catch (GuzzleException | \JsonException $exception) {
      $this->messenger()->addError($this->t('The token could not be created: %message', [
        '%message' => $exception->getMessage(),
      ]));
    }
  }

  /**
   * Builds a query for confidential client credentials consumers.
   *
   * @return \Drupal\Core\Entity\Query\QueryInterface
   *   The consumer query.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  protected function getConsumerQuery(): QueryInterface {
    $consumerStorage = $this->entityTypeManager->getStorage('consumer');
    return $consumerStorage->getQuery()
      ->accessCheck()
      ->condition('grant_types', 'client_credentials')
      ->exists('scopes')
      ->exists('user_id')
      ->condition('confidential', TRUE);
  }

}
