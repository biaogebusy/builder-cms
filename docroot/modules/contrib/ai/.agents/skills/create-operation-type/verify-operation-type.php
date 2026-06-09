#!/usr/bin/env php
<?php

/**
 * @file
 * Verifies that a scaffolded operation type is complete and correct.
 *
 * Usage: php verify-operation-type.php <OperationName>
 * Example: php verify-operation-type.php ImageClassification.
 */

if ($argc < 2) {
  fwrite(STDERR, "Usage: php verify-operation-type.php <OperationName>\n");
  fwrite(STDERR, "Example: php verify-operation-type.php ImageClassification\n");
  exit(1);
}

$operationName = $argv[1];

// Validate PascalCase.
if (!preg_match('/^[A-Z][a-zA-Z0-9]+$/', $operationName)) {
  fwrite(STDERR, "ERROR: Operation name '$operationName' must be PascalCase (e.g. TextToImage).\n");
  exit(1);
}

// Determine base path - the script can be run from any location.
// Look for the src/OperationType directory relative to common locations.
$scriptDir = dirname(__FILE__);
// Navigate up from .agents/skills/create-operation-type/ to the module root.
$moduleRoot = realpath($scriptDir . '/../../..') ?: $scriptDir . '/../../..';

// If we can't find src/OperationType from the script location, try cwd.
if (!is_dir($moduleRoot . '/src/OperationType')) {
  $moduleRoot = getcwd();
}

if (!is_dir($moduleRoot . '/src/OperationType')) {
  fwrite(STDERR, "ERROR: Cannot find src/OperationType/ directory.\n");
  fwrite(STDERR, "Run this script from the AI module root or ensure the .agents directory is in the module root.\n");
  exit(1);
}

$srcDir = $moduleRoot . '/src/OperationType/' . $operationName;
$testDir = $moduleRoot . '/tests/src/Unit/OperationType/' . $operationName;

$errors = [];
$warnings = [];
$passes = [];

// ============================================================================
// Check 1: Interface file exists.
// ============================================================================
$interfaceFile = $srcDir . '/' . $operationName . 'Interface.php';
if (!file_exists($interfaceFile)) {
  $errors[] = "Interface file missing: $interfaceFile";
}
else {
  $passes[] = "Interface file exists: {$operationName}Interface.php";
  $interfaceContent = file_get_contents($interfaceFile);

  // Check 1a: Interface extends OperationTypeInterface.
  if (!preg_match('/interface\s+' . preg_quote($operationName) . 'Interface\s+extends\s+OperationTypeInterface\b/', $interfaceContent)) {
    $errors[] = "Interface must extend OperationTypeInterface.";
  }
  else {
    $passes[] = "Interface extends OperationTypeInterface.";
  }

  // Check 1b: #[OperationType] attribute exists.
  if (!preg_match('/#\[OperationType\s*\(/', $interfaceContent)) {
    $errors[] = "Missing #[OperationType] attribute on interface.";
  }
  else {
    $passes[] = "#[OperationType] attribute found.";

    // Check 1c: id uses only alphanumeric + underscore.
    if (preg_match('/#\[OperationType\s*\([^)]*id\s*:\s*[\'"]([^\'"]+)[\'"]/', $interfaceContent, $idMatch)) {
      $id = $idMatch[1];
      if (!preg_match('/^[a-z0-9_]+$/', $id)) {
        $errors[] = "OperationType id '$id' contains invalid characters. Must match [a-z0-9_] only (no dashes, no uppercase).";
      }
      else {
        $passes[] = "OperationType id '$id' is valid (alphanumeric + underscore).";
      }
    }
    else {
      $errors[] = "Could not parse 'id' from #[OperationType] attribute.";
    }

    // Check 1d: label uses TranslatableMarkup.
    if (preg_match('/label\s*:\s*new\s+TranslatableMarkup\s*\(/', $interfaceContent)) {
      $passes[] = "Label uses TranslatableMarkup.";
    }
    else {
      $errors[] = "Label must use 'new TranslatableMarkup(...)' in #[OperationType] attribute.";
    }
  }

  // Check 1e: Interface method references Input and Output classes.
  $inputClassName = $operationName . 'Input';
  $outputClassName = $operationName . 'Output';

  if (strpos($interfaceContent, $inputClassName) === FALSE) {
    $errors[] = "Interface does not reference {$inputClassName}. The primary method must accept the Input class.";
  }
  else {
    $passes[] = "Interface references {$inputClassName}.";
  }

  if (strpos($interfaceContent, $outputClassName) === FALSE) {
    $errors[] = "Interface does not reference {$outputClassName}. The primary method must return the Output class.";
  }
  else {
    $passes[] = "Interface references {$outputClassName}.";
  }
}

