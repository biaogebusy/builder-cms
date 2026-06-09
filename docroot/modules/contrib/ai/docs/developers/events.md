# Events

There are three important events that are currently available in the AI module. One that is triggered before the request is being sent, one that is triggered before the response is given from the AI Provider plugin and one that is triggered after a streaming response is done.

These three make it possible to change prompts, change responses, log, find bugs etc.

With the PreGenerateResponseEvent that is triggered before the request is sent, you can choose to create an exception to make sure that the request never happens or there is a method called setForcedOutputObject, where you can give an OutputInterface and it will answer with this one without doing the request.

There is also an event that is triggered when an AI provider gets uninstalled/disabled. This is good for 3rd party modules that might rely on a specific provider existing due to 3rd party provider settings.

## Example #1: Pre request.

You have a module where you want to make sure to filter out certain words that are important for your IP, so they don't get sent to OpenAI when using the AI API Explorer.

You do this by creating an event subscriber, something like this.

```php
<?php

namespace Drupal\ai_example\EventSubscriber;

use Drupal\ai\Event\PreGenerateResponseEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Check for IP.
 */
class IpCheckSubscriber implements EventSubscriberInterface {

  /**
   * {@inheritdoc}
   *
   * @return array
   *   The pre generate response event.
   */
  public static function getSubscribedEvents(): array {
    return [
      PreGenerateResponseEvent::EVENT_NAME => 'checkBeforeRequest',
    ];
  }

  /**
   * Change IP before sending.
   *
   * @param \Drupal\ai\Event\PreGenerateResponseEvent $event
   *   The event to check.
   */
  public function checkBeforeRequest(PreGenerateResponseEvent $event) {
    // Only do AI API Explorer.
    if (in_array('ai_api_explorer', $event->getTags()) && $event->getOperationType() == 'chat') {
      // Fictional intellectual property.
      $ip = [
        'secret_ip',
        'my_secret_word',
        'do_not_send_this',
      ];
      $messages = $event->getInput()->getMessages();
      foreach ($messages as $key => &$message) {
        // Remove all ips.
        $messages[$key]->setText(str_replace($ip, 'censored', $message->getText()));
      }
    }
  }

}

```

## Example #2: Post request.

You have a module where you want to log how many images you created in total.

You do this by creating an event subscriber, something like this.

```php
<?php

namespace Drupal\ai_logging\EventSubscriber;

use Drupal\ai\Event\PostGenerateResponseEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Counts images.
 */
class CountImagesSubscriber implements EventSubscriberInterface {

  /**
   * {@inheritdoc}
   *
   * @return array
   *   The post generate response event.
   */
  public static function getSubscribedEvents(): array {
    return [
      PostGenerateResponseEvent::EVENT_NAME => 'countImages',
    ];
  }

  /**
   * Count the images.
   *
   * @param \Drupal\ai\Event\PostGenerateResponseEvent $event
   *   The event to count.
   */
  public function countImages(PostGenerateResponseEvent $event) {
    // Only do AI API Explorer.
    if ($event->getOperationType() == 'text-to-image') {
      $pseudoCounter->countOneMore();
    }
  }

}

```


## Example #3: Stream finished.

You have a module where you want to log the chat messages, but they happen
to be streaming responses so you can't see it on the post request event.

You do this by creating an event subscriber, something like this.

```php
<?php

namespace Drupal\ai_logging\EventSubscriber;

use Drupal\ai\Event\PostGenerateResponseEvent;
use Drupal\ai\Event\PostStreamingResponseEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Counts images.
 */
class CountImagesSubscriber implements EventSubscriberInterface {

  /**
   * The state in between.
   *
   * @var array
   */
  private $state = [];

  /**
   * {@inheritdoc}
   *
   * @return array
   *   The post generate response event.
   */
  public static function getSubscribedEvents(): array {
    return [
      PostGenerateResponseEvent::EVENT_NAME => 'storeInput',
      PostStreamingResponseEvent::EVENT_NAME => 'storeOutput',
    ];
  }

  /**
   * Store the input first.
   *
   * @param \Drupal\ai\Event\PostGenerateResponseEvent $event
   *   The event to store input.
   */
  public function storeInput(PostGenerateResponseEvent $event) {
    // Only do AI API Explorer.
    if ($event->getOperationType() == 'chat') {
      // Store with the unique id of the events happening
      $this->state[$event->getRequestThreadId()]['input'] = $event->getInput();
    }
  }

  /**
   * Connect the output.
   *
   * @param \Drupal\ai\Event\PostStreamingResponseEvent $event
   *   The event to store output.
   */
  public function storeOutput(PreGenerateResponseEvent $event) {
      // Store the output.
      $this->state[$event->getRequestThreadId()]['output'] = $event->getOutput();
  }

}

```

