<?php

declare(strict_types=1);

namespace Drupal\Tests\ai\Kernel\Service;

use Drupal\KernelTests\KernelTestBase;
use Drupal\Tests\ai\Mock\MockIterator;
use Drupal\Tests\ai\Mock\MockStreamedChatIterator;

/**
 * @coversDefaultClass \Drupal\ai\Service\HostnameFilter
 * @group ai
 */
class HostnameFilterTest extends KernelTestBase {

  /**
   * Modules to enable.
   *
   * @var array
   */
  protected static $modules = [
    'ai',
    'ai_test',
    'key',
    'file',
    'user',
    'field',
    'system',
  ];

  /**
   * Setup the test.
   */
  protected function setUp(): void {
    parent::setUp();

    // Install entity schemas.
    $this->installEntitySchema('user');
    $this->installEntitySchema('file');
    $this->installSchema('file', [
      'file_usage',
    ]);
    $this->installConfig(['ai', 'ai_test']);
    $this->installEntitySchema('ai_mock_provider_result');
  }

  /**
   * Test that small chunks and relative works.
   */
  public function testChatStreamWithRelativeLinks(): void {
    $stream = [
      0 => '<h',
      1 => '2>Rethinking',
      2 => ' digital ecosystems for',
      3 => ' scalable platforms',
      4 => ' today</h2><p',
      5 => '>Modern organizations',
      6 => ' operate in increasingly complex environments,',
      7 => ' where systems must communicate seamlessly',
      8 => ' across multiple layers.<br>Yet',
      9 => ' many teams still struggle',
      10 => ' with fragmented architectures',
      11 => ' that limit their ability',
      12 => 'to move quickly.',
      13 => ' This creates bottlenecks',
      14 => ' that slow innovation.',
      15 => ' A unified approach is',
      16 => '<br>no longer optional,',
      17 => ' but essential for growth',
      18 => '.</p><img data-entity-',
      19 => 'uuid="a1b2c3d4-e56',
      20 => '7-8901-abcd',
      21 => '-1234567890ef',
      22 => '" data-entity-type',
      23 => '="file" src="/sites/default',
      24 => '/files/inline-images/',
      25 => 'platform_visual_',
      26 => 'example_stream_',
      27 => 'image.png" width="',
      28 => '2048" height="1152">',
      29 => '<h4>What is changing',
      30 => ' in platform design</h4><p',
      31 => '>Today,',
      32 => ' distributed systems',
      33 => ' enable flexible integration patterns',
      34 => ' between services',
      35 => '<br>and external providers.',
      36 => ' Event-driven architectures are becoming standard',
      37 => ' alongside API-first approaches ',
      38 => 'to support scalability.',
      39 => '<br>At the same time, automation is accelerating',
      40 => ' across industries',
      41 => ' driven',
      42 => '<br>by AI-powered decision systems',
      43 => ' that continuously adapt',
      44 => ' to new inputs',
      45 => '.</p',
      46 => '><p>Learn more at [<a href="https',
      47 => '://example.org/',
      48 => 'platform-strategy',
      49 => '/">this guide</a>].',
      50 => ' &nbsp;</p><h4>',
      51 => 'Key performance indicators',
      52 => ' across industries',
      53 => '</h4><table class="table',
      54 => '"><tbody><tr><td>',
      55 => 'Education &nbsp; &nbsp;',
      56 => ' &nbsp;</td><td>52',
      57 => '% adoption&',
      58 => 'nbsp;</td><td>Moderate',
      59 => ' impact&nbsp;</td>',
      60 => '</tr><tr><td>Government',
      61 => ' &nbsp; &nbsp;&nbsp;',
      62 => '</td><td>41% transformation',
      63 => '&nbsp;</td><td>',
      64 => 'Significant',
      65 => '</td></tr><tr><td>&',
      66 => 'nbsp;',
      67 => 'E-commerce</td>',
      68 => '<td>&nbsp;68% expansion',
      69 => '</td><td>High impact',
      70 => '&nbsp;</td></tr>',
      71 => '<tr><td>Healthcare',
      72 => '</td><td>&nbsp;59%',
      73 => ' adoption</td><td>',
      74 => 'Critical</td></tr></tbody',
      75 => '></table><h4>What to do next',
      76 => ' in practice</h4><p',
      77 => '>Start by reviewing your architecture',
      78 => ' and identifying integration gaps.',
      79 => ' Introduce tools that enable automation',
      80 => ' and orchestration across services.',
      81 => ' Intelligent systems can surface insights',
      82 => ' and highlight inefficiencies automatically<br>so teams can act faster.',
      83 => ' Over time, embed these capabilities',
      84 => ' into your workflows<br>to create a resilient, adaptive platform.</p>',
      85 => '',
      86 => '',
    ];

    $full_link = "/sites/default/files/inline-images/platform_visual_example_stream_image.png";

    $iterator = new MockIterator($stream);
    $message = new MockStreamedChatIterator($iterator);
    $collected = '';
    foreach ($message as $part) {
      $this->assertIsString($part->getText());
      $collected .= $part->getText();
    }
    $this->assertStringContainsString($full_link, $collected);
  }

