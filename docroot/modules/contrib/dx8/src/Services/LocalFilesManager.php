<?php

namespace Drupal\cohesion\Services;

use Drupal\Component\PhpStorage\FileStorage;

/**
 * Class LocalFilesManager
 *
 * Helper service used to move local cohesion:// files around for entity save / dx8:import.
 *
 * \Drupal::service('cohesion.local_files_manager')
 *
 * @package Drupal\cohesion\Helper
 */
class LocalFilesManager {

  /**
   * Flush the css dummy query string parameter (forces browser reload).
   */
  public function refreshCaches() {
    \Drupal::service('asset.css.collection_optimizer')->deleteAll();
    \Drupal::service('asset.js.collection_optimizer')->deleteAll();

    // Change the js/css cache buster.
    _drupal_flush_css_js();
    \Drupal::service('cache.data')->deleteAll();
  }

  /**
   * Copy the live stylesheet.json to temporary:// so styles don't get wiped
   * when re-importing.
   */
  public function liveToTemp() {
    foreach (\Drupal::service('cohesion.utils')->getCohesionEnabledThemes() as $theme_info) {
      $from = $this->getStyleSheetFilename('json', $theme_info->getName(), TRUE);
      $to = $this->getStyleSheetFilename('json', $theme_info->getName());
      if (file_exists($from)) {
        file_unmanaged_move($from, $to, FILE_EXISTS_REPLACE);
      }
    }
  }

  /**
   * Copy the *.tmp.* files to live.
   */
  public function tempToLive() {

    foreach (\Drupal::service('cohesion.utils')->getCohesionEnabledThemes() as $theme_info) {

      $styles = ['json', 'base', 'theme', 'grid', 'icons'];

      foreach ($styles as $style) {
        $from = $this->getStyleSheetFilename($style, $theme_info->getName());
        $to = $this->getStyleSheetFilename($style, $theme_info->getName(), TRUE);
        if (file_exists($from)) {
          // Copy the file.
          file_unmanaged_move($from, $to, FILE_EXISTS_REPLACE);
        }
      }
    }

    // Clean up.
    $this->refreshCaches();
  }

  /**
   * Move temporary template to cohesion template directory
   *
   * @return boolean
   */
  public function moveTemporaryTemplateToLive() {
    // Create cohesion:// templates if it doesn't exist.
    if (!file_exists(COHESION_TEMPLATE_PATH)) {
      \Drupal::service('file_system')->mkdir(COHESION_TEMPLATE_PATH, 0777, FALSE);
    }

    $files = [];
    if (($templates = \Drupal::keyValue('cohesion.temporary_template')->get('temporary_templates', []))) {

      foreach ($templates as $temp_template) {
        $template_file = COHESION_TEMPLATE_PATH . '/' . basename($temp_template);

        if (!file_exists($temp_template)) {
          continue;
        }

        if (($file = file_unmanaged_move($temp_template, $template_file, FILE_EXISTS_REPLACE))) {
          $files[] = $file;
        }
        else {
          drupal_set_message(t('Error moving @file', ['@file' => $temp_template]), 'error');
        }
      }

      // Reset temporary template list
      \Drupal::keyValue('cohesion.temporary_template')->set('temporary_templates', []);
    }

    // Refresh some caches.
    drupal_flush_all_caches();

    return !empty($files) ? TRUE : FALSE;
  }

  /**
   * Return different filenames depending if the user is rebuilding.
   *
   * @param string $type
   * @param string $theme_id
   * @param bool $force_clean_filename
   *
   * @return string
   */
  public function getStyleSheetFilename($type, $theme_id = '', $force_clean_filename = FALSE) {

    $theme_filemane = str_replace('_', '-', $theme_id);

    $cohesion_uris = [
      'json' => "{$theme_filemane}-stylesheet.json",
      'base' => "base/{$theme_filemane}-stylesheet.min.css",
      'theme' => "theme/{$theme_filemane}-stylesheet.min.css",
      'grid' => "cohesion-responsive-grid-settings.css",
      'icons' => "cohesion-icon-libraries.css",
    ];

    $tmp_uris = [
      'json' => "{$theme_filemane}-stylesheet.json",
      'base' => "{$theme_filemane}-base-stylesheet.min.css",
      'theme' => "{$theme_filemane}-theme-stylesheet.min.css",
      'grid' => 'cohesion-responsive-grid-settings.css',
      'icons' => 'cohesion-icon-libraries.css',
    ];


    if (array_key_exists($type, $cohesion_uris) && array_key_exists($type, $tmp_uris)) {
      $filename = '';
      $running_dx8_batch = &drupal_static('running_dx8_batch');
      if (!$running_dx8_batch || $force_clean_filename) {
        $filename .= COHESION_CSS_PATH . '/' . $cohesion_uris[$type];
      }
      else {
        $filename .= $this->scratchDirectory() . '/' . $tmp_uris[$type];
      }

      return $filename;
    }
  }

