<?php

namespace Drupal\cohesion_elements\Entity;

use Drupal\cohesion\Entity\EntityJsonValuesInterface;
use Drupal\cohesion\EntityJsonValuesTrait;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\entity_reference_revisions\EntityNeedsSaveTrait;
use Drupal\cohesion_templates\Plugin\Api\TemplatesApi;
use Drupal\Component\Serialization\Json;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\Core\TypedData\TranslatableInterface;
use Drupal\cohesion_elements\CohesionLayoutInterface;
use Drupal\cohesion\EntityUpdateInterface;

/**
 * Defines the CohesionLayout entity.
 *
 *
 * @ContentEntityType(
 *   id = "cohesion_layout",
 *   label = @Translation("Layout canvas"),
 *   handlers = {
 *     "view_builder" = "Drupal\cohesion_elements\CohesionLayoutViewBuilder",
 *     "access" = "Drupal\cohesion_elements\CohesionLayoutAccessControlHandler",
 *     "form" = {
 *       "default" = "Drupal\Core\Entity\ContentEntityForm",
 *       "delete" = "Drupal\Core\Entity\ContentEntityDeleteForm",
 *       "edit" = "Drupal\cohesion_elements\Form\CohesionLayoutForm"
 *     },
 *     "route_provider" = {
 *       "html" = "Drupal\cohesion_elements\Entity\CohesionLayoutRouteProvider",
 *     },
 *     "views_data" = "Drupal\views\EntityViewsData",
 *   },
 *   entity_revision_parent_type_field = "parent_type",
 *   entity_revision_parent_id_field = "parent_id",
 *   entity_revision_parent_field_name_field = "parent_field_name",
 *   base_table = "cohesion_layout",
 *   data_table = "cohesion_layout_field_data",
 *   revision_table = "cohesion_layout_revision",
 *   revision_data_table = "cohesion_layout_field_revision",
 *   translatable = TRUE,
 *   content_translation_ui_skip = TRUE,
 *   entity_keys = {
 *     "id" = "id",
 *     "uuid" = "uuid",
 *     "revision" = "revision",
 *     "langcode" = "langcode",
 *   },
 *   common_reference_revisions_target = TRUE,
 *   default_reference_revision_settings = {
 *     "field_storage_config" = {
 *       "cardinality" = 1,
 *       "settings" = {
 *         "target_type" = "cohesion_layout"
 *       }
 *     },
 *     "field_config" = {
 *       "settings" = {
 *         "handler" = "default:cohesion_layout"
 *       }
 *     },
 *     "entity_form_display" = {
 *       "type" = "cohesion_layout_builder_widget"
 *     },
 *     "entity_view_display" = {
 *       "type" = "entity_reference_revisions_entity_view"
 *     }
 *   }
 * )
 *
 */
class CohesionLayout extends ContentEntityBase implements CohesionLayoutInterface, EntityJsonValuesInterface, EntityUpdateInterface {

  use EntityNeedsSaveTrait;
  use EntityJsonValuesTrait;

  protected $host = NULL;

  /**
   * {@inheritdoc}
   */
  public function setJsonValue($json_values) {
    $this->set('json_values', $json_values);
    return $this;
  }

  /**
   * Get the raw JSON.
   *
   * @return string
   */
  public function getJsonValues() {
    return $this->get('json_values')->value ? $this->get('json_values')->value : '{}';
  }

  /**
   * {@inheritdoc}
   */
  public function setJsonMapper($json_values) {
    $this->set('json_mapper', $json_values);
    return $this;
  }

  /**
   * Get the raw mapper JSON.
   *
   * @return string
   */
  public function getJsonMapper() {
    return $this->get('json_mapper')->value ? $this->get('json_mapper')->value : '{}';
  }

  /**
   * @return mixed
   */
  public function getTwig() {
    if ($this->get('template')->value) {
      return Json::decode($this->get('template')->value)['twig'];
    }

    return '';
  }

