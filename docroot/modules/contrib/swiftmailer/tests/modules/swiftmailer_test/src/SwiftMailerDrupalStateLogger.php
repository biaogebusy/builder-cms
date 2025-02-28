<?php

namespace Drupal\swiftmailer_test;

use Swift_Events_SendEvent;
use Swift_Events_SendListener;

class SwiftMailerDrupalStateLogger implements Swift_Events_SendListener {

  public function beforeSendPerformed(Swift_Events_SendEvent $evt) {
    $this->add([
      'method' => 'beforeSendPerformed',
      'body' => $evt->getMessage()->getBody(),
      'subject' => $evt->getMessage()->getSubject(),
      'headers' => $this->getHeadersAsArray($evt->getMessage()),
    ]);
  }

  public function sendPerformed(Swift_Events_SendEvent $evt) {
    $this->add([
      'method' => 'sendPerformed',
      'body' => $evt->getMessage()->getBody(),
      'subject' => $evt->getMessage()->getSubject(),
      'headers' => $this->getHeadersAsArray($evt->getMessage()),
    ]);
  }

  /**
   * Gathers and returns the headers of the message as an array.
   *
   * @param \Swift_Mime_SimpleMessage $m
   *   The swift message object.
   *
   * @return array
   *   An array of headers and their values.
   */
  protected function getHeadersAsArray(\Swift_Mime_SimpleMessage $m) {
    $return = [];
    /** @var \Symfony\Component\Mime\Header\AbstractHeader $header */
    foreach ($m->getHeaders()->getAll() as $header) {
      if ($header instanceof \Swift_Mime_Header) {
        $return[$header->getFieldName()] = $header->getFieldBody();
      }
    }
    return $return;
  }

  public function add($entry) {
    $captured_emails = \Drupal::state()->get('swiftmailer.mail_collector') ?: [];
    $captured_emails[] = $entry;
    \Drupal::state()->set('swiftmailer.mail_collector', $captured_emails);
  }

  public function clear() {
    \Drupal::state()->delete('swiftmailer.mail_collector');
  }

  public function dump() {
    return \Drupal::state()->get('swiftmailer.mail_collector', []);
  }

}