## Example #4: Provider Disabled.

You have a third party module that is dependent on a provider called dropai.

You need to make changes to your settings when that provider is being uninstalled.


```php
<?php

namespace Drupal\ai_logging\EventSubscriber;

use Drupal\ai\Event\ProviderDisabledEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Sets new provider when picked provider is disabled.
 */
class SetNewProvider implements EventSubscriberInterface {

  /**
   * {@inheritdoc}
   *
   * @return array
   *   The ProviderDisabledEvent event.
   */
  public static function getSubscribedEvents(): array {
    return [
      ProviderDisabledEvent::EVENT_NAME => 'countImages',
    ];
  }

  /**
   * Change setting when the provider you have is disabled..
   *
   * @param \Drupal\ai\Event\ProviderDisabledEvent $event
   *   The event disabled.
   */
  public function countImages(ProviderDisabledEvent $event) {
    if ($event->getProviderId() == 'dropai') {
      // Whatever custom code you need here, just example code.
      $myconfig->provider = 'default';
    }
  }

}

```

## Example #5: Provider exception — rewrite the message or failover.

When a provider throws from inside `ProviderProxy::wrapperCall()` (quota exceeded, rate limit, unsafe prompt, bad response, …), the AI module dispatches an `AiExceptionEvent` before it rethrows. A subscriber can:

- Call `$event->setMessage(...)` to rewrite the user-facing message. The exception class is preserved, so existing `catch (AiQuotaException $e)` blocks keep working.
- Call `$event->setForcedOutputObject($output)` with any `\Drupal\ai\OperationType\OutputInterface` to recover gracefully. The proxy returns that output to the caller instead of throwing — useful for failing over to a backup provider, returning a cached response, or showing a canned apology.

`AiExceptionEvent` extends `AiProviderRequestBaseEvent`, so it exposes the full request context via the same getters as `PreGenerateResponseEvent` / `PostGenerateResponseEvent`: `getProviderId()`, `getOperationType()`, `getModelId()`, `getInput()`, `getConfiguration()`, `getTags()`, `getRequestThreadId()`. The original exception is available as the public readonly `$event->exception`.

```php
<?php

namespace Drupal\my_failover\EventSubscriber;

use Drupal\ai\AiProviderPluginManager;
use Drupal\ai\Event\AiExceptionEvent;
use Drupal\ai\Exception\AiQuotaException;
use Drupal\ai\Exception\AiRateLimitException;
use Drupal\ai\OperationType\Chat\ChatInput;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Falls back to a backup provider when the primary one is out of quota.
 */
final class FailoverSubscriber implements EventSubscriberInterface {

  public function __construct(
    private readonly AiProviderPluginManager $aiProvider,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [AiExceptionEvent::class => 'onException'];
  }

  /**
   * Swap in a backup provider's response on quota/rate-limit failures.
   */
  public function onException(AiExceptionEvent $event): void {
    // Only failover on quota / rate-limit, and only for chat.
    if (!($event->exception instanceof AiQuotaException
        || $event->exception instanceof AiRateLimitException)) {
      return;
    }
    if ($event->getOperationType() !== 'chat' || !$event->getInput() instanceof ChatInput) {
      return;
    }
    // Avoid looping if the backup itself threw.
    if ($event->getProviderId() === 'anthropic') {
      return;
    }

    $backup = $this->aiProvider->createInstance('anthropic');
    $output = $backup->chat($event->getInput(), 'claude-3-5-sonnet-latest', $event->getTags());
    $event->setForcedOutputObject($output);
  }

}
```

If no subscriber sets a forced output, the proxy rethrows `$event->getException()` — so pure message-rewrite subscribers can coexist with failover subscribers, and an installation with no subscribers at all behaves exactly as before.

