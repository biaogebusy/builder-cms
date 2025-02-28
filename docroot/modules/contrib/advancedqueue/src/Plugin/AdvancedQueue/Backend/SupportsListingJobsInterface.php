<?php

namespace Drupal\advancedqueue\Plugin\AdvancedQueue\Backend;

/**
 * Provides the interface for queue backends which support listing jobs.
 *
 * This means being able to see what's in the queue at all times.
 * Many backends consider the queue to be opaque for performance reasons.
 */
interface SupportsListingJobsInterface {

}
