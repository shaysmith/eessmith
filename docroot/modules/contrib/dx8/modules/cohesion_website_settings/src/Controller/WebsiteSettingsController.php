<?php

namespace Drupal\cohesion_website_settings\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Session\AccountInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Drupal\Component\Serialization\Json;
use Symfony\Component\HttpFoundation\Response;
use Drupal\cohesion\CohesionJsonResponse;
use Drupal\Core\Url;
use Drupal\cohesion\Entity\CohesionConfigEntityBase;

/**
 * Class WebsiteSettingsController
 *
 * Returns responses for WebsiteSettings routes.
 *
 * @package Drupal\cohesion_website_settings\Controller
 */
class WebsiteSettingsController extends ControllerBase implements ContainerInjectionInterface {

  /**
   * POST: /admin/cohesion/upload/font_libraries
   *
   * @param Request $request
   *
   * @return Response json response data
   * Callback from angular form that responds what fonts are included in the
   *   zip, and unzip it to a temporary directory, and return a response to
   *   angular to highlight which fonts are in there. Sets an "updated" flag in
   *   the json if the font has been updated so on entity save it knows to
   *   handle a change in the font files
   */
  public function fontLibrariesPostCallback(Request $request) {
    $response = new Response();
    $response->headers->set('Content-Type', 'application/json');

    $accepted_types = [
      'application/zip',
      'application/x-zip-compressed',
      'multipart/x-zip',
      'application/x-compressed',
      'application/octet-stream',
    ];
    $accepted_extensions = ['eot', 'ttf', 'woff', 'woff2'];
    $temp_folder = \Drupal::service('cohesion.local_files_manager')->scratchDirectory();
    $file = $request->files->get("file");
    // Move uploaded ZIP file to temp directory if valid
    if ($file && !$file->getError() && in_array($file->getMimeType(), $accepted_types) && pathinfo($file->getClientOriginalName(), PATHINFO_EXTENSION) == "zip") {
      $filename = $file->getClientOriginalName();
      $file->move($temp_folder, $filename);

      $zip = new \ZipArchive();
      $return = [];
      foreach ($accepted_extensions as $extension) {
        $return[$extension] = NULL;
      }

      $real_path = \Drupal::service('file_system')->realpath($temp_folder . "/" . $filename);
      if ($zip->open($real_path) === TRUE) {
        for ($i = 0; $i < $zip->numFiles; $i++) {
          $fontname = $zip->getNameIndex($i);
          if (strpos($fontname, "_MAC") > 0) {
            continue;
          }
          $fontname = preg_replace("/[^a-zA-Z0-9-_.]/", "+", basename($fontname));
          $ext = pathinfo($fontname, PATHINFO_EXTENSION);
          // Save each font file in the temp directory
          if (in_array($ext, $accepted_extensions)) {
            $font = $zip->getFromIndex($i);

            $file_destination = $temp_folder . "/" . $fontname;
            $res = file_unmanaged_save_data($font, $file_destination);
            if ($res) {
              $return[$ext]['uri'] = '"' . $file_destination . '"';
              unset($accepted_extensions[$ext]);
            }
          }
        }
        $zip->close();
        unlink($temp_folder . "/" . $filename);
      }
      else {

        \Drupal::logger('api-call-error')->error(t("Error occurred while uploading the file."));
        $response->setStatusCode(400);
        $return = (object) [
          "message" => "Cohesion API",
          "error" => t("Error occurred while uploading the file."),
        ];
      }
    }
    else {

      if ($file && $file->getError()) {
        $message = $file->getErrorMessage();
      }
      else {
        $message = t("Error occurred while uploading the file.");
      }

      \Drupal::logger('api-call-error')->error($message);
      $response->setStatusCode(400);
      $return = (object) [
        "message" => "Cohesion API",
        "error" => $message,
      ];
    }
    $response->setContent(Json::encode($return));
    return $response;
  }

  /**
   * @return string
   */
  protected function getFileScheme() {
    return COHESION_STREAM_WRAPPER_NAME;
  }

