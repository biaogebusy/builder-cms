<?php

namespace Drupal\Tests\update_to_d11\Unit;

use Consolidation\AnnotatedCommand\AnnotatedCommandFactory;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\ImmutableConfig;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\update_to_d11\Drush\PanelizerMigrateCommands;
use Drupal\update_to_d11\PanelizerMigrator;
use Drush\Log\DrushLoggerManager;
use Drush\Style\DrushStyle;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;

/** Checks cleanup preconditions without deleting configuration or entities. */
final class PanelizerCleanupTest extends TestCase {

  private array $events = [];

  private const EMPTY_REPORT = [
    'checked' => 0, 'ok' => 0, 'missing' => [], 'mismatch' => [], 'display_residue' => [],
  ];

  public static function unsafeReports(): array {
    return [
      'missing layout' => [['checked' => 1, 'missing' => ['node 12 (page / en)']]],
      'different UUID sequence' => [['checked' => 1, 'mismatch' => ['node 12: panelizer=a,b layout=b,a']]],
      'residue without content' => [['display_residue' => ['core.entity_view_display.node.page.default']]],
      'matching content with residue' => [['checked' => 1, 'ok' => 1, 'display_residue' => ['core.entity_view_display.node.page.default']]],
    ];
  }

  #[DataProvider('unsafeReports')]
  public function testFailedVerificationStopsBeforeFirstWrite(array $changes): void {
    $migrator = $this->migrator([array_replace(self::EMPTY_REPORT, $changes)]);
    $exception = $this->cleanupException($migrator);
    $this->assertSame(['verify'], $this->events);
    $this->assertInstanceOf(\RuntimeException::class, $exception);
    $this->assertStringContainsString('update-to-d11:panelizer-verify', $exception->getMessage());
  }

  public static function safeReports(): array {
    return [
      'nothing to compare' => [self::EMPTY_REPORT],
      'matching content' => [array_replace(self::EMPTY_REPORT, ['checked' => 2, 'ok' => 2])],
    ];
  }

  #[DataProvider('safeReports')]
  public function testSuccessfulVerificationReachesExistingCleanup(array $report): void {
    $exception = $this->cleanupException($this->migrator([$report]));
    $this->assertSame(['verify', 'write-boundary'], $this->events);
    $this->assertInstanceOf(\LogicException::class, $exception);
    $this->assertSame('Test stopped at the first write boundary.', $exception->getMessage());
  }

  public function testVerifierExceptionAbortsBeforeFirstWrite(): void {
    $failure = new \RuntimeException('Verification failed to read content.');
    $exception = $this->cleanupException($this->migrator([$failure]));
    $this->assertSame($failure, $exception);
    $this->assertSame(['verify'], $this->events);
  }

  public function testEarlierSuccessfulVerificationDoesNotAuthorizeLaterCleanup(): void {
    $migrator = $this->migrator([
      self::EMPTY_REPORT,
      array_replace(self::EMPTY_REPORT, ['display_residue' => ['core.entity_view_display.node.page.default']]),
    ]);
    $this->assertSame(self::EMPTY_REPORT, $migrator->verify());
    $exception = $this->cleanupException($migrator);
    $this->assertSame(['verify', 'verify'], $this->events);
    $this->assertInstanceOf(\RuntimeException::class, $exception);
  }

  public function testCancellationDoesNotVerifyOrCleanUp(): void {
    [$status, $output, $logs] = $this->command($this->migrator([self::EMPTY_REPORT]), FALSE);
    $this->assertSame(0, $status);
    $this->assertSame(['confirm'], $this->events);
    $this->assertSame(['warning'], $logs);
    $this->assertStringNotContainsString('清理日志', $output);
  }

  public function testConfirmedCommandCannotBypassFailedServiceVerification(): void {
    $report = array_replace(self::EMPTY_REPORT, ['display_residue' => ['core.entity_view_display.node.page.default']]);
    [$status, $output, $logs] = $this->command($this->migrator([$report]), TRUE);
    $this->assertSame(1, $status);
    $this->assertSame(['confirm', 'verify'], $this->events);
    $this->assertSame([], $logs);
    $this->assertStringContainsString('update-to-d11:panelizer-verify', $output);
    $this->assertStringNotContainsString('清理日志', $output);
  }

  public function testCommandReportsSuccessOnlyAfterCleanupReturns(): void {
    $migrator = $this->createMock(PanelizerMigrator::class);
    $migrator->method('cleanup')->willReturnCallback(function (): array {
      $this->events[] = 'cleanup-returned';
      return ['Fixture cleanup complete'];
    });
    [$status, $output, $logs] = $this->command($migrator, TRUE);
    $this->assertSame(0, $status);
    $this->assertSame(['confirm', 'cleanup-returned'], $this->events);
    $this->assertSame(['success'], $logs);
    $this->assertStringContainsString('Fixture cleanup complete', $output);
  }

  private function migrator(array $reports): PanelizerMigrator {
    $configs = $this->createMock(ConfigFactoryInterface::class);
    $config = $this->createMock(ImmutableConfig::class);
    $config->method('get')->with('third_party_settings.panelizer')->willReturn(['enable' => TRUE]);
    $configs->method('listAll')->willReturn(['core.entity_view_display.node.page.default']);
    $configs->method('get')->willReturn($config);
    // Stop before mutation even if a regression removes the service guard.
    $configs->method('getEditable')->willReturnCallback(function () {
      $this->events[] = 'write-boundary';
      throw new \LogicException('Test stopped at the first write boundary.');
    });
    $migrator = $this->getMockBuilder(PanelizerMigrator::class)
      ->setConstructorArgs([
        $this->createMock(EntityTypeManagerInterface::class),
        $this->createMock(EntityFieldManagerInterface::class), $configs,
      ])->onlyMethods(['verify'])->getMock();
    $migrator->method('verify')->willReturnCallback(function () use (&$reports): array {
      $this->events[] = 'verify';
      $report = array_shift($reports);
      if ($report instanceof \Throwable) {
        throw $report;
      }
      return $report;
    });
    return $migrator;
  }

  private function cleanupException(PanelizerMigrator $migrator): \Throwable {
    try {
      $migrator->cleanup();
    }
    catch (\Throwable $exception) {
      return $exception;
    }
    $this->fail('Fixture cleanup must stop before any real mutation.');
  }

  private function command(PanelizerMigrator $migrator, bool $confirmed): array {
    $handler = new PanelizerMigrateCommands($migrator);
    $input = new ArrayInput([]);
    $input->setInteractive(FALSE);
    $output = new BufferedOutput();
    $style = $this->getMockBuilder(DrushStyle::class)
      ->setConstructorArgs([$input, $output])->onlyMethods(['confirm'])->getMock();
    $style->method('confirm')->willReturnCallback(function ($question, $default) use ($confirmed): bool {
      $this->assertFalse($default);
      $this->events[] = 'confirm';
      return $confirmed;
    });
    $handler->restoreState($input, $output, $style);
    $logger = $this->createMock(DrushLoggerManager::class);
    $logs = [];
    foreach (['success', 'warning'] as $level) {
      $logger->method($level)->willReturnCallback(static function () use (&$logs, $level): void {
        $logs[] = $level;
      });
    }
    $handler->setLogger($logger);
    $factory = new AnnotatedCommandFactory();
    $command = $factory->createCommand($factory->createCommandInfo($handler, 'cleanup'), $handler);
    $this->assertSame('update-to-d11:panelizer-cleanup', $command->getName());
    $status = $command->run($input, $output);
    return [$status, $output->fetch(), $logs];
  }

}
