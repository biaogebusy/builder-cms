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
- Custom Queue Backends
- Maintainers


## Requirements

This module requires no modules outside of Drupal core.


## Installation

Install as you would normally install a contributed Drupal module. For further
information, see
[Installing Drupal Modules](https://www.drupal.org/docs/extending-drupal/installing-drupal-modules).


## Configuration

There are no configuration provided.


## Custom Queue Backends

Queue UI uses a plugin system to support different queue backends. By default, it
supports the core `DatabaseQueue`.

To support a custom queue backend (e.g., Redis, SQS), you need to implement a
`QueueUI` plugin in your module.

1. Create a plugin class in `src/Plugin/QueueUI/YourQueueBackend.php`.
2. Use the `@QueueUI` annotation.
3. The `class_name` property in the annotation must match either the short class
   name or the full class name of your `QueueInterface` implementation.

Example:

```php
/**
 * @QueueUI(
 *   id = "my_custom_queue",
 *   class_name = "\Drupal\my_module\Queue\MyCustomQueue"
 * )
 */
class MyCustomQueue extends QueueUIBase {
  // Implement required methods from QueueUIInterface.
}
```


## Maintainers

- Oleh Vehera - [voleger](https://www.drupal.org/u/voleger)
- Oleksandr Dekhteruk - [pifagor](https://www.drupal.org/u/pifagor)

**Supporting organization:**

- [Nascom](https://www.drupal.org/nascom)
- [Golems G.A.B.B.](https://www.drupal.org/golems-gabb)