  /**
   * Test that small chunks and absolute links work.
   */
  public function testChatStreamWithAbsoluteLinks(): void {
    $stream = [
      0 => '<h',
      1 => '2>Rethinking',
      2 => ' digital ecosystems for',
      3 => ' scalable platforms',
      4 => ' today</h2><p',
      5 => '>Modern organizations',
      6 => ' operate in increasingly complex environments,',
      7 => ' where systems must communicate seamlessly',
      8 => ' across multiple layers.<br>Yet',
      9 => ' many teams still struggle',
      10 => ' with fragmented architectures',
      11 => ' that limit their ability',
      12 => 'to move quickly.',
      13 => ' This creates bottlenecks',
      14 => ' that slow innovation.',
      15 => ' A unified approach is',
      16 => '<br>no longer optional,',
      17 => ' but essential for growth',
      18 => '.</p><img data-entity-',
      19 => 'uuid="a1b2c3d4-e56',
      20 => '7-8901-abcd',
      21 => '-1234567890ef',
      22 => '" data-entity-type',
      23 => '="file" src="https://',
      24 => 'example.com/files',
      25 => '/inline-images/platform_visual_',
      26 => 'example_stream_',
      27 => 'image.png" width="',
      28 => '2048" height="1152">',
      29 => '<h4>What is changing',
      30 => ' in platform design</h4><p',
      31 => '>Today,',
      32 => ' distributed systems',
      33 => ' enable flexible integration patterns',
      34 => ' between services',
      35 => '<br>and external providers.',
      36 => ' Event-driven architectures are becoming standard',
      37 => ' alongside API-first approaches ',
      38 => 'to support scalability.',
      39 => '<br>At the same time, automation is accelerating',
      40 => ' across industries',
      41 => ' driven',
      42 => '<br>by AI-powered decision systems',
      43 => ' that continuously adapt',
      44 => ' to new inputs',
      45 => '.</p',
      46 => '><p>Learn more at [<a href="https',
      47 => '://example.org/',
      48 => 'platform-strategy',
      49 => '/">this guide</a>].',
      50 => ' &nbsp;</p><h4>',
      51 => 'Key performance indicators',
      52 => ' across industries',
      53 => '</h4><table class="table',
      54 => '"><tbody><tr><td>',
      55 => 'Education &nbsp; &nbsp;',
      56 => ' &nbsp;</td><td>52',
      57 => '% adoption&',
      58 => 'nbsp;</td><td>Moderate',
      59 => ' impact&nbsp;</td>',
      60 => '</tr><tr><td>Government',
      61 => ' &nbsp; &nbsp;&nbsp;',
      62 => '</td><td>41% transformation',
      63 => '&nbsp;</td><td>',
      64 => 'Significant',
      65 => '</td></tr><tr><td>&',
      66 => 'nbsp;',
      67 => 'E-commerce</td>',
      68 => '<td>&nbsp;68% expansion',
      69 => '</td><td>High impact',
      70 => '&nbsp;</td></tr>',
      71 => '<tr><td>Healthcare',
      72 => '</td><td>&nbsp;59%',
      73 => ' adoption</td><td>',
      74 => 'Critical</td></tr></tbody',
      75 => '></table><h4>What to do next',
      76 => ' in practice</h4><p',
      77 => '>Start by reviewing your architecture',
      78 => ' and identifying integration gaps.',
      79 => ' Introduce tools that enable automation',
      80 => ' and orchestration across services.',
      81 => ' Intelligent systems can surface insights',
      82 => ' and highlight inefficiencies automatically<br>so teams can act faster.',
      83 => ' Over time, embed these capabilities',
      84 => ' into your workflows<br>to create a resilient, adaptive platform.</p>',
      85 => '',
      86 => '',
    ];

    $full_link = "https://example.com/files/inline-images/platform_visual_example_stream_image.png";

    $iterator = new MockIterator($stream);
    $message = new MockStreamedChatIterator($iterator);
    $collected = '';
    foreach ($message as $part) {
      $this->assertIsString($part->getText());
      $collected .= $part->getText();
    }
    // Full link should no exist.
    $this->assertStringNotContainsString($full_link, $collected);
  }

