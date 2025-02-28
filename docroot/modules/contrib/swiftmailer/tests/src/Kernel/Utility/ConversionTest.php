<?php

namespace Drupal\Tests\swiftmailer\Kernel\Utility;

use Drupal\KernelTests\KernelTestBase;
use Drupal\swiftmailer\Utility\Conversion;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Header\MailboxHeader;

/**
 * @coversDefaultClass \Drupal\swiftmailer\Utility\Conversion
 * @group swiftmailer
 */
class ConversionTest extends KernelTestBase {

  /**
   * @dataProvider swiftmailer_parse_mailboxes_dataProvider
   */
  public function test_swiftmailer_parse_mailboxes($value, $expected) {
    $this->assertSame($expected, Conversion::swiftmailer_parse_mailboxes($value));
  }

  /**
   * DataProvider for ::test_swiftmailer_parse_mailboxes.
   */
  public function swiftmailer_parse_mailboxes_dataProvider() {
    return [
      [
        'mail@example.com',
        [
          'mail@example.com',
        ],
      ],
      [
        'mail1@example.com;mail2@example.com',
        [
          'mail1@example.com',
          'mail2@example.com',
        ],
      ],
      [
        'mail1@example.com;invalid-email',
        [
          'mail1@example.com',
        ],
      ],
    ];
  }

  /**
   * Test for regex match after q-encoding inplaced.
   */
  public function testConversionRegex() {
    $site_mail = 'simpletest@example.com';
    $site_name = '中文名稱測試文';

    // Assert regex pattern of mailbox_header from q-encoding site_name.
    $mailbox = new MailboxHeader('From', new Address($site_mail, $site_name));
    $from = $mailbox->getBodyAsString();
    $test = Conversion::swiftmailer_is_mailbox_header('From', $from);
    $this->assertTrue($test);

    // Assert decoding of q-encoded site_name.
    $test2 = Conversion::swiftmailer_parse_mailboxes($from)[$site_mail];
    $site_name2 = iconv_mime_decode($test2);
    $this->assertEquals($site_name, $site_name2);
  }

}
