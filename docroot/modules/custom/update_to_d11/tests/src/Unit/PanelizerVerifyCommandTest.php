<?php

namespace Drupal\Tests\update_to_d11\Unit;

use Consolidation\AnnotatedCommand\AnnotatedCommandFactory;
use Drupal\Core\Config\ConfigFactory;
use Drupal\Core\Config\MemoryStorage;
use Drupal\Core\Config\TypedConfigManagerInterface;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\update_to_d11\Drush\PanelizerMigrateCommands;
use Drupal\update_to_d11\PanelizerMigrator;
use Drush\Log\DrushLoggerManager;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\EventDispatcher\EventDispatcher;

/** Checks verification output and console exit status without migrating data. */
final class PanelizerVerifyCommandTest extends TestCase {

  public static function reports(): array {
    $empty = ['checked' => 0, 'ok' => 0, 'missing' => [], 'mismatch' => [], 'display_residue' => []];
    $residue = ['core.entity_view_display.node.page.default'];
    return [
      'empty data with residue' => [array_replace($empty, ['display_residue' => $residue]), 1, $residue],
      'empty data without residue' => [$empty, 0, []],
      'matching content' => [array_replace($empty, ['checked' => 2, 'ok' => 2]), 0, []],
      'missing layout' => [array_replace($empty, ['checked' => 1, 'missing' => ['node 12 (page / en)']]), 1, ['node 12 (page / en)']],
      'different block sequence' => [array_replace($empty, ['checked' => 1, 'mismatch' => ['node 13: panelizer=a,b layout=b,a']]), 1, ['node 13: panelizer=a,b layout=b,a']],
      'matching content with residue' => [array_replace($empty, ['checked' => 1, 'ok' => 1, 'display_residue' => $residue]), 1, $residue],
      'multiple errors' => [array_replace($empty, [
        'checked' => 2, 'missing' => ['node 12 (page / en)'],
        'mismatch' => ['node 13: panelizer=a,b layout=b,a'], 'display_residue' => $residue,
      ]), 1, ['node 12 (page / en)', 'node 13: panelizer=a,b layout=b,a', ...$residue]],
    ];
  }

  #[DataProvider('reports')]
  public function testExitStatusAndReportedErrors(array $report, int $expected, array $details): void {
    $migrator = $this->createMock(PanelizerMigrator::class);
    $migrator->expects($this->once())->method('verify')->willReturn($report);
    $migrator->expects($this->never())->method('migrate');
    $migrator->expects($this->never())->method('cleanup');
    [$status, $output, $logs] = $this->runVerification($migrator);
    $this->assertSame($expected, $status);
    $this->assertSame($expected === 0 ? [$report['checked'] === 0 ? 'warning' : 'success'] : [], $logs);
    foreach ($details as $detail) {
      $this->assertStringContainsString($detail, $output);
    }
    if ($report['display_residue']) {
      $this->assertStringContainsString('视图显示仍残留 panelizer 设置', $output);
    }
  }

  public function testRealVerifierRejectsDisplayResidueWithoutAnyPanelizerFields(): void {
    $storage = new MemoryStorage();
    $name = 'core.entity_view_display.node.page.default';
    $data = ['third_party_settings' => ['panelizer' => ['enable' => TRUE]]];
    $storage->write($name, $data);
    $configs = new ConfigFactory($storage, new EventDispatcher(), $this->createMock(TypedConfigManagerInterface::class));
    $entities = $this->createMock(EntityTypeManagerInterface::class);
    $entities->expects($this->once())->method('getStorage')->with('node')
      ->willReturn($this->createMock(EntityStorageInterface::class));
    $migrator = new PanelizerMigrator($entities, $this->createMock(EntityFieldManagerInterface::class), $configs);

    [$status, $output, $logs] = $this->runVerification($migrator);
    $this->assertSame(1, $status);
    $this->assertSame([], $logs);
    $this->assertStringContainsString($name, $output);
    $this->assertSame([$name], $storage->listAll());
    $this->assertSame($data, $storage->read($name));
  }

  private function runVerification(PanelizerMigrator $migrator): array {
    $handler = new PanelizerMigrateCommands($migrator);
    $logger = $this->createMock(DrushLoggerManager::class);
    $logs = [];
    foreach (['success', 'warning'] as $level) {
      $logger->method($level)->willReturnCallback(static function () use (&$logs, $level): void {
        $logs[] = $level;
      });
    }
    $handler->setLogger($logger);
    $input = new ArrayInput([]);
    $input->setInteractive(FALSE);
    $output = new BufferedOutput();
    $handler->setInput($input);
    $handler->setOutput($output);
    // Use the real CLI attribute and return-code processing, without site hooks.
    $factory = new AnnotatedCommandFactory();
    $command = $factory->createCommand($factory->createCommandInfo($handler, 'verify'), $handler);
    $this->assertSame('update-to-d11:panelizer-verify', $command->getName());
    $status = $command->run($input, $output);
    return [$status, $output->fetch(), $logs];
  }

}
