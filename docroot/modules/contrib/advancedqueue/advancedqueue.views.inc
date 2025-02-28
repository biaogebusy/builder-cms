<?php

/**
 * @file
 * Provide views data for the Advanced Queue module.
 */

/**
 * Implements hook_views_data().
 */
function advancedqueue_views_data() {
  $data['advancedqueue'] = [];
  $data['advancedqueue']['table']['group'] = t('Advanced queue');

  $data['advancedqueue']['table']['base'] = [
    'field' => 'job_id',
    'title' => t('Jobs'),
    'help' => t('Contains a list of advanced queue jobs.'),
  ];

  $data['advancedqueue']['job_id'] = [
    'title' => t('Job ID'),
    'help' => t('Primary Key: Job ID.'),
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
    'title' => t('Queue ID'),
    'help' => t('The queue ID.'),
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
    'title' => t('Job type'),
    'help' => t('The job type.'),
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
    'title' => t('Payload'),
    'help' => t('The job payload, stored as JSON.'),
    'field' => [
      'id' => 'advancedqueue_json',
    ],
  ];
  $data['advancedqueue']['state'] = [
    'title' => t('State'),
    'help' => t('The job state'),
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
    'title' => t('Message'),
    'help' => t('The job message, stored after processing the job.'),
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
    'title' => t('Number of retries'),
    'help' => t('The number of times the job has been retried.'),
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
    'title' => t('Available date'),
    'help' => t('The availability timestamp.'),
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
    'title' => t('Processed date'),
    'help' => t('The processing timestamp.'),
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
    'title' => t('Expire date'),
    'help' => t('The lease expiration timestamp.'),
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
    'title' => t('Operations'),
    'help' => t('Provides available operations'),
    'field' => [
      'id' => 'advancedqueue_job_operations',
    ],
  ];

  return $data;
}