  /**
   * @param \Symfony\Component\HttpFoundation\Request $request
   *
   * @return \Symfony\Component\HttpFoundation\Response
   */
  public function fileUploadPostCallback(Request $request) {
    $response = new Response();
    $response->headers->set('Content-Type', 'application/json');

    // Get the type of storage request (managed or unmanaged).
    $storage_type_managed = ($request->get('managed')) ? TRUE : FALSE;

    // Carry on.
    $accepted_types = ['application/octet-stream'];
    $accepted_extensions = ['jpg', 'jpeg', 'png', 'svg', 'gif'];
    $file = $request->files->get("file");

    // Check a file was uploaded.
    if ($file && in_array($file->getMimeType(), $accepted_types) && in_array(strtolower($file->getClientOriginalExtension()), $accepted_extensions)) {
      $filename = $file->getClientOriginalName();
      $temp_folder = file_directory_temp();

      $file->move($temp_folder, $filename);
      $temp_path = $temp_folder . '/' . $filename;
      if (file_exists($temp_path)) {
        $contents = file_get_contents($temp_path);
        try {
          // Perform a managed save.
          if ($storage_type_managed) {
            if ($file_managed = file_save_data($contents, $this->getFileScheme() . "://" . $filename)) {

              // And return the file ID and path.
              $return = (object) [
                "json" => $file_managed->id(),
                "path" => file_create_url($file_managed->getFileUri()),
              ];
            }
          } // Perform an unmanaged save.
          else {
            $file_unmanaged = file_unmanaged_save_data($contents, $this->getFileScheme() . "://" . $filename);

            // Get the file path.
            if (strpos($file_unmanaged, DRUPAL_ROOT) === 0) {
              $file_path = substr($file_unmanaged, strlen(DRUPAL_ROOT));
            }
            else {
              $file_path = $file_unmanaged;
            }

            // And return the data.
            $return = (object) [
              "json" => $file_path,
            ];
          }
        } catch (FileException $e) {
          $return = (object) [
            "message" => "Cohesion API",
            "error" => t("Error occured while uploading the file."),
          ];
          \Drupal::logger('api-call-error')->error(t("Error occured while uploading the file."));
        }
      }
      else {
        \Drupal::logger('api-call-error')->error(t("Error occured while uploading the file."));
        $return = (object) [
          "message" => "Cohesion API",
          "error" => t("Error occured while uploading the file."),
        ];
      }
      unlink($temp_folder . "/" . $filename);
    }
    else {
      \Drupal::logger('api-call-error')->error(t("Error occured while uploading the file."));
      $return = (object) [
        "message" => "Cohesion API",
        "error" => t("Error occured while uploading the file."),
      ];
    }

    $response->setContent(Json::encode($return));
    return $response;
  }

