<?php

namespace Drupal\replication\Normalizer;

use Drupal\serialization\Normalizer\NormalizerBase;
use Symfony\Component\Serializer\Exception\LogicException;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;

class ChangesNormalizer extends NormalizerBase implements DenormalizerInterface {

  protected $supportedInterfaceOrClass = ['Drupal\replication\Changes\ChangesInterface'];

  /**
   * @var string
   */
  protected $format = ['json'];

  /**
   * {@inheritdoc}
   */
  public function normalize($changes, $format = NULL, array $context = []) {
    /** @var \Drupal\replication\Changes\ChangesInterface $changes */
    if (isset($context['query']['filter'])) {
      $changes->filter($context['query']['filter']);
    }
    if (isset($context['query']['parameters'])) {
      $changes->parameters($context['query']['parameters']);
    }
    if (isset($context['query']['limit'])) {
      $changes->setLimit($context['query']['limit']);
    }
    $since = (isset($context['query']['since']) && is_numeric($context['query']['since'])) ? $context['query']['since'] : 0;
    // Set since if it is not already set. It could be set if there was a POST
    // request.
    if (!$changes->getSince()) {
      $changes->setSince($since);
    }

    $results = $changes->getNormal();
    $last_result = end($results);
    $last_seq = isset($last_result['seq']) ? $last_result['seq'] : 0;

    return [
      'last_seq' => $last_seq,
      'results' => $results,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function denormalize($data, $class, $format = NULL, array $context = []) {
    if (!isset($context['workspace'])) {
      throw new LogicException('A \'workspace\' context is required to denormalize Changes data.');
    }

    // The service is not injected to avoid circular reference.
    /** @var \Drupal\replication\Changes\ChangesInterface $changes */
    $changes = \Drupal::service('replication.changes_factory')->get($context['workspace']);

    if (isset($context['query']['filter'])) {
      $changes->filter($context['query']['filter']);
    }
    if (isset($data['limit'])) {
      $changes->setLimit($data['limit']);
    }
    $since = (isset($data['since']) && is_numeric($data['since'])) ? $data['since'] : 0;
    $changes->setSince($since);
    $parameters = [];
    if (isset($data['parameters'])) {
      $parameters = $data['parameters'];
    }
    if (!empty($data['doc_ids'])) {
      $parameters['doc_ids'] = $data['doc_ids'];
    }

    return $changes->parameters($parameters);
  }

}
