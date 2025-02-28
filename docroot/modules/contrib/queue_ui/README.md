# Queue UI

The Queue UI module provides a user interface to viewing and managing Drupal
queues created via the Queue API which began in Drupal 7.

QueueUI's dev releases will be packaged whilst D8 evolves. The current port
works with all existing base functionality. However, the dev version needs to be
extended to non-core classes of the Queue Inspection, which is going to need
converting to the plugin system before it can be extended by other contribute
modules.

Features:

- View queues and number of items
- Developers can define meta info about queues they create and process
- Process queue with Batch API
- Process queue during cron
- Remove leases
- Delete queue

For a full description of the module, visit the
[project page](https://www.drupal.org/project/queue_ui).

Submit bug reports and feature suggestions, or track changes in the
[issue queue](https://www.drupal.org/project/issues/queue_ui).


## Table of contents

- Requirements
- Installation
- Configuration
- Maintainers


## Requirements

This module requires no modules outside of Drupal core.


## Installation

Install as you would normally install a contributed Drupal module. For further
information, see
[Installing Drupal Modules](https://www.drupal.org/docs/extending-drupal/installing-drupal-modules).


## Configuration

There are no configuration provided.


## Maintainers

- Oleh Vehera - [voleger](https://www.drupal.org/u/voleger)
- Oleksandr Dekhteruk - [pifagor](https://www.drupal.org/u/pifagor)
- DrupalSpoons Bot - [drupalspoons](https://www.drupal.org/u/drupalspoons)

**Supporting organization:**

- [Nascom](https://www.drupal.org/nascom)