  /**
   * Return a temp directory inside cohesion://
   * This is used because of unpredictable behavior of the /tmp diretory on
   * Pantheon and Acquia hosting.
   *
   * @return string
   */
  public function scratchDirectory() {
    $cohesion_scratch_path = COHESION_FILESYSTEM_URI . 'scratch';

    // If the scratch directory doesn't exist, create it.
    if (!file_exists($cohesion_scratch_path)) {
      // Create the directory.
      \Drupal::service('file_system')->mkdir($cohesion_scratch_path, 0777, FALSE);

      // Add a .htaccess file.
      file_unmanaged_save_data(FileStorage::htaccessLines(TRUE), $cohesion_scratch_path . '/.htaccess', FILE_EXISTS_REPLACE);
    }

    return $cohesion_scratch_path;
  }

  /**
   * Clean the scratch directory ahead of a dx8:import or dx8:rebuild.
   */
  public function resetScratchDirectory() {
    // Delete the directory.
    if (file_exists($this->scratchDirectory())) {
      file_unmanaged_delete_recursive($this->scratchDirectory());
    }

    // Recreate it blank.
    $this->scratchDirectory();
  }

  /**
   * Delete a file by URI checking if it's a managed file or not first
   *
   * @param $uri - the uri of the file
   *
   * @return bool
   */
  public function deleteFileByURI($uri) {
    $files = \Drupal::entityTypeManager()->getStorage('file')->loadByProperties(['uri' => $uri]);
    if ($file = reset($files)) {
      return $file->delete();
    }
    else {
      return file_unmanaged_delete($uri);
    }
  }

  /**
   * This recusrively scans a decoded JSON object for temporary:// files and
   * moves them to the cohesion:// directory.
   * It patches the object paths with the new URIs.
   *
   * @param $obj
   */
  public function moveTemporaryFiles(&$obj) {
    if (is_object($obj)) {
      foreach ($obj as $property => $value) {
        $file = $this->resolveTemporaryFile($value);
        if ($file) {
          $obj = $file;
        }
        else {
          $this->moveTemporaryFiles($obj->$property);
        }
      }
    }
    else {
      if (is_array($obj)) {
        foreach ($obj as $key => $value) {
          $file = $this->resolveTemporaryFile($value);
          if ($file) {
            $obj = $file;
          }
          else {
            $this->moveTemporaryFiles($obj[$key]);
          }
        }
      }
    }
  }

  /**
   * This scans a variable for a temporary file path, if found it creates a
   * permanent file in cohesion://
   * Note, this does NOT set the core file usage because the FileUsage plugin
   * does this on entity postSave().
   *
   * @param $tmp_file
   *
   * @return bool|\stdClass
   */
  private function resolveTemporaryFile($tmp_file) {
    $temp_folder = $this->scratchDirectory();
    $tmp_pattern = "#" . $temp_folder . "/[a-zA-Z0-9-_+]+\.[a-zA-Z0-9-_+]+#";
    if (is_string($tmp_file) && preg_match($tmp_pattern, $tmp_file)) {
      $tmp_file = str_replace('"', '', $tmp_file);
      $file_get = file_get_contents($tmp_file);
      $filename = basename($tmp_file);
      $filename = preg_replace("/[^a-zA-Z0-9-_.]/", "", basename($filename));
      $file = file_save_data($file_get, 'cohesion://' . $filename);
      if ($file) {
        @ unlink($tmp_file);
        $return_object = new \stdClass();
        $return_object->type = 'file';
        $return_object->uri = $file->getFileUri();
        $return_object->uuid = $file->uuid();
        return $return_object;
      }
    }
    return FALSE;
  }
}
