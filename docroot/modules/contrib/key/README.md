# Key <!-- omit in toc -->

Key provides the ability to improve Drupal security by managing sensitive keys
(such as API and encryption keys). It gives site administrators the ability to
define how and where keys are stored, which allows the option of a high level
of security and allows sites to meet regulatory or compliance requirements.

Examples of the types of keys that could be managed with Key are:

- An API key for connecting to an external service, such as PayPal, MailChimp,
  Authorize.net, UPS, an SMTP mail server, or Amazon Web Services
- A key used for encrypting data using the
  [encrypt module](https://www.drupal.org/project/encrypt)

For a full description of the module, visit the
[project page](https://www.drupal.org/project/key).

Submit bug reports and feature suggestions, or track changes in the
[issue queue](https://www.drupal.org/project/issues/key).


## Table of Contents

- [Table of Contents](#table-of-contents)
- [Requirements](#requirements)
- [Installation](#installation)
- [Configuration](#configuration)
    - [Key type](#key-type)
    - [Key provider](#key-provider)
    - [Key input](#key-input)
- [Using a key](#using-a-key)
    - [Get all key entities](#get-all-key-entities)
    - [Get a single key entity](#get-a-single-key-entity)
    - [Get a key value](#get-a-key-value)
    - [Get multiple values from a key](#get-multiple-values-from-a-key)
- [Maintainers](#maintainers)


## Requirements

No additional modules or libraries are required, but other modules can
extend the functionality of, or integrate with, Key.


## Installation

Install as you would normally install a contributed Drupal module. For further
information, see
[Installing Drupal Modules](https://www.drupal.org/docs/extending-drupal/installing-drupal-modules).


## Configuration

Key provides an administration page where users with the "administer
keys" permission can add, edit, and delete keys.


### Key type

A key type can be selected for a key in order to indicate the purpose
of the key. The following key types are included with Key:

- **Authentication:** A generic key type to use for a password or API
  key that does not belong to any other defined key type. This is the
  default.
- **Encryption:** Can be used for encrypting and decrypting data. This
  key type has a field for selecting a key size, which is used to
  validate the size of the key value.

Key types are native Drupal plugins, so new types can be defined easily.


### Key provider

A key provider is the means by which the key value is stored and/or
provided when needed. The following key providers are included with
Key:

- **Configuration:** Stores the key in Drupal configuration settings.
  The key value can be set, edited, and viewed through the administrative
  interface, making it useful during site development. However, for
  better security on production websites, keys should not be stored in
  configuration. Keys using the Configuration provider are not obscured
  when editing, making it even more important that this provider not be
  used in a production environment.
- **File:** Stores the key in a file, which can be anywhere in the file
  system, as long as it's readable by the user that runs the web server.
  Storing the key in a file outside of the web root is generally more
  secure than storing it in the database.

- **Environment:** Allows the key to be stored in an environmental
  variable.

All three provider plugins support storing encryption keys with Base64
encoding.

Key providers are native Drupal plugins, so new providers can be defined
easily.


### Key input

When adding or editing a key, if the selected key provider accepts a
key value, a key input is automatically selected, as defined by the key
type, in order to submit a key value. The following key inputs are
included with Key:

- **None:** This input is used by default when the selected key
  provider does not accept a key value. The File key provider uses this
  input.
- **Text Field:** This input provides a basic text field for submitting
  a key value. The Configuration key provider uses this input.
- **Textarea Field:** This input is the same as the text field input,
  except it uses a textarea HTML element, so it's useful for longer keys,
  such as SSH keys.

The Text Field and Textarea Field input plugins support the submission
of keys that are Base64-encoded.

Key inputs are native Drupal plugins, so new inputs can be defined easily.


## Using a key

Creating a key will have no effect unless another module makes use of
it. That integration would typically present itself to the end user in
the form of a field that lists available keys and allows the user to
choose one. This could appear, for instance, on the integrating
module's configuration page.

Modules can add a key field to a form using the key_select API element,
which behaves like a select element, but is populated with available
keys as options.

```php
$form['secret_key'] = [
  '#type' => 'key_select',
  '#title' => $this->t('Secret key'),
];
```

There are a couple of additional properties that can be used:

- `#key_filters` An array of filters to apply to the list of keys.
Filtering can be performed on any combination of key type, key provider,
key type group, or storage method. Examples:
  - `#key_filters = ['type' => 'mailchimp']` This would only display
  MailChimp keys.
  - `#key_filters = ['provider' => 'file']` This would only display keys
  that use the File key provider.
  - `#key_filters = ['type' => 'mailchimp', 'provider' => 'file']`
  This would only display MailChimp keys that use the File key provider.
  - `#key_filters = ['type_group' => 'encryption']` This would only display
  keys that are of a key type that belongs to the 'encryption' group.
  - `#key_filters = ['tags' => ['file', 'private']]` This would only display
    keys whose definitions are tagged with `file` and `private` tags.
- `#key_description` This is a boolean value that determines if information
  about keys is added to the element's description. It is TRUE by default
  and it prepends the description with the following text (with a link to
  the add key form), which can be disabled by setting #key_description to
  FALSE:

  Choose an available key. If the desired key is not listed, create a new key.

Modules can retrieve information about keys or a specific key value by making
a call to the Key Manager service. It is best practice to
[inject the service](https://www.drupal.org/node/2133171)
into your own service, [form](https://www.drupal.org/node/2203931), or
[controller](https://api.drupal.org/api/drupal/core!lib!Drupal!Core!DependencyInjection!ContainerInjectionInterface.php/interface/ContainerInjectionInterface/8).

The following examples assume the use of the `\Drupal` object for brevity,
but the examples can be extrapolated to fit the use case of your module.


### Get all key entities

`Drupal::service('key.repository')->getKeys()`


### Get a single key entity

`Drupal::service('key.repository')->getKey($key_id)`


### Get a key value

`Drupal::service('key.repository')->getKey($key_id)->getKeyValue()`


### Get multiple values from a key

`Drupal::service('key.repository')->getKey($key_id)->getKeyValues()`

This will return an array of values. If the key does not support multiple
values, the array will contain only one element.


## Maintainers

- Rick Hawkins - [rlhawk](https://www.drupal.org/u/rlhawk)
- Chris Teitzel - [Cellar Door](https://www.drupal.org/u/cellar-door)
- Patrick Teglia - [crashtest_](https://www.drupal.org/u/crashtest_)
- Adam Bergstein - [nerdstein](https://www.drupal.org/u/nerdstein)