// ============================================================================
// Check 2: Input file exists.
// ============================================================================
$inputFile = $srcDir . '/' . $operationName . 'Input.php';
if (!file_exists($inputFile)) {
  $errors[] = "Input file missing: $inputFile";
}
else {
  $passes[] = "Input file exists: {$operationName}Input.php";
  $inputContent = file_get_contents($inputFile);

  // Check 2a: Extends InputBase.
  if (strpos($inputContent, 'extends InputBase') === FALSE) {
    $errors[] = "Input class must extend InputBase.";
  }
  else {
    $passes[] = "Input class extends InputBase.";
  }

  // Check 2b: Implements InputInterface.
  if (strpos($inputContent, 'implements InputInterface') === FALSE) {
    $errors[] = "Input class must implement InputInterface.";
  }
  else {
    $passes[] = "Input class implements InputInterface.";
  }

  // Check 2c: Has toString() method.
  if (!preg_match('/function\s+toString\s*\(/', $inputContent)) {
    $errors[] = "Input class must implement toString() method.";
  }
  else {
    $passes[] = "Input class has toString() method.";
  }

  // Check 2d: Has toArray() method.
  if (!preg_match('/function\s+toArray\s*\(/', $inputContent)) {
    $errors[] = "Input class must implement toArray() method.";
  }
  else {
    $passes[] = "Input class has toArray() method.";
  }
}

// ============================================================================
// Check 3: Output file exists.
// ============================================================================
$outputFile = $srcDir . '/' . $operationName . 'Output.php';
if (!file_exists($outputFile)) {
  $errors[] = "Output file missing: $outputFile";
}
else {
  $passes[] = "Output file exists: {$operationName}Output.php";
  $outputContent = file_get_contents($outputFile);

  // Check 3a: Implements OutputInterface.
  if (strpos($outputContent, 'implements OutputInterface') === FALSE) {
    $errors[] = "Output class must implement OutputInterface.";
  }
  else {
    $passes[] = "Output class implements OutputInterface.";
  }

  // Check 3b: Has getNormalized() method.
  if (!preg_match('/function\s+getNormalized\s*\(/', $outputContent)) {
    $errors[] = "Output class must implement getNormalized() method.";
  }
  else {
    $passes[] = "Output class has getNormalized() method.";
  }

  // Check 3c: Has getRawOutput() method.
  if (!preg_match('/function\s+getRawOutput\s*\(/', $outputContent)) {
    $errors[] = "Output class must implement getRawOutput() method.";
  }
  else {
    $passes[] = "Output class has getRawOutput() method.";
  }

  // Check 3d: Has getMetadata() method.
  if (!preg_match('/function\s+getMetadata\s*\(/', $outputContent)) {
    $errors[] = "Output class must implement getMetadata() method.";
  }
  else {
    $passes[] = "Output class has getMetadata() method.";
  }

  // Check 3e: Has toArray() method.
  if (!preg_match('/function\s+toArray\s*\(/', $outputContent)) {
    $errors[] = "Output class must implement toArray() method.";
  }
  else {
    $passes[] = "Output class has toArray() method.";
  }
}

// ============================================================================
// Check 4: Item/Response class (optional - detect if present).
// ============================================================================
$itemFiles = glob($srcDir . '/' . $operationName . '{Item,Response}.php', GLOB_BRACE);
$hasItemClass = !empty($itemFiles);