  /**
   * User uploaded an icon JSON file via the Angular form. Returns the path to
   * the converted JSON file.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *
   * @return \Symfony\Component\HttpFoundation\Response
   */
  public function iconLibrariesPostCallback(Request $request) {
    $response = new Response();
    $response->headers->set('Content-Type', 'application/json');

    $accepted_types = ['application/octet-stream'];
    $file = $request->files->get("file");

    // Check a file was uploaded.
    if ($file && in_array($file->getMimeType(), $accepted_types)) {


      $icons = \Drupal::service('cohesion.icon_interpreter')->sendToApi(file_get_contents($file->getPathname()));

      if ($icons['code'] == 200) {
        $contents = isset($icons['data']) ? json_encode($icons['data'], JSON_PRETTY_PRINT) : '';
      }
      else {
        $return = [
          "message" => "Cohesion API",
          "error" => t("Invalid icon library loaded"),
        ];
        \Drupal::logger('api-call-error')->error(t("Error: Invalid icon library loaded"));
        $response->setContent(Json::encode($return));
        return $response;
      }

      if (is_array(Json::decode($contents))) {
        $filename = $file->getClientOriginalName();
        try {
          // $file->move($destination_folder, $filename );
          $file_unmanaged = file_unmanaged_save_data($contents, COHESION_FILESYSTEM_URI . $filename);
          if (strpos($file_unmanaged, DRUPAL_ROOT) === 0) {
            $file_path = substr($file_unmanaged, strlen(DRUPAL_ROOT));
          }
          else {
            $file_path = $file_unmanaged;
          }

          $return = (object) [
            // "json" => $this->getFileScheme() . "://" . basename($file_unmanaged),
            "json" => $file_path,
          ];
        } catch (FileException $e) {
          $return = (object) [
            "message" => "Cohesion API",
            "error" => t("Error occured while uploading the file."),
          ];
          \Drupal::logger('api-call-error')->error(t("Error occured while uploading the file."));
        }
      }
      else {
        \Drupal::logger('api-call-error')->error(t("Error occured while uploading the file."));
        $return = (object) [
          "message" => "Cohesion API",
          "error" => t("Error occured while uploading the file."),
        ];
      }
    }
    else {
      \Drupal::logger('api-call-error')->error(t("Error occured while uploading the file."));
      $return = (object) [
        "message" => "Cohesion API",
        "error" => t("Error occured while uploading the file."),
      ];
    }

    $response->setContent(Json::encode($return));
    return $response;
  }

  /**
   * GET: /cohesionapi/main/{type}
   *
   * @param Request $request
   *
   * @return Response json response data
   * Endpoint to return one of the website settings library, color - font - icon
   */
  public function libraryAction(Request $request) {
    // Get the type of website setting from the request.
    $type = ($request->get('type')) ? $request->get('type') : NULL;
    $item = ($request->get('item')) ? $request->get('item') : NULL;

    $error = FALSE;
    $content = [];
    $status = 200;

    // Get the content.
    switch ($type) {
      case 'icon_libraries':
      case 'font_libraries':
      case 'responsive_grid_settings':
      case 'system_font':
        $content = \Drupal::service('settings.endpoint.utils')->getEndpointLibraries($type);
        break;

      case 'color_palette':
        $content = \Drupal::service('settings.endpoint.utils')->getColorsList($item);

        $content = $item ? array_pop($content) : array_values($content);
        if (!$content) {
          $status = 404;
          $error = TRUE;
        }
        break;

      default:
        $status = 400;
        $error = TRUE;
        break;
    }

    // Send response.
    return new CohesionJsonResponse([
      'status' => !$error ? 'success' : 'error',
      'data' => $content,
    ], $status);
  }

  /**
   * Filter the sidebar elements list by drupalSettings.cohesion.entityTypeId
   *
   * @param $data
   * @param $entity_type_id
   *
   * @return mixed
   */
  private function filterByEntityTypeId($data, $entity_type_id) {
    // Get the exclusion list.
    $excludes = \Drupal::keyValue('cohesion.assets.static_assets')->get('sidebar-elements-exclude');

    // Strip elements.
    if ($excludes == NULL || !isset($excludes[$entity_type_id])) {
      $entity_type_id = 'default';
    }

    foreach ($data as $category_index => $category) {
      foreach ($category['children'] as $child_index => $child) {
        if (in_array($child['uid'], $excludes[$entity_type_id])) {
          unset($data[$category_index]['children'][$child_index]);
        }
      }
      // Rebase keys.
      $data[$category_index]['children'] = array_values($data[$category_index]['children']);
    }

    // Remove empty categories.
    foreach ($data as $category_index => $category) {
      if (empty($category['children'])) {
        unset($data[$category_index]);
      }
    }

    // Return the patched list with rebased keys.
    return array_values($data);
  }

