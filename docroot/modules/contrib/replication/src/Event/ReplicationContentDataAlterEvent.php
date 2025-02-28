<?php

namespace Drupal\replication\Event;


use Drupal\Core\Entity\ContentEntityInterface;
use Symfony\Component\EventDispatcher\Event;

/**
 * Event instance for altering normalized content entity data.
 */
class ReplicationContentDataAlterEvent extends Event {

  /**
   * The entity being normalized.
   *
   * @var \Drupal\Core\Entity\ContentEntityInterface
   */
  protected $entity;

  /**
   * The data being normalized.
   *
   * @var array
   */
  protected $data;

  /**
   * @var string
   */
  protected $format;

  /**
   * @var array
   */
  protected $context;

  /**
   * ReplicationContentDataAlterEvent constructor.
   *
   * @param ContentEntityInterface $entity
   * @param array $data
   * @param $format
   * @param array $context
   */
  public function __construct(ContentEntityInterface $entity, array $data, $format, array $context) {
    $this->entity = $entity;
    $this->data = $data;
    $this->format = $format;
    $this->context = $context;
  }

  /**
   * @return \Drupal\Core\Entity\ContentEntityInterface
   */
  public function getEntity() {
    return $this->entity;
  }

  /**
   * @return string
   */
  public function getFormat() {
    return $this->format;
  }

  /**
   * @return array
   */
  public function getContext() {
    return $this->context;
  }


  /**
   * @return array
   */
  public function getData() {
    return $this->data;
  }

  /**
   * @param array $data
   */
  public function setData(array $data) {
    $this->data = $data;
  }

}
