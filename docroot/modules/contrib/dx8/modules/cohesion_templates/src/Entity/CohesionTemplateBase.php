<?php

namespace Drupal\cohesion_templates\Entity;

use Drupal\cohesion\Entity\CohesionConfigEntityBase;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\cohesion\Entity\CohesionSettingsInterface;
use Drupal\cohesion_templates\Plugin\Api\TemplatesApi;

/**
 * Defines the Cohesion template base entity type.
 */
abstract class CohesionTemplateBase extends CohesionConfigEntityBase implements CohesionSettingsInterface {

  public $element_actions = [];

  /**
   * @inheritdoc
   */
  public function setDefaultValues() {
    parent::setDefaultValues();

    $this->set('custom', FALSE);
    $this->set('twig_template', NULL);
  }

  /**
   * Make this the default entity of its type.
   *
   * @param bool $default
   */
  public function setDefault($default = TRUE) {
    // Set the default value.
    $this->set('default', $default);
  }

  /**
   * {@inheritdoc}
   */
  public function preSave(EntityStorageInterface $storage) {
    parent::preSave($storage);
    $this->preProcessJsonValues();
  }

  /**
   * {@inheritdoc}
   */
  public function process() {
    parent::process();

    /** @var TemplatesApi $send_to_api */
    $send_to_api = \Drupal::service('plugin.manager.api.processor')->createInstance('templates_api');

    $send_to_api->setEntity($this);
    $send_to_api->send('template');

    // Invalidate the template cache.
    self::clearCache($this);
  }

  /**
   * {@inheritdoc}
   */
  public function jsonValuesErrors() {
    /** @var TemplatesApi $send_to_api */
    $send_to_api = \Drupal::service('plugin.manager.api.processor')->createInstance('templates_api');

    $send_to_api->setEntity($this);
    $send_to_api->setSaveData(FALSE);
    $success = $send_to_api->send('template');
    $responseData = $send_to_api->getData();

    if ($success === TRUE) {
      return FALSE;
    }
    else {
      return $responseData;
    }
  }

  /**
   * {@inheritdoc}
   */
  public function postSave(EntityStorageInterface $storage, $update = TRUE) {
    parent::postSave($storage);

    // Send to API only if JSON has changed;
    if ($this->status()) {
      $this->process();
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function preDelete(EntityStorageInterface $storage, array $entities) {
    parent::preDelete($storage, $entities);

    foreach ($entities as $entity) {
      // Clear the cache for this component.
      self::clearCache($entity);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function clearData() {
    // Invalidate the template cache.
    self::clearCache($this);

    // Delete the entry from the stylesheet.json file.
    /** @var TemplatesApi $send_to_api */
    $send_to_api = \Drupal::service('plugin.manager.api.processor')->createInstance('templates_api');
    $send_to_api->setEntity($this);
    $send_to_api->delete();


    // Delete the twig template generated from the API.
    if ($template_file = $this->getTwigPath()) {
      if (file_exists($template_file)) {
        file_unmanaged_delete($template_file);
      }
    }
  }

  /**
   * @return bool|string
   */
  protected function getTwigPath() {
    if ($this->get('twig_template')) {
      return COHESION_TEMPLATE_PATH . '/' . $this->get('twig_template') . '.html.twig';
    }
    return FALSE;
  }

  /**
   * Delete the template twig cache (if available) and invalidate the render
   * cache tags.
   */
  protected static function clearCache($entity) {
    // The twig filename for this template.
    $filename = $entity->get('twig_template');

    _cohesion_templates_delete_twig_cache_file($filename);

    // Content template
    if ($entity->get('entity_type') && $entity->get('bundle')) {
      $entity_cache_tags = [];

      // Template is also the default.
      if ($entity->get('default') == TRUE) {
        $entity_cache_tags[] = 'cohesion.templates.' . $entity->get('entity_type') . '.' . $entity->get('bundle') . '.' . $entity->get('view_mode') . '.__default__';
      }

      // Template is global
      if ($entity->get('bundle') == '__any__') {
        $entity_cache_tags[] = 'cohesion.templates.' . $entity->get('entity_type') . '.' . $entity->get('view_mode');
      }
      else {
        $entity_cache_tags[] = 'cohesion.templates.' . $entity->get('entity_type') . '.' . $entity->get('bundle') . '.' . $entity->get('view_mode') . '.' . $entity->id();
      }
    }
    // All other templates.
    else {
      $entity_cache_tags = ['cohesion.templates.' . $entity->id()];
    }

    // Invalidate render cache tag for this template.
    \Drupal::service('cache_tags.invalidator')->invalidateTags($entity_cache_tags);

    // And clear the theme cache.
    parent::clearCache($entity);
  }

  /**
   * @return string (twig template)
   */
  public function getTwigTemplate() {
    return $this->get('twig_template') ?: NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function isLayoutCanvas() {
    return TRUE;
  }
}
