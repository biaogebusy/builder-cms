<?php

declare(strict_types=1);

namespace Drupal\Tests\ai\Kernel\Service\FunctionCalling;

use Drupal\ai\OperationType\Chat\Tools\ToolsFunctionOutput;
use Drupal\ai_test\Plugin\AiFunctionCall\UrlTest;
use Drupal\KernelTests\KernelTestBase;

/**
 * Tests for the UrlTest function call plugin.
 *
 * @group ai
 */
final class UrlTestFunctionCallTest extends KernelTestBase {

  /**
   * The function call plugin manager.
   *
   * @var \Drupal\ai\Plugin\AiFunctionCall\AiFunctionCallManager
   */
  protected $functionCallManager;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'node',
    'user',
    'key',
    'ai',
    'system',
    'field',
    'link',
    'text',
    'ai_test',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->functionCallManager = $this->container->get('plugin.manager.ai.function_calls');

    $this->installEntitySchema('user');
    $this->installEntitySchema('node');
  }

  /**
   * Test that the url_test plugin definition exists.
   */
  public function testUrlTestDefinitionExists(): void {
    $definitions = $this->functionCallManager->getDefinitions();
    $this->assertArrayHasKey('ai:url_test', $definitions, 'UrlTest function call definition exists.');
  }

  /**
   * Test that the url_test plugin is executable.
   */
  public function testUrlTestIsExecutable(): void {
    $executable_definitions = $this->functionCallManager->getExecutableDefinitions();
    $this->assertArrayHasKey('ai:url_test', $executable_definitions, 'UrlTest executable function call definition exists.');
  }

  /**
   * Test that the url_test function exists by function name.
   */
  public function testUrlTestFunctionExists(): void {
    $this->assertTrue($this->functionCallManager->functionExists('url_test'), 'Function url_test exists.');
  }

  /**
   * Test creating an instance from function name.
   */
  public function testGetUrlTestFromFunctionName(): void {
    $function_call = $this->functionCallManager->getFunctionCallFromFunctionName('url_test');
    $this->assertInstanceOf(UrlTest::class, $function_call, 'Function call from function name is an instance of UrlTest.');
  }

  /**
   * Test creating an instance from class.
   */
  public function testGetUrlTestFromClass(): void {
    $function_call = $this->functionCallManager->getFunctionCallFromClass(UrlTest::class);
    $this->assertInstanceOf(UrlTest::class, $function_call, 'Function call from class is an instance of UrlTest.');
  }

  /**
   * Test a valid HTTPS URL.
   */
  public function testValidHttpsUrl(): void {
    $input = $this->functionCallManager->createInstance('ai:url_test');
    $input->setContextValue('input', 'https://example.com');
    $input->execute();
    $this->assertEquals('The input is a valid URL: https://example.com', $input->getReadableOutput());
  }

  /**
   * Test a valid HTTP URL.
   */
  public function testValidHttpUrl(): void {
    $input = $this->functionCallManager->createInstance('ai:url_test');
    $input->setContextValue('input', 'http://example.com');
    $input->execute();
    $this->assertEquals('The input is a valid URL: http://example.com', $input->getReadableOutput());
  }

  /**
   * Test a valid HTTPS URL with path.
   */
  public function testValidHttpsUrlWithPath(): void {
    $input = $this->functionCallManager->createInstance('ai:url_test');
    $input->setContextValue('input', 'https://example.com/path/to/page');
    $input->execute();
    $this->assertEquals('The input is a valid URL: https://example.com/path/to/page', $input->getReadableOutput());
  }

  /**
   * Test a valid HTTPS URL with query string.
   */
  public function testValidHttpsUrlWithQuery(): void {
    $input = $this->functionCallManager->createInstance('ai:url_test');
    $input->setContextValue('input', 'https://example.com/search?q=test&lang=en');
    $input->execute();
    $this->assertEquals('The input is a valid URL: https://example.com/search?q=test&lang=en', $input->getReadableOutput());
  }

  /**
   * Test a valid HTTPS URL with fragment.
   */
  public function testValidHttpsUrlWithFragment(): void {
    $input = $this->functionCallManager->createInstance('ai:url_test');
    $input->setContextValue('input', 'https://example.com/page#section');
    $input->execute();
    $this->assertEquals('The input is a valid URL: https://example.com/page#section', $input->getReadableOutput());
  }

  /**
   * Test a valid HTTPS URL with port.
   */
  public function testValidHttpsUrlWithPort(): void {
    $input = $this->functionCallManager->createInstance('ai:url_test');
    $input->setContextValue('input', 'https://example.com:8443/page');
    $input->execute();
    $this->assertEquals('The input is a valid URL: https://example.com:8443/page', $input->getReadableOutput());
  }

  /**
   * Test a valid FTP URL.
   */
  public function testValidFtpUrl(): void {
    $input = $this->functionCallManager->createInstance('ai:url_test');
    $input->setContextValue('input', 'ftp://files.example.com/pub');
    $input->execute();
    $this->assertEquals('The input is a valid URL: ftp://files.example.com/pub', $input->getReadableOutput());
  }

  /**
   * Test that a bare domain like byte.theme is not a valid URL.
   */
  public function testBareDomainByteTheme(): void {
    $input = $this->functionCallManager->createInstance('ai:url_test');
    $input->setContextValue('input', 'byte.theme');
    $input->execute();
    $this->assertEquals('The input is not a valid URL: byte.theme', $input->getReadableOutput());
  }

  /**
   * Test that a bare domain like byte.test is not a valid URL.
   */
  public function testBareDomainByteTest(): void {
    $input = $this->functionCallManager->createInstance('ai:url_test');
    $input->setContextValue('input', 'byte.test');
    $input->execute();
    $this->assertEquals('The input is not a valid URL: byte.test', $input->getReadableOutput());
  }

  /**
   * Test that a bare domain like example.com is not a valid URL.
   */
  public function testBareDomainExampleCom(): void {
    $input = $this->functionCallManager->createInstance('ai:url_test');
    $input->setContextValue('input', 'example.com');
    $input->execute();
    $this->assertEquals('The input is not a valid URL: example.com', $input->getReadableOutput());
  }

  /**
   * Test that www.example.com without protocol is not a valid URL.
   */
  public function testWwwDomainWithoutProtocol(): void {
    $input = $this->functionCallManager->createInstance('ai:url_test');
    $input->setContextValue('input', 'www.example.com');
    $input->execute();
    $this->assertEquals('The input is not a valid URL: www.example.com', $input->getReadableOutput());
  }

  /**
   * Test that plain text is not a valid URL.
   */
  public function testPlainText(): void {
    $input = $this->functionCallManager->createInstance('ai:url_test');
    $input->setContextValue('input', 'hello world');
    $input->execute();
    $this->assertEquals('The input is not a valid URL: hello world', $input->getReadableOutput());
  }

  /**
   * Test that an empty string is not a valid URL.
   */
  public function testEmptyString(): void {
    $input = $this->functionCallManager->createInstance('ai:url_test');
    $input->setContextValue('input', '');
    $input->execute();
    $this->assertEquals('The input is not a valid URL: ', $input->getReadableOutput());
  }

  /**
   * Test that a single word is not a valid URL.
   */
  public function testSingleWord(): void {
    $input = $this->functionCallManager->createInstance('ai:url_test');
    $input->setContextValue('input', 'notaurl');
    $input->execute();
    $this->assertEquals('The input is not a valid URL: notaurl', $input->getReadableOutput());
  }

  /**
   * Test that a javascript scheme is not a valid URL.
   */
  public function testJavascriptScheme(): void {
    $input = $this->functionCallManager->createInstance('ai:url_test');
    $input->setContextValue('input', 'javascript:alert(1)');
    $input->execute();
    $this->assertEquals('The input is not a valid URL: javascript:alert(1)', $input->getReadableOutput());
  }

  /**
   * Test that a data scheme is not a valid URL.
   */
  public function testDataScheme(): void {
    $input = $this->functionCallManager->createInstance('ai:url_test');
    $input->setContextValue('input', 'data:text/html,<h1>Hi</h1>');
    $input->execute();
    $this->assertEquals('The input is not a valid URL: data:text/html,<h1>Hi</h1>', $input->getReadableOutput());
  }

  /**
   * Test converting tool response to function call response.
   */
  public function testConvertToolResponseToFunctionCallResponse(): void {
    $input = $this->functionCallManager->createInstance('ai:url_test');
    $output = new ToolsFunctionOutput($input->normalize(), 'random', [
      'input' => 'https://example.com',
    ]);

    $function_call_response = $this->functionCallManager->convertToolResponseToObject($output);
    $this->assertInstanceOf(UrlTest::class, $function_call_response, 'Function call response is an instance of UrlTest.');
    $function_call_response->execute();
    $this->assertEquals('The input is a valid URL: https://example.com', $function_call_response->getReadableOutput());
  }

  /**
   * Test converting tool response with invalid URL.
   */
  public function testConvertToolResponseWithInvalidUrl(): void {
    $input = $this->functionCallManager->createInstance('ai:url_test');
    $output = new ToolsFunctionOutput($input->normalize(), 'random', [
      'input' => 'byte.theme',
    ]);

    $function_call_response = $this->functionCallManager->convertToolResponseToObject($output);
    $this->assertInstanceOf(UrlTest::class, $function_call_response, 'Function call response is an instance of UrlTest.');
    $function_call_response->execute();
    $this->assertEquals('The input is not a valid URL: byte.theme', $function_call_response->getReadableOutput());
  }

}
