# Private Message

[![https://drupalcode.org/project/private_message/badges/3.0.x/pipeline.svg](https://drupalcode.org/project/private_message/badges/3.0.x/pipeline.svg)](https://drupalcode.org/project/private_message)

The Private Message module allows for private messages between users on a site.
It has been written to be fully extendable using Drupal 8 APIs.

For a full description of the module, visit the
[project page](https://www.drupal.org/project/private_message).

Submit bug reports and feature suggestions, or track changes in the
[issue queue](https://www.drupal.org/project/issues/private_message).


## Table of contents

- Requirements
- Installation
- Configuration
- Maintainers
- Contribute

## Requirements

This module requires no modules outside of Drupal core.


## Installation

Install as you would normally install a contributed Drupal module. For further
information, see
[Installing Drupal Modules](https://www.drupal.org/docs/extending-drupal/installing-drupal-modules).


## Configuration

1. Navigate to Administration > Extend and enable the Private Message
   module.
2. Navigate to Administration > People > Permissions and give the two
   permissions (use private messaging system, access user profiles)
   to the roles that should use the messaging system. Save permissions.
3. To write a private message to another user, navigate to the path
   /private-messages.

Configuring the Private Message Inbox Block

1. Navigate to Administration > Structure > Block Layout.
2. Find the Private Message Inbox Block and select the Configure button.
3. Give the block a title.
4. Select the number of threads to show in the block.
5. Select the number of threads to be loaded with ajax.
6. Select an Ajax refresh rate. This is the number of seconds between checks
    if there are any new messages. Note: setting this number to zero will
    disable refresh and the inbox will only be refreshed upon page refresh.
7. In the Visibility horizontal tab section there are three options for
    visibility: Content types, Pages and Roles.
8. The user may also want to set the block to only show on the following
    paths:
    - /private-messages
    - /private-messages/*
    This will limit the block to only show on private message thread pages.
9. Select to region for block display from the Region dropdown.
10. Save block.

Configuring the Private Message Notification Block

1. Navigate to Administration > Structure > Block Layout.
2. Find the Private Message Notification Block and select the Configure
   button.
3. Give the block a title.
4. Select an Ajax refresh rate. This is the number of seconds between checks
   if there are any new messages. Note: setting this number to zero will
   disable refresh and the inbox will only be refreshed upon page refresh.
5. In the Visibility horizontal tab section there are three options for
   visibility: Content types, Pages and Roles.
6. Select to region for block display from the Region dropdown.
7. Save block.

To Configure Private Message Threads

1. Navigate to Administration > Structure > Privates Messages > Private
   Message Threads.
2. Select the Manage fields tab and fields can be added as with any other
   entity.
3. Select the Manage display to order the items in a thread.
4. Save block.

For other use stories and configurations, please visit
`https://www.drupal.org/node/2871948`


Note: if Bartik is not the enabled theme, the Private Message Inbox block will
need to be placed in a region somewhere on the page.

## Contribute

[DDEV](https://ddev.com), a Docker-based PHP development tool for a streamlined
and unified development process, is the recommended tool for contributing to the
module. The [DDEV Drupal Contrib](https://github.com/ddev/ddev-drupal-contrib)
addon makes it easy to develop a Drupal module by offering the tools to set up
and test the module.

### Install DDEV

* Install a Docker provider by following DDEV [Docker Installation](https://ddev.readthedocs.io/en/stable/users/install/docker-installation/)
  instructions for your Operating System.
* [Install DDEV](https://ddev.readthedocs.io/en/stable/users/install/ddev-installation/),
  use the documentation that best fits your OS.
* DDEV is used mostly via CLI commands. [Configure shell completion &
  autocomplete](https://ddev.readthedocs.io/en/stable/users/install/shell-completion/)
  according to your environment.
* Configure your IDE to take advantage of the DDEV features. This is a critical
  step to be able to test and debug your module. Remember, the website runs
  inside Docker, so pay attention to these configurations:
  - [PhpStorm Setup](https://ddev.readthedocs.io/en/stable/users/install/phpstorm/)
  - [Configure](https://ddev.readthedocs.io/en/stable/users/debugging-profiling/step-debugging/)
    PhpStorm and VS Code for step debugging.
  - Profiling with [xhprof](https://ddev.readthedocs.io/en/stable/users/debugging-profiling/xhprof-profiling/),
    [Xdebug](https://ddev.readthedocs.io/en/stable/users/debugging-profiling/xdebug-profiling/)
    and [Blackfire](https://ddev.readthedocs.io/en/stable/users/debugging-profiling/blackfire-profiling/).

### Checkout the module

Normally, you check out the code form an [issue fork](https://www.drupal.org/docs/develop/git/using-gitlab-to-contribute-to-drupal/creating-issue-forks):

```shell
git clone git@git.drupal.org:issue/private_message-[issue number].git
cd private_message-[issue number]
```

### Start DDEV

Inside the cloned project run:

```shell
ddev start
```

This command will fire up the Docker containers and add all configurations.

### Install dependencies

```shell
ddev poser
```

This will install the PHP dependencies. Note that this is a replacement for
Composer _install_ command that knows how to bundle together Drupal core and the
module. Read more about this command at
https://github.com/ddev/ddev-drupal-contrib?tab=readme-ov-file#commands

```shell
ddev symlink-project
```

This symlinks the module inside `web/modules/custom`. Read more about this
command at https://github.com/ddev/ddev-drupal-contrib?tab=readme-ov-file#commands.
Note that as soon as `vendor/autoload.php` has been generated, this command runs
automatically on every `ddev start`.

This command should also be run when adding new directories or files to the root
of the module.

```shell
ddev exec "cd web/core && yarn install"
```

Install Node dependencies. This is needed for the `ddev eslint` and `ddev
stylelint` commands.

### Install Drupal

```shell
ddev install
```

This will install Drupal and will enable the module.

### Changing the Drupal core version

* Create a file `.ddev/config.local.yaml`
* In the new config file, set the desired Drupal core version. E.g.,
  ```yaml
  web_environment:
    - DRUPAL_CORE=^10.3
  ```
* Run `ddev restart`

Refer to the original documentation: [Changing the Drupal core
version](https://github.com/ddev/ddev-drupal-contrib/blob/main/README.md#changing-the-drupal-core-version)

### Run tests

* `ddev phpunit`: run PHPUnit tests
* `ddev phpcs`: run PHP coding standards checks
* `ddev phpcbf`: fix coding standards findings
* `ddev phpstan`: run PHP static analysis
* `ddev eslint`: Run ESLint on Javascript and YAML files.
* `ddev stylelint`: Run Stylelint on CSS files.

## Maintainers

- Artem Sylchuk - [artem_sylchuk](https://www.drupal.org/u/artem_sylchuk)
- Jay Friendly - [Jaypan](https://www.drupal.org/u/jaypan)
- Philippe Joulot - [phjou](https://www.drupal.org/u/phjou)
- Lucas Hedding - [heddn](https://www.drupal.org/u/heddn)
- Anmol Goel - [anmolgoyal74](https://www.drupal.org/u/anmolgoyal74)
- Eduardo Telaya - [edutrul](https://www.drupal.org/u/edutrul)
- Claudiu Cristea - [claudiu.cristea](https://www.drupal.org/u/claudiucristea)

**Supporting organizations:**

- [Jaypan](https://www.drupal.org/jaypan)
- [European Commission and European Union Institutions, Agencies and Bodies](https://www.drupal.org/european-commission-and-european-union-institutions-agencies-and-bodies)