  /**
   * Tests getAllowedDomains returns empty array when config value is NULL.
   *
   * @covers ::getAllowedDomains
   */
  public function testGetAllowedDomainsWithNullConfig(): void {
    $this->setRawConfigValue('allowed_hosts', NULL);
    /** @var \Drupal\ai\Service\HostnameFilter $filter */
    $filter = $this->container->get('ai.hostname_filter_service');
    $this->assertSame([], $filter->getAllowedDomains());
  }

  /**
   * Tests getAllowedDomains handles a bare empty string config value.
   *
   * This is the regression scenario from #3586397 where allowed_hosts
   * is stored as '' instead of an empty array.
   *
   * @covers ::getAllowedDomains
   */
  public function testGetAllowedDomainsWithEmptyStringConfig(): void {
    $this->setRawConfigValue('allowed_hosts', '');
    /** @var \Drupal\ai\Service\HostnameFilter $filter */
    $filter = $this->container->get('ai.hostname_filter_service');
    $this->assertSame([], $filter->getAllowedDomains());
  }

  /**
   * Tests getAllowedDomains filters out empty strings from config array.
   *
   * @covers ::getAllowedDomains
   */
  public function testGetAllowedDomainsWithEmptyStringsInArray(): void {
    $this->config('ai.settings')->set('allowed_hosts', ['', '', ''])->save();
    /** @var \Drupal\ai\Service\HostnameFilter $filter */
    $filter = $this->container->get('ai.hostname_filter_service');
    $this->assertSame([], $filter->getAllowedDomains());
  }

  /**
   * Tests getAllowedDomains filters out whitespace-only strings.
   *
   * @covers ::getAllowedDomains
   */
  public function testGetAllowedDomainsWithWhitespaceOnlyStrings(): void {
    $this->config('ai.settings')->set('allowed_hosts', ['  ', "\t", "\n"])->save();
    /** @var \Drupal\ai\Service\HostnameFilter $filter */
    $filter = $this->container->get('ai.hostname_filter_service');
    $this->assertSame([], $filter->getAllowedDomains());
  }

  /**
   * Tests getAllowedDomains normalizes mixed valid and invalid entries.
   *
   * @covers ::getAllowedDomains
   */
  public function testGetAllowedDomainsWithMixedValues(): void {
    $this->config('ai.settings')->set('allowed_hosts', [
      'example.com',
      '',
      '  cdn.example.com  ',
      '   ',
      '*.test.org',
    ])->save();
    /** @var \Drupal\ai\Service\HostnameFilter $filter */
    $filter = $this->container->get('ai.hostname_filter_service');
    $this->assertSame([
      'example.com',
      'cdn.example.com',
      '*.test.org',
    ], $filter->getAllowedDomains());
  }

  /**
   * Tests getAllowedDomains handles a non-empty string config value.
   *
   * @covers ::getAllowedDomains
   */
  public function testGetAllowedDomainsWithNonEmptyStringConfig(): void {
    $this->setRawConfigValue('allowed_hosts', 'example.com');
    /** @var \Drupal\ai\Service\HostnameFilter $filter */
    $filter = $this->container->get('ai.hostname_filter_service');
    $this->assertSame(['example.com'], $filter->getAllowedDomains());
  }

  /**
   * Writes a raw config value bypassing schema validation.
   *
   * This simulates malformed config that arrives via config import, drush,
   * or direct YAML editing, which bypasses the schema checker.
   *
   * @param string $key
   *   The config key within ai.settings.
   * @param mixed $value
   *   The raw value to set.
   */
  protected function setRawConfigValue(string $key, mixed $value): void {
    /** @var \Drupal\Core\Config\StorageInterface $storage */
    $storage = $this->container->get('config.storage');
    $data = $storage->read('ai.settings');
    $data[$key] = $value;
    $storage->write('ai.settings', $data);

    // Clear the static cache so the service reads the new value.
    \Drupal::configFactory()->reset('ai.settings');
  }

}