if ($hasItemClass) {
  foreach ($itemFiles as $itemFile) {
    $itemBasename = basename($itemFile, '.php');
    $passes[] = "Item/Response class found: {$itemBasename}.php";
    $itemContent = file_get_contents($itemFile);

    // Check it has a constructor.
    if (!preg_match('/function\s+__construct\s*\(/', $itemContent)) {
      $errors[] = "{$itemBasename} class should have a constructor.";
    }
    else {
      $passes[] = "{$itemBasename} class has a constructor.";
    }

    // Check the output references the item class.
    if (isset($outputContent) && strpos($outputContent, $itemBasename) === FALSE) {
      $warnings[] = "Output class does not reference {$itemBasename}. If the item class is used as normalized data, the output should reference it.";
    }

    // Check for unit test of item class.
    $itemTestFile = $testDir . '/' . $itemBasename . 'Test.php';
    if (!file_exists($itemTestFile)) {
      $errors[] = "Unit test missing for {$itemBasename}: {$itemTestFile}";
    }
    else {
      $passes[] = "Unit test exists for {$itemBasename}.";
    }
  }
}

// ============================================================================
// Check 5: Unit tests exist.
// ============================================================================
$inputTestFile = $testDir . '/' . $operationName . 'InputTest.php';
if (!file_exists($inputTestFile)) {
  $errors[] = "Unit test missing for Input: {$inputTestFile}";
}
else {
  $passes[] = "Unit test exists: {$operationName}InputTest.php";
  $inputTestContent = file_get_contents($inputTestFile);

  // Check test class structure.
  if (strpos($inputTestContent, 'extends TestCase') === FALSE) {
    $errors[] = "Input test must extend TestCase.";
  }

  if (strpos($inputTestContent, '@group ai') === FALSE) {
    $errors[] = "Input test must have @group ai annotation.";
  }
}

$outputTestFile = $testDir . '/' . $operationName . 'OutputTest.php';
if (!file_exists($outputTestFile)) {
  $errors[] = "Unit test missing for Output: {$outputTestFile}";
}
else {
  $passes[] = "Unit test exists: {$operationName}OutputTest.php";
  $outputTestContent = file_get_contents($outputTestFile);

  // Check test class structure.
  if (strpos($outputTestContent, 'extends TestCase') === FALSE) {
    $errors[] = "Output test must extend TestCase.";
  }

  if (strpos($outputTestContent, '@group ai') === FALSE) {
    $errors[] = "Output test must have @group ai annotation.";
  }
}

// ============================================================================
// Check 6: Kernel test exists.
// ============================================================================
$kernelTestDir = $moduleRoot . '/tests/src/Kernel/OperationType/' . $operationName;
$kernelTestFile = $kernelTestDir . '/' . $operationName . 'InterfaceTest.php';
if (!file_exists($kernelTestFile)) {
  $errors[] = "Kernel test missing: {$kernelTestFile}";
}
else {
  $passes[] = "Kernel test exists: {$operationName}InterfaceTest.php";
  $kernelTestContent = file_get_contents($kernelTestFile);

  // Check extends KernelTestBase.
  if (strpos($kernelTestContent, 'extends KernelTestBase') === FALSE) {
    $errors[] = "Kernel test must extend KernelTestBase.";
  }

  if (strpos($kernelTestContent, '@group ai') === FALSE) {
    $errors[] = "Kernel test must have @group ai annotation.";
  }

  // Check it references the output class.
  if (strpos($kernelTestContent, $operationName . 'Output') === FALSE) {
    $errors[] = "Kernel test must reference {$operationName}Output.";
  }
  else {
    $passes[] = "Kernel test references {$operationName}Output.";
  }

  // Check it uses the EchoAI provider.
  if (strpos($kernelTestContent, 'echoai') === FALSE) {
    $errors[] = "Kernel test must use the 'echoai' test provider.";
  }
  else {
    $passes[] = "Kernel test uses echoai provider.";
  }

  // Check correct namespace.
  $expectedKernelNs = 'namespace Drupal\\Tests\\ai\\Kernel\\OperationType\\' . $operationName . ';';
  if (strpos($kernelTestContent, $expectedKernelNs) === FALSE) {
    $errors[] = "Kernel test has incorrect namespace. Expected: $expectedKernelNs";
  }
}

