Advanced Queue
==============

Provides a better queue API for Drupal 8.

Queues are configuration entities with an associated backend plugin.  The
backend plugin is responsible for enqueueing and manipulating jobs.  Each
job has a type (JobType plugin), responsible for processing it.

Example:
```
$job = Job::create('commerce_recurring_renew_order', ['order_id' => '10']);
// Any queue can hold any job. Having different queues
// allows grouping jobs by different criteria.
// High vs low priority. Processed by cron or Drush/Console.
// One in SQL, one in Redis. Etc.
$queue = Queue::load('default');
$queue->enqueueJob($job);
```

Features:
- Job states (queued/processing/success/failure)
- Job results (state, message, processing time stored on the job).
- Retries (configurable per job type or per job).
- Delayed processing (run the job in 10 days, retry in 1 day, etc)
- API support for bulk job creation and interfaces for optional features.
- Drush and Drupal Console commands for processing queues.
- Views-powered job listings.