  /**
   * Filter elments by permissions
   *
   * @param $data
   * @param $entity_type_id
   *
   * @return mixed
   */
  private function filterByElementsPermissions($data) {

    $config = $this->config('cohesion.settings');
    $perms = ($config && $config->get('elements_permissions')) ? $config->get('elements_permissions') : "{}";
    $perms = Json::decode($perms);

    foreach ($data as $key => $element) {
      $perm = FALSE;
      foreach ($this->currentUser()->getRoles() as $role) {
        if (!isset($perms[$role][$element['uid']]) && $role != AccountInterface::ANONYMOUS_ROLE || isset($perms[$role][$element['uid']]) && $perms[$role][$element['uid']] == 1) {
          $perm = TRUE;
          break;
        }
      }
      if (!$perm) {
        unset($data[$key]);
      }
    }

    $data = array_values($data);

    return $data;
  }

  /**
   * GET: /cohesionapi/element/{group}/{type}
   * Retrieve one or all elements from asset storage group. Endpoint.
   *
   * Request should include {group} and {type} parameters.
   * If {type} is omitted, it is assumed all assets of that group are desired.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *
   * @return CohesionJsonResponse
   */
  public function elementAction(Request $request) {
    $group = ($request->get('group')) ? $request->get('group') : NULL;
    $assetLibrary = $this->keyValue('cohesion.assets.' . $group);
    $type = $request->get('type');
    $with_categories = $request->query->get('withcategories');
    $entity_type_id = $request->query->get('entityTypeId');

    list($error, $data, $message) = \Drupal::service('settings.endpoint.utils')->getAssets($assetLibrary, $type, $group, $with_categories);

    if ($group == 'elements') {
      if ($with_categories) {
        foreach ($data['categories'] as $key => $category) {
          $children = $this->filterByElementsPermissions($category['children']);
          if (empty($children)) {
            unset($data['categories'][$key]);
          }
          else {
            $data['categories'][$key]['children'] = $this->filterByElementsPermissions($category['children']);
          }
        }
      }
      else {
        $data = $this->filterByElementsPermissions($data);
      }
    }

    // Filter the elements list by drupalSettings.cohesion.entityTypeId
    if ($entity_type_id) {
      $data['categories'] = $this->filterByEntityTypeId($data['categories'], $entity_type_id);
    }

    if (isset($data['categories']) && is_array($data['categories'])) {
      $data['categories'] = array_values($data['categories']);
    }

    // Return the (optionally) patched results.
    return new CohesionJsonResponse([
      'status' => !$error ? 'success' : 'error',
      'data' => $data,
    ]);
  }

  /**
   * GET: /cohesionapi/element
   * Return all key_value collections matching cohesion
   * (SELECT DISTINCT collection FROM key_value WHERE collection LIKE
   * "cohesion.assets.%";)
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *
   * @return CohesionJsonResponse
   */
  public function elementActionAll(Request $request) {

    $data = [];

    foreach ($this->elementCollection() as $collection) {
      $base_name = str_replace('cohesion.assets.', '', $collection->collection);

      // Using the status /Drupal:keyValue here instead of the one from the
      // collection so the base collection can be modified in the loop.
      $assetLibrary = \Drupal::keyValue($collection->collection);
      list($error, $group_data, $message) = \Drupal::service('settings.endpoint.utils')->getAssets($assetLibrary, '__ALL__', $base_name, FALSE);

      // Patch in any custom element data.
      switch ($base_name) {
        case 'elements':
          $group_data = \Drupal::service('custom.elements')->patchElementList($group_data);
          break;

        case 'element_forms':
          $group_data = \Drupal::service('custom.elements')->patchElementBuilderForms($group_data);
          break;

        case 'form_defaults':
          $group_data = \Drupal::service('custom.elements')->patchFormDefaults($group_data);
          break;

        case 'element_properties':
          $group_data = \Drupal::service('custom.elements')->patchElementProperties($group_data);
          break;

        case 'property_group_options':
          $group_data = \Drupal::service('custom.elements')->patchProperyGroupOptions($group_data);
          break;

        case 'static_assets':
          $group_data['api-urls'] = \Drupal::service('custom.elements')->patchApiUrls($group_data['api-urls']);
          break;
      }

      // And finalize.
      if (!$error) {
        $data[$base_name] = $group_data;
      }
      else {
        $data = []; // Reset data if error found
        break;
      }
    }
    // Send response.
    return new CohesionJsonResponse([
      'status' => !$error ? 'success' : 'error',
      'data' => $data,
    ]);
  }

