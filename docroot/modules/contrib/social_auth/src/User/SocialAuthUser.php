<?php

namespace Drupal\social_auth\User;

/**
 * User data used for authentication with Drupal.
 */
class SocialAuthUser implements SocialAuthUserInterface {

  /**
   * First name.
   *
   * @var string|null
   */
  protected ?string $firstName = NULL;

  /**
   * Last name.
   *
   * @var string|null
   */
  protected ?string $lastName = NULL;

  /**
   * Used to create the username in Drupal: first + last most of the time.
   *
   * @var string
   */
  protected string $name;

  /**
   * Email address.
   *
   * @var string|null
   */
  protected ?string $email = NULL;

  /**
   * ID in provider.
   *
   * @var string
   */
  protected string $providerUserID;

  /**
   * Token used for authentication in provider.
   *
   * @var string|mixed
   */
  protected mixed $token;

  /**
   * URL to get profile picture.
   *
   * @var string|null
   */
  protected ?string $pictureUrl = NULL;

  /**
   * Profile picture file.
   *
   * @var string|int|null
   */
  protected mixed $picture = NULL;

  /**
   * User's extra data. Store in additional_data field in social_auth entity.
   *
   * @var array|null
   */
  protected ?array $additionalData;

  /**
   * Other data added through external modules (e.g. event subscribers)
   *
   * @var array
   */
  protected array $customData = [];

  /**
   * User constructor.
   *
   * @param string $name
   *   The user's name.
   * @param string $provider_user_id
   *   The unique ID in provider.
   * @param string $token
   *   The access token for making API calls.
   * @param string|null $email
   *   The user's email address.
   * @param string|null $picture_url
   *   The user's picture.
   * @param array|null $additional_data
   *   The additional user data to be stored in database.
   */
  public function __construct(
    string $name,
    string $provider_user_id,
    string $token,
    ?string $email = NULL,
    ?string $picture_url = NULL,
    ?array $additional_data = NULL,
  ) {
    $this->name = $name;
    $this->providerUserID = $provider_user_id;
    $this->token = $token;
    $this->email = $email;
    $this->pictureUrl = $picture_url;
    $this->additionalData = $additional_data;
  }

  /**
   * {@inheritdoc}
   */
  public function getFirstName(): ?string {
    return $this->firstName;
  }

  /**
   * {@inheritdoc}
   */
  public function setFirstName(?string $first_name): void {
    $this->firstName = $first_name;
  }

  /**
   * {@inheritdoc}
   */
  public function getLastName(): ?string {
    return $this->lastName;
  }

  /**
   * {@inheritdoc}
   */
  public function setLastName(?string $last_name): void {
    $this->lastName = $last_name;
  }

  /**
   * {@inheritdoc}
   */
  public function getName(): string {
    return $this->name;
  }

  /**
   * {@inheritdoc}
   */
  public function setName(string $name): void {
    $this->name = $name;
  }

  /**
   * {@inheritdoc}
   */
  public function getEmail(): ?string {
    return $this->email;
  }

  /**
   * {@inheritdoc}
   */
  public function setEmail(?string $email): void {
    $this->email = $email;
  }

  /**
   * {@inheritdoc}
   */
  public function getProviderId(): string {
    return $this->providerUserID;
  }

  /**
   * {@inheritdoc}
   */
  public function setProviderId(string $provider_id): void {
    $this->providerUserID = $provider_id;
  }

  /**
   * {@inheritdoc}
   */
  public function getToken(): string {
    return $this->token;
  }

  /**
   * {@inheritdoc}
   */
  public function setToken(string $token): void {
    $this->token = $token;
  }

  /**
   * {@inheritdoc}
   */
  public function getPictureUrl(): ?string {
    return $this->pictureUrl;
  }

  /**
   * {@inheritdoc}
   */
  public function setPictureUrl(?string $picture_url): void {
    $this->pictureUrl = $picture_url;
  }

  /**
   * {@inheritdoc}
   */
  public function getPicture(): mixed {
    return $this->picture;
  }

  /**
   * {@inheritdoc}
   */
  public function setPicture(mixed $file_id) {
    $this->picture = $file_id;
  }

  /**
   * {@inheritdoc}
   */
  public function getAdditionalData(): ?array {
    return $this->additionalData;
  }

  /**
   * {@inheritdoc}
   */
  public function setAdditionalData(?array $additional_data): void {
    $this->additionalData = $additional_data;
  }

  /**
   * {@inheritdoc}
   */
  public function addData(string $key, mixed $value): static {
    $this->customData[$key] = $value;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getData($key): mixed {
    return $this->customData[$key] ?? NULL;
  }

}
