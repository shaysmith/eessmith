<?php

namespace Drupal\cohesion_style_helpers;

use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\cohesion\CohesionHtmlRouteProvider;
use Drupal\cohesion_style_helpers\Controller\StyleHelpersController;

/**
 * Provides routes for Cohesion base styles entities.
 *
 * @see Drupal\Core\Entity\Routing\AdminHtmlRouteProvider
 * @see Drupal\Core\Entity\Routing\DefaultHtmlRouteProvider
 */
class StyleHelpersHtmlRouteProvider extends CohesionHtmlRouteProvider {

  /**
   *
   */
  protected function getAddPageRoute(EntityTypeInterface $entity_type) {
    if ($route = parent::getAddPageRoute($entity_type)) {
      $route->setDefault('_controller', StyleHelpersController::class . '::addPage');
      $route->setDefault('_title_callback', StyleHelpersController::class . '::addTitle');

      return $route;
    }
  }

}
