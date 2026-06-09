<?php

namespace Drupal\ai\Service;

use Drupal\ai\Entity\AiPrompt;
use Drupal\ai\Entity\AiPromptInterface;
use Drupal\ai\Entity\AiPromptType;
use Drupal\ai\Entity\AiPromptTypeInterface;
use Drupal\Component\Serialization\Yaml;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ExtensionPathResolver;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Symfony\Component\Finder\Finder;

/**
 * Management of the Prompts and Prompt Types.
 */
class AiPromptManager {

  /**
   * Constructs a new AI Prompt Manager.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   * @param \Drupal\Core\Extension\ExtensionPathResolver $extensionPathResolver
   *   The extension path resolver.
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $loggerFactory
   *   The logger factory.
   */
  public function __construct(
    protected EntityTypeManagerInterface $entityTypeManager,
    protected ExtensionPathResolver $extensionPathResolver,
    protected LoggerChannelFactoryInterface $loggerFactory,
  ) {
  }

  /**
   * Get AI Prompt Type entities.
   *
   * @return array
   *   The available type entities.
   */
  public function getTypes(): array {
    return $this->entityTypeManager
      ->getStorage('ai_prompt_type')
      ->loadMultiple();
  }

  /**
   * Get AI Prompt Types as options.
   *
   * @return array
   *   The available types as an options list.
   */
  public function getTypeOptions(): array {
    $options = [];
    foreach ($this->getTypes() as $type) {
      $options[$type->id()] = $type->label();
    }
    return $options;
  }

  /**
   * Get all Prompts matching given type IDs.
   *
   * @param array $types
   *   The prompt type IDs allowed.
   *
   * @return array
   *   An array of all matching prompts.
   */
  public function getPromptsByTypes(array $types): array {
    $storage = $this->entityTypeManager->getStorage('ai_prompt');
    $query = $storage->getQuery();
    $query->accessCheck();
    $query->condition('type', $types);
    $query->sort('label');
    $prompts = $query->execute();
    if ($prompts) {
      return $storage->loadMultiple($prompts);
    }
    return [];
  }

  /**
   * Inserts or updates prompt types from a module's /config/install directory.
   *
   * @param string $module
   *   The machine name of the module.
   */
  public function upsertFromConfigInstall(string $module): void {
    $path = $this->extensionPathResolver->getPath('module', $module) . '/config/install';
    $this->upsertFromPath($path);
  }

  /**
   * Inserts or updates prompt types from a given file path.
   *
   * @param string $path
   *   A file system path to the folder containing ai_prompt_type.*.yml files.
   */
  public function upsertFromPath(string $path): void {
    if (!is_dir($path)) {
      return;
    }

    // Find all prompt type files and install, followed by prompts, and install.
    foreach (['ai_prompt_type', 'ai_prompt'] as $type) {
      $finder = new Finder();
      $finder->files()
        ->in($path)
        ->name('ai.' . $type . '.*.yml');
      foreach ($finder as $file) {
        try {
          $contents = file_get_contents($file->getRealPath());
          $data = Yaml::decode($contents);
          if (isset($data['id'])) {
            if ($type === 'ai_prompt_type') {
              $this->upsertPromptType($data);
            }
            elseif ($type === 'ai_prompt') {
              $this->upsertPrompt($data);
            }
          }
        }
        catch (\Exception $error) {
          $this->loggerFactory->get('ai')->error('Failed to import prompt type from @file: @message', [
            '@file' => $file->getFilename(),
            '@message' => $error->getMessage(),
          ]);
        }
      }
    }
  }

  /**
   * This creates or updates a prompt type using the 'id' as a merge key.
   *
   * @param array $prompt_type_settings
   *   The settings to use.
   *
   * @return \Drupal\ai\Entity\AiPromptTypeInterface
   *   The prompt type.
   */
  public function upsertPromptType(array $prompt_type_settings): AiPromptTypeInterface {
    $prompt_type = $this->entityTypeManager
      ->getStorage('ai_prompt_type')
      ->load($prompt_type_settings['id']);
    if (!$prompt_type instanceof AiPromptTypeInterface) {
      $prompt_type = AiPromptType::create($prompt_type_settings);
    }

    // Update values.
    unset($prompt_type_settings['id']);
    foreach ($prompt_type_settings as $key => $value) {
      $prompt_type->set($key, $value);
    }
    $prompt_type->save();
    return $prompt_type;
  }

  /**
   * This creates or updates a prompt using the 'id' as a merge key.
   *
   * @param array $prompt_settings
   *   The settings to use.
   *
   * @return \Drupal\ai\Entity\AiPromptInterface
   *   The prompt type.
   */
  public function upsertPrompt(array $prompt_settings): AiPromptInterface {
    $prompt = $this->entityTypeManager
      ->getStorage('ai_prompt')
      ->load($prompt_settings['id']);
    if (!$prompt instanceof AiPromptInterface) {
      $prompt = AiPrompt::create($prompt_settings);
    }

    // Update values.
    unset($prompt_settings['id']);
    foreach ($prompt_settings as $key => $value) {
      $prompt->set($key, $value);
    }

    $prompt->save();
    return $prompt;
  }

}