  /**
   * @param bool $cron
   *
   * @return array|null|\Symfony\Component\HttpFoundation\RedirectResponse
   */
  public static function batch($cron = FALSE) {
    // Reset temporary template list
    \Drupal::keyValue('cohesion.temporary_template')->set('temporary_templates', []);

    // Clean the scratch directory.
    \Drupal::service('cohesion.local_files_manager')->resetScratchDirectory();

    // Set up the batch process array framework.
    $batch = [
      'title' => t('Rebuilding'),
      'operations' => [],
      'finished' => 'entity_rebuild_finished_callback',
      'file' => drupal_get_path('module', 'cohesion_website_settings') . '/cohesion_website_settings.batch.inc',
    ];

    $configs = \Drupal::entityTypeManager()->getDefinitions();

    // Make sure website settings are processed first
    $website_settings_configs = [
      'cohesion_scss_variable',
      'cohesion_color',
      'cohesion_icon_library',
      'cohesion_font_library',
      'cohesion_font_stack',
      'cohesion_website_settings',
    ];

    foreach ($website_settings_configs as $website_settings_type){
      if(isset($configs[$website_settings_type])){

        $entity_list = \Drupal::entityTypeManager()->getStorage($website_settings_type)->loadMultiple();

        foreach ($entity_list as $entity) {
          $batch['operations'][] = [
            '_resave_entity',
            ['entity' => $entity, 'realsave' => TRUE],
          ];
        }
        // Remove processed website setting from all configs
        unset($configs[$website_settings_type]);
      }
    }

    // Process default element styles
    $batch['operations'][] = [
      'cohesion_elements_get_elements_style_process_batch',
      [],
    ];

    // Process all remaining DX8 configuration entities.
    $search = 'cohesion_';
    foreach ($configs as $entity_type_name => $entity_type) {
      if (substr($entity_type_name, 0, strlen($search)) === $search) {
        try {
          $entity_list = \Drupal::entityTypeManager()->getStorage($entity_type_name)->loadMultiple();

          foreach ($entity_list as $entity) {
            // Only rebuild entities that have been activated/modified.
            if ($entity->get('modified') || $entity_type_name === 'cohesion_website_settings' || $entity_type_name === 'cohesion_color' || $entity_type_name === 'cohesion_icon_library' || $entity_type_name === 'cohesion_font_stack') {
              $batch['operations'][] = [
                '_resave_entity',
                ['entity' => $entity, 'realsave' => TRUE],
              ];
            }
          }
        } catch (\Exception $e) {

        }
      }
    }

    // Save all "cohesion_layout" content entities.
    $query = \Drupal::entityQuery('cohesion_layout');
    $entity_ids = $query->execute();
    $increment = 10;
    for ($i = 0; $i < count($entity_ids); $i += $increment) {
      $ids = array_slice($entity_ids, $i, $increment);
      $batch['operations'][] = [
        '_resave_cohesion_layout_entity',
        ['ids' => $ids],
      ];
    }

    // Rebuild the views usage.
    $batch['operations'][] = [
      '_rebuild_views_usage',
      [],
    ];

    // Add .htaccess to twig template directory.
    $batch['operations'][] = ['cohesion_templates_secure_directory', []];

    // Carry on!
    if ($cron) {
      return $batch;
    }
    else {
      // Run the batch process.
      batch_set($batch);
      return batch_process(Url::fromRoute('cohesion.configuration.account_settings')->toString());
    }
  }

  /**
   * @return array collection of DX8 elements
   */
  private function elementCollection() {
    try {
      return \Drupal::database()->select('key_value', 'chc')->fields('chc', ['collection'])->condition('chc.collection', 'cohesion.assets.%', 'LIKE')->groupBy('chc.collection')->execute()->fetchAll();
    } catch (\Exception $ex) {
      watchdog_exception('cohesion', $ex);
    }

    return [];
  }

}
