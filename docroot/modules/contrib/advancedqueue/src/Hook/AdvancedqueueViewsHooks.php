<?php

namespace Drupal\advancedqueue\Hook;

use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\StringTranslation\StringTranslationTrait;

/**
 * Hook implementations for advancedqueue.
 */
class AdvancedqueueViewsHooks {
  use StringTranslationTrait;

  /**
   * @file
   * Provide views data for the Advanced Queue module.
   */

  /**
   * Implements hook_views_data().
   */
  #[Hook('views_data')]
  public function viewsData(): array {
    $data['advancedqueue'] = [];
    $data['advancedqueue']['table']['group'] = $this->t('Advanced queue');
    $data['advancedqueue']['table']['base'] = [
      'field' => 'job_id',
      'title' => $this->t('Jobs'),
      'help' => $this->t('Contains a list of advanced queue jobs.'),
    ];
    $data['advancedqueue']['job_id'] = [
      'title' => $this->t('Job ID'),
      'help' => $this->t('Primary Key: Job ID.'),
      'field' => [
        'id' => 'standard',
      ],
      'filter' => [
        'id' => 'numeric',
      ],
      'argument' => [
        'id' => 'numeric',
      ],
      'sort' => [
        'id' => 'standard',
      ],
    ];
    $data['advancedqueue']['queue_id'] = [
      'title' => $this->t('Queue ID'),
      'help' => $this->t('The queue ID.'),
      'field' => [
        'id' => 'standard',
      ],
      'filter' => [
        'id' => 'string',
      ],
      'argument' => [
        'id' => 'string',
      ],
      'sort' => [
        'id' => 'standard',
      ],
    ];
    $data['advancedqueue']['type'] = [
      'title' => $this->t('Job type'),
      'help' => $this->t('The job type.'),
      'field' => [
        'id' => 'advancedqueue_job_type',
      ],
      'filter' => [
        'id' => 'in_operator',
        'options callback' => '\Drupal\advancedqueue\Plugin\views\field\JobType::getOptions',
      ],
      'argument' => [
        'id' => 'string',
      ],
      'sort' => [
        'id' => 'standard',
      ],
    ];
    $data['advancedqueue']['payload'] = [
      'title' => $this->t('Payload'),
      'help' => $this->t('The job payload, stored as JSON.'),
      'field' => [
        'id' => 'advancedqueue_json',
      ],
      'filter' => [
        'id' => 'string',
      ],
    ];
    $data['advancedqueue']['state'] = [
      'title' => $this->t('State'),
      'help' => $this->t('The job state'),
      'field' => [
        'id' => 'advancedqueue_job_state',
      ],
      'filter' => [
        'id' => 'in_operator',
        'options callback' => '\Drupal\advancedqueue\Plugin\views\field\JobState::getOptions',
      ],
      'argument' => [
        'id' => 'string',
      ],
      'sort' => [
        'id' => 'standard',
      ],
    ];
    $data['advancedqueue']['message'] = [
      'title' => $this->t('Message'),
      'help' => $this->t('The job message, stored after processing the job.'),
      'field' => [
        'id' => 'standard',
      ],
      'filter' => [
        'id' => 'string',
      ],
      'argument' => [
        'id' => 'string',
      ],
      'sort' => [
        'id' => 'standard',
      ],
    ];
    $data['advancedqueue']['num_retries'] = [
      'title' => $this->t('Number of retries'),
      'help' => $this->t('The number of times the job has been retried.'),
      'field' => [
        'id' => 'numeric',
      ],
      'filter' => [
        'id' => 'numeric',
      ],
      'argument' => [
        'id' => 'numeric',
      ],
      'sort' => [
        'id' => 'standard',
      ],
    ];
    $data['advancedqueue']['available'] = [
      'title' => $this->t('Available date'),
      'help' => $this->t('The availability timestamp.'),
      'field' => [
        'id' => 'date',
      ],
      'filter' => [
        'id' => 'date',
      ],
      'argument' => [
        'id' => 'date',
      ],
      'sort' => [
        'id' => 'date',
      ],
    ];
    $data['advancedqueue']['processed'] = [
      'title' => $this->t('Processed date'),
      'help' => $this->t('The processing timestamp.'),
      'field' => [
        'id' => 'date',
      ],
      'filter' => [
        'id' => 'date',
      ],
      'argument' => [
        'id' => 'date',
      ],
      'sort' => [
        'id' => 'date',
      ],
    ];
    $data['advancedqueue']['expires'] = [
      'title' => $this->t('Expire date'),
      'help' => $this->t('The lease expiration timestamp.'),
      'field' => [
        'id' => 'date',
      ],
      'filter' => [
        'id' => 'date',
      ],
      'argument' => [
        'id' => 'date',
      ],
      'sort' => [
        'id' => 'date',
      ],
    ];
    $data['advancedqueue']['operations'] = [
      'title' => $this->t('Operations'),
      'help' => $this->t('Provides available operations'),
      'field' => [
        'id' => 'advancedqueue_job_operations',
      ],
    ];
    $data['advancedqueue']['advancedqueue_bulk_form'] = [
      'title' => $this->t('Advanced queue operations bulk form'),
      'help' => $this->t('Add a form element that lets you run operations on multiple queue items.'),
      'field' => [
        'id' => 'advancedqueue_bulk_form',
      ],
    ];
    return $data;
  }

}
