<?php

namespace Drupal\cohesion_sync\Drush;

use Drupal\cohesion\Entity\CohesionSettingsInterface;
use Drupal\Core\Config\Entity\ConfigEntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\cohesion_sync\PackagerManager;
use Drupal\Core\Entity\EntityRepository;
use Drupal\Core\Config\ConfigFactoryInterface;
use \Drupal\Core\Config\ImmutableConfig;
use Drupal\Core\Config\FileStorageFactory;

const DX8_SYNC_DIRECTORY = 'dx8_sync';

/**
 * Class CommandHelpers
 *
 * @package Drupal\cohesion_sync\Drush
 */
final class CommandHelpers {

  protected static $instance = NULL;

  /**
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * @var \Drupal\Core\Config\ConfigFactory
   */
  protected $configFactory;

  /**
   * @var \Drupal\cohesion_sync\PackagerManager
   */
  protected $packagerManager;

  /**
   * @var \Drupal\Core\Entity\EntityRepository
   */
  protected $entityRepository;

  /**
   * @var \Drupal\Core\Config\Config|\Drupal\Core\Config\ImmutableConfig
   */
  protected $config;

  /**
   * DrushCommandHelpers constructor.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   * @param \Drupal\cohesion_sync\PackagerManager $packagerManager
   * @param \Drupal\Core\Entity\EntityRepository $entityRepository
   */
  public function __construct(EntityTypeManagerInterface $entityTypeManager, ConfigFactoryInterface $configFactory, PackagerManager $packagerManager, EntityRepository $entityRepository) {
    $this->entityTypeManager = $entityTypeManager;
    $this->configFactory = $configFactory;
    $this->packagerManager = $packagerManager;
    $this->entityRepository = $entityRepository;

    /** @var ImmutableConfig config */
    $this->config = $this->configFactory->get('cohesion.sync.settings');
  }

  /**
   * @return string
   */
  private function getExportFilename() {
    // Get a filename safe verison of the site name.
    $site_name = preg_replace('/[^a-z0-9]+/', '-', strtolower(\Drupal::config('system.site')->get('name')));
    return $site_name . '.package.yml_';
  }

  /**
   * Perform the export. Return success message.
   *
   * @param $filename_prefix
   *
   * @return bool|string
   * @throws \Exception
   */
  public function exportAll($filename_prefix = FALSE) {
    // Make sure the config sync directory has been set.
    try {
      $dir = config_get_config_directory(DX8_SYNC_DIRECTORY);
    } catch (\Throwable $e) {
      $dir = 'sites/default/files/sync';
    }

    // Get the enabled entity types.
    $enabled_entity_types = $this->config->get('enabled_entity_types');
    if (!is_array($enabled_entity_types)) {
      throw new \Exception('Export settings have not been defined (enabled_entity_types configuration not found). Visit: /admin/cohesion/sync/export_settings to configure package export.');
    }

    // Build the excluded entity types up.
    $excluded_entity_type_ids = [];
    foreach ($enabled_entity_types as $entity_type_id => $enabled) {
      if (!$enabled) {
        $excluded_entity_type_ids[] = $entity_type_id;
      }
    }

    // Loop over each DX8 entity type to get all the entities.
    $entities = [];
    foreach ($this->entityTypeManager->getDefinitions() as $entity_type => $definition) {
      if ($definition->entityClassImplements(CohesionSettingsInterface::class) && !in_array($entity_type, $excluded_entity_type_ids) && $entity_type !== 'custom_style_type') {
        try {
          $entity_storage = $this->entityTypeManager->getStorage($entity_type);
        } catch (\Exception $e) {
          continue;
        }

        /** @var ConfigEntityInterface $entity */
        foreach ($entity_storage->loadMultiple() as $entity) {
          if ($entity->status()) {
            $entities[] = $entity;
          }
        }
      }
    }

    // Check there are entities.
    if (!count($entities)) {
      return 'No DX8 entities were found. Nothing was exported.';
    }

    // Prepare the directory.
    if (!file_prepare_directory($dir, FILE_CREATE_DIRECTORY)) {
      throw new \Exception('Unable to prepare directory: ' . $dir);
    }

    // Build the filename.
    if ($filename_prefix) {
      $filename = $filename_prefix . '.package.yml_';
    }
    else {
      $filename = $this->getExportFilename();
    }

    // Save the file.
    $file_destination = $dir . '/' . $filename;
    $fp = fopen($file_destination, 'w');

    // Use the Yaml generator to stream the output to the file (buildPackageStream() yields).
    $counter = 0;
    foreach ($this->packagerManager->buildPackageStream($entities, TRUE, $excluded_entity_type_ids) as $yaml) {
      fwrite($fp, $yaml);
      $counter++;
    }

    fclose($fp);

    return 'Exported ' . $counter . ' items to ' . $file_destination;
  }

  /**
   * @param $overwrite
   * @param $keep
   * @param $path
   *
   * @return string
   * @throws \Exception
   */
  public function import($overwrite, $keep, $path) {
    $paths = [];
    $messages = [];

    // Path was specified by the user.
    if ($path !== NULL) {
      $paths[] = $path;
    }
    // Full import (no paths specified, so look up from settings.php)
    else {
      // For a full import, set the site to maintenance mode.
      \Drupal::state()->set('system.maintenance_mode', TRUE);

      // Make sure the config sync directory has been set.
      try {
        $dir = config_get_config_directory(DX8_SYNC_DIRECTORY);
      } catch (\Throwable $e) {
        $dir = 'sites/default/files/sync';
      }

      file_scan_directory($dir, '/.package.yml_$/', [
        'callback' => function ($file) use (&$paths, &$messages) {
          $paths[] = $file;
        },
      ]);

      if (empty($paths)) {
        $messages[] = 'No *.package.yml_ files found in ' . $dir;
      }
    }

    // Import each file in the paths list.
    foreach ($paths as $path) {
      try {
        $action_data = $this->packagerManager->validateYamlPackageStream($path);
      } catch (\Exception $e) {
        \Drupal::state()->set('system.maintenance_mode', FALSE);
        throw new \Exception('Error in ' . $path . ' ' . $e->getMessage());
      }

      // Set the status of the action items.
      foreach ($action_data as $uuid => $action) {
        if ($action['entry_action_state'] == ENTRY_EXISTING_ASK) {
          if ($overwrite) {
            $action_data[$uuid]['entry_action_state'] = ENTRY_EXISTING_OVERWRITTEN;
          }
          if ($keep) {
            $action_data[$uuid]['entry_action_state'] = ENTRY_EXISTING_IGNORED;
          }
        }
      }

      // Process the action items.
      $applied_count = $this->packagerManager->applyYamlPackageStream($path, $action_data);

      $messages[] = 'Imported ' . $applied_count . ' items from package: ' . $path;
    }


    \Drupal::state()->set('system.maintenance_mode', FALSE);
    return implode("\n", $messages);
  }
}