// ============================================================================
// Check 7: EchoProvider supports the new operation type.
// ============================================================================
$echoProviderFile = $moduleRoot . '/tests/modules/ai_test/src/Plugin/AiProvider/EchoProvider.php';
if (file_exists($echoProviderFile)) {
  $echoContent = file_get_contents($echoProviderFile);

  // Check that it implements the new interface.
  if (strpos($echoContent, $operationName . 'Interface') === FALSE) {
    $errors[] = "EchoProvider does not implement {$operationName}Interface. Update the EchoProvider to support this operation type.";
  }
  else {
    $passes[] = "EchoProvider implements {$operationName}Interface.";
  }

  // Check that the operation type ID is in getSupportedOperationTypes().
  // Convert PascalCase to snake_case for the ID.
  $snakeId = strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', $operationName));
  if (strpos($echoContent, "'" . $snakeId . "'") === FALSE) {
    $errors[] = "EchoProvider getSupportedOperationTypes() does not include '{$snakeId}'.";
  }
  else {
    $passes[] = "EchoProvider registers '{$snakeId}' in getSupportedOperationTypes().";
  }
}
else {
  $warnings[] = "EchoProvider not found at expected path: {$echoProviderFile}";
}

// ============================================================================
// Check 8: Namespace correctness.
// ============================================================================
if (isset($interfaceContent)) {
  $expectedNamespace = 'namespace Drupal\\ai\\OperationType\\' . $operationName . ';';
  if (strpos($interfaceContent, $expectedNamespace) === FALSE) {
    $errors[] = "Interface has incorrect namespace. Expected: $expectedNamespace";
  }
}
if (isset($inputContent)) {
  $expectedNamespace = 'namespace Drupal\\ai\\OperationType\\' . $operationName . ';';
  if (strpos($inputContent, $expectedNamespace) === FALSE) {
    $errors[] = "Input class has incorrect namespace. Expected: $expectedNamespace";
  }
}
if (isset($outputContent)) {
  $expectedNamespace = 'namespace Drupal\\ai\\OperationType\\' . $operationName . ';';
  if (strpos($outputContent, $expectedNamespace) === FALSE) {
    $errors[] = "Output class has incorrect namespace. Expected: $expectedNamespace";
  }
}
if (isset($inputTestContent)) {
  $expectedTestNamespace = 'namespace Drupal\\Tests\\ai\\Unit\\OperationType\\' . $operationName . ';';
  if (strpos($inputTestContent, $expectedTestNamespace) === FALSE) {
    $errors[] = "Input test has incorrect namespace. Expected: $expectedTestNamespace";
  }
}
if (isset($outputTestContent)) {
  $expectedTestNamespace = 'namespace Drupal\\Tests\\ai\\Unit\\OperationType\\' . $operationName . ';';
  if (strpos($outputTestContent, $expectedTestNamespace) === FALSE) {
    $errors[] = "Output test has incorrect namespace. Expected: $expectedTestNamespace";
  }
}

// ============================================================================
// Report results.
// ============================================================================
echo "\n";
echo "========================================\n";
echo " Operation Type Verification: $operationName\n";
echo "========================================\n\n";

foreach ($passes as $pass) {
  echo "  PASS  $pass\n";
}

if (!empty($warnings)) {
  echo "\n";
  foreach ($warnings as $warning) {
    echo "  WARN  $warning\n";
  }
}

if (!empty($errors)) {
  echo "\n";
  foreach ($errors as $error) {
    echo "  FAIL  $error\n";
  }
  echo "\n";
  echo "RESULT: FAILED (" . count($errors) . " error(s), " . count($warnings) . " warning(s), " . count($passes) . " passed)\n";
  exit(1);
}
else {
  echo "\n";
  echo "RESULT: ALL CHECKS PASSED (" . count($passes) . " passed, " . count($warnings) . " warning(s))\n";
  exit(0);
}
