<?php

namespace Drupal\linkit\Utility;

/**
 * Provides helper to operate on URIs.
 */
class LinkitHelper {

  /**
   * Load the entity referenced by an entity scheme uri.
   *
   * @param string $uri
   *   An internal uri string representing an entity path, such as
   *   "entity:node/23".
   *
   * @return \Drupal\Core\Entity\EntityInterface|null
   *   The most appropriate translation of the entity that matches the given
   *   uri, or NULL if could not match any entity.
   */
  public static function getEntityFromUri($uri) {
    // Stripe out potential query and fragment from the uri.
    $uri = strtok(strtok($uri, "?"), "#");
    list($entity_type, $entity_id) = explode('/', substr($uri, 7), 2);
    $entity_manager = \Drupal::entityTypeManager();
    if ($entity_manager->getDefinition($entity_type, FALSE)) {
      if ($entity = $entity_manager->getStorage($entity_type)->load($entity_id)) {
        return \Drupal::service('entity.repository')->getTranslationFromContext($entity);
      }
    }

    return NULL;
  }

  /**
   * Converts linkit form fields to a uri.
   *
   * @param array $value
   *   User submitted values for this widget.
   *
   * @return string
   *   An internal uri string, such as "internal:blog" or "entity:node/23".
   */
  public static function getUriFromSubmittedValue(array $value) {
    $uri = $value['uri'];

    if (empty($uri)) {
      return '';
    }

    if (!empty($value['attributes']['data-entity-type']) && !empty($value['attributes']['data-entity-uuid'])) {
      $entity_type = $value['attributes']['data-entity-type'];
      $entity_uuid = $value['attributes']['data-entity-uuid'];

      /* @var \Drupal\Core\Entity\EntityInterface $entity */
      $entity = \Drupal::service('entity.repository')->loadEntityByUuid($entity_type, $entity_uuid);
      if ($entity) {
        $entity_uri = 'entity:' . $entity->getEntityTypeId() . '/' . $entity->id();
        // Preserve the fragment, if present.
        $fragment = parse_url($uri, PHP_URL_FRAGMENT);
        if (!empty($fragment)) {
          $entity_uri .= '#' . $fragment;
        }
        return $entity_uri;
      }
    }

    if (!empty($uri) && parse_url($uri, PHP_URL_SCHEME) === NULL) {
      // @todo '<front>' is valid input for BC reasons, may be removed by
      //   https://www.drupal.org/node/2421941
      // - '<front>' -> '/'
      // - '<front>#foo' -> '/#foo'
      if (strpos($uri, '<front>') === 0) {
        $uri = '/' . substr($uri, strlen('<front>'));
      }
      return 'internal:' . $uri;
    }

    return $uri;
  }

}