  /**
   * {@inheritdoc}
   */
  public function setTemplate($template) {
    $this->set('template', $template);
    return $this;
  }

  /**
   * @return mixed
   */
  public function getTwigContexts() {
    if ($this->get('template')->value) {
      return Json::decode($this->get('template')->value)['metadata']['contexts'];
    }

    return [];
  }

  /**
   * @return mixed
   */
  public function getStyles() {
    if ($this->get('styles')->value) {
      return $this->get('styles')->value;
    }

    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function setStyles($styles) {
    $this->set('styles', $styles);
    return $this;
  }

  /**
   * @return ContentEntityInterface
   */
  public function getHost() {
    return $this->host;
  }

  /**
   * {@inheritdoc}
   */
  public function setHost($host) {
    if ($host instanceof ContentEntityInterface) {
      $this->host = $host;
    }
    return $this;
  }

  /**
   * @inheritdoc
   */
  public function isLayoutCanvas() {
    return TRUE;
  }

  /**
   * Get the parent content ID this CohesionLayout entity is referenced
   * from.
   *
   * @return $this|\Drupal\Core\Entity\ContentEntityInterface|\Drupal\Core\Entity\EntityInterface|null
   */
  public function getParentEntity() {
    if (!isset($this->get('parent_type')->value) || !isset($this->get('parent_id')->value)) {
      return NULL;
    }

    try {
      $parent = $this->entityTypeManager()->getStorage($this->get('parent_type')->value)->load($this->get('parent_id')->value);
    } catch (\Exception $e) {
      return NULL;
    }

    // Return current translation of parent entity, if it exists.
    if ($parent != NULL && ($parent instanceof TranslatableInterface) && $parent->hasTranslation($this->language()->getId())) {
      return $parent->getTranslation($this->language()->getId());
    }

    return $parent;
  }

  /**
   * @inheritdoc
   */
  public function jsonValuesErrors() {

    $this->resetElementsUUIDs();
    $errors = $this->validateComponentValues();

    if ($errors) {
      return $errors;
    }

    /** @var TemplatesApi $send_to_api */
    $send_to_api = \Drupal::service('plugin.manager.api.processor')->createInstance('templates_api');
    $send_to_api->setEntity($this);
    $send_to_api->setSaveData(FALSE);
    $success = $send_to_api->send();
    $responseData = $send_to_api->getData();

    if ($success === TRUE) {     // layout-field
      return FALSE;
    }
    else {
      return $responseData;
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getLastAppliedUpdate() {
    return $this->get('last_entity_update')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function setLastAppliedUpdate($callback) {
    $this->set('last_entity_update', $callback);
    return $this;
  }

  /**
   * @inheritdoc
   */
  public function process() {
    $this->preProcessJsonValues();

    /** @var TemplatesApi $send_to_api */
    $send_to_api = \Drupal::service('plugin.manager.api.processor')->createInstance('templates_api');
    $send_to_api->setEntity($this);
    $success = $send_to_api->send();
    $responseData = $send_to_api->getData();

    if ($success === TRUE) {     // layout-field
      // Only update template/style data if there were no errors.
      if (isset($responseData['theme']) && isset($responseData['template'])) {
        $this->set('styles', $responseData['theme']);
        $this->set('template', $responseData['template']);
      }
      return FALSE;
    }
    else {
      $cohesion_error = &drupal_static('entity_cohesion_error');
      $cohesion_error = isset($responseData['error']) ? $responseData['error'] : '';
      return $cohesion_error;
    }

  }

  /**
   * {@inheritdoc}
   */
  public function preSave(EntityStorageInterface $storage) {
    parent::preSave($storage);

    \Drupal::service('cohesion.entity_update_manager')->apply($this);

    $styles = $this->get('styles')->getValue();
    $template = $this->get('template')->getValue();

    $errors = $this->process();

    if ($errors) {
      // Keep original styles/template data if API error.
      \Drupal::messenger()->addMessage($errors, 'error');

      $this->set('styles', $styles);
      $this->set('template', $template);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function postSave(EntityStorageInterface $storage, $update = TRUE) {
    parent::postSave($storage, $update);

    $this->setNeedsSave(FALSE);
    $parent_entity = $this->getParentEntity();

    // Handle invalidating existing cache tags for the parent entity.
    if ($update) {
      // Get the cache tags set for this layout formatter on this entity.
      try {
        if ($parent_entity) {
          $layout_cache_tag = 'layout_formatter.' . $parent_entity->uuid();
          $entity_cache_tag = $this->getParentEntity()->getEntityTypeId() . ':' . $parent_entity->id();

          // Invalidate render cache tag for this layout formatter AND the overall node.
          \Drupal::service('cache_tags.invalidator')->invalidateTags([
            $layout_cache_tag,
            $entity_cache_tag,
          ]);

          // The purge module is enabled (Ie. Acquia hosting with Vanish), forceably purge the cache for this entity.
          if (\Drupal::moduleHandler()->moduleExists('purge')) {

            $purgeInvalidationFactory = \Drupal::service('purge.invalidation.factory');
            $purgeQueuers = \Drupal::service('purge.queuers');
            $purgeQueue = \Drupal::service('purge.queue');
            if ($queuer = $purgeQueuers->get('drush_purge_queue_add')) {

              $invalidations = [
                $purgeInvalidationFactory->get('tag', $layout_cache_tag),
                $purgeInvalidationFactory->get('tag', $entity_cache_tag),
              ];

              $purgeQueue->add($queuer, $invalidations);
            }
          }
        }

      } catch (\Exception $e) {
        return;
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function delete() {
    // Prevent CohesionLayout entities from being deleted changing the field type
    // for layout canvas to cohesion_entity_reference_revisions in cohesion_elements_update_8308
    if (!drupal_static('cohesion_elements_update_8308', FALSE)) {
      parent::delete();
    }
  }

  /**
   * {@inheritdoc}
   *
   * Define the field properties here.
   *
   * Field name, type and size determine the table structure.
   *
   * In addition, we can define how the field and its content can be manipulated
   * in the GUI. The behaviour of the widgets used can be determined here.
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type) {

    $fields = parent::baseFieldDefinitions($entity_type);

    $fields['json_values'] = BaseFieldDefinition::create('string_long')->setLabel(t('Values'))->setDefaultValue('{}')->setRevisionable(TRUE)->setTranslatable(TRUE);

    $fields['styles'] = BaseFieldDefinition::create('string_long')->setLabel(t('Styles'))->setRevisionable(TRUE)->setTranslatable(TRUE);

    $fields['template'] = BaseFieldDefinition::create('string_long')->setLabel(t('Template'))->setDefaultValue('/* */')->setRevisionable(TRUE)->setTranslatable(TRUE);

    $fields['parent_id'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Parent ID'))
      ->setDescription(t('The ID of the parent entity of which this entity is referenced.'))
      ->setSetting('is_ascii', TRUE);

    $fields['parent_type'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Parent type'))
      ->setDescription(t('The entity parent type to which this entity is referenced.'))
      ->setSetting('is_ascii', TRUE)
      ->setSetting('max_length', EntityTypeInterface::ID_MAX_LENGTH);

    $fields['parent_field_name'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Parent field name'))
      ->setDescription(t('The entity parent field name to which this entity is referenced.'))
      ->setSetting('is_ascii', TRUE)
      ->setSetting('max_length', FieldStorageConfig::NAME_MAX_LENGTH);

    $fields['last_entity_update'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Last entity update callback applied'))
      ->setDescription(t('The function name of the latest EntityUpdateManager callback applied to this entity.'));

    return $fields;
  }

  /**
   * {@inheritdoc}
   */
  protected function urlRouteParameters($rel) {
    $uri_route_parameters = parent::urlRouteParameters($rel);

    $uri_route_parameters['component_instance_uuid'] = $this->uuid();
    $uri_route_parameters['component_id'] = 0;

    return $uri_route_parameters;
  }

}
