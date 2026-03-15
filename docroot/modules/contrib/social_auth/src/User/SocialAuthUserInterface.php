<?php

namespace Drupal\social_auth\User;

/**
 * User data used for authentication with Drupal.
 */
interface SocialAuthUserInterface {

  /**
   * Gets the user's first name.
   *
   * @return string|null
   *   The user's first name.
   */
  public function getFirstName(): ?string;

  /**
   * Sets the user's first name.
   *
   * @param string|null $first_name
   *   The user's first name.
   */
  public function setFirstName(?string $first_name): void;

  /**
   * Gets the user's last name.
   *
   * @return string|null
   *   The user's last name.
   */
  public function getLastName(): ?string;

  /**
   * Sets the user's last name.
   *
   * @param string|null $last_name
   *   The user's last name.
   */
  public function setLastName(?string $last_name): void;

  /**
   * Gets the user's name.
   *
   * @return string
   *   The user's name.
   */
  public function getName(): string;

  /**
   * Sets the user's name.
   *
   * @param string $name
   *   The user's name.
   */
  public function setName(string $name): void;

  /**
   * Gets the user's email.
   *
   * @return string|null
   *   The user's email.
   */
  public function getEmail(): ?string;

  /**
   * Sets the user's email.
   *
   * @param string|null $email
   *   The user's email.
   */
  public function setEmail(?string $email): void;

  /**
   * Gets the user's id in provider.
   *
   * @return string
   *   The user's id in provider.
   */
  public function getProviderId(): string;

  /**
   * Sets the user's id in provider.
   *
   * @param string $provider_id
   *   The user's id in provider.
   */
  public function setProviderId(string $provider_id);

  /**
   * Gets the user's token.
   *
   * @return string
   *   The user's token.
   */
  public function getToken(): string;

  /**
   * Sets the user's token.
   *
   * @param string $token
   *   The user's token.
   */
  public function setToken(string $token): void;

  /**
   * Gets the user's picture URL.
   *
   * @return string|null
   *   The user's picture URL.
   */
  public function getPictureUrl(): ?string;

  /**
   * Sets the user's picture URL.
   *
   * @param string|null $picture_url
   *   The user's picture URL.
   */
  public function setPictureUrl(?string $picture_url): void;

  /**
   * Gets the user's picture ID.
   *
   * @return mixed
   *   The user's picture ID.
   */
  public function getPicture(): mixed;

  /**
   * Sets the user's picture ID.
   *
   * @param mixed $file_id
   *   The user's picture ID.
   */
  public function setPicture(mixed $file_id);

  /**
   * Set the user's additional data.
   *
   * @return array|null
   *   The user's additional data.
   */
  public function getAdditionalData(): ?array;

  /**
   * Sets the user's additional data.
   *
   * @param array|null $additional_data
   *   The user's additional data.
   */
  public function setAdditionalData(?array $additional_data);

  /**
   * Adds a new key-value pair in customData.
   *
   * @param string $key
   *   The key identifying the data.
   * @param mixed $value
   *   The value associated to the key.
   *
   * @return static
   *   The User instance.
   */
  public function addData(string $key, mixed $value): static;

  /**
   * Gets a value from customData.
   *
   * @param string $key
   *   The key identifying the data.
   *
   * @return mixed
   *   The custom data or null if not found.
   */
  public function getData(string $key): mixed;

}
