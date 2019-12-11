<?php

/**
 * @file
 * Hooks related to Media and its plugins.
 */

/**
 * @addtogroup hooks
 * @{
 */

/**
 * Alters the information provided in \Drupal\media\Annotation\MediaSource.
 *
 * @param array $sources
 *   The array of media source plugin definitions, keyed by plugin ID.
 */
function hook_media_source_info_alter(array &$sources) {
  $sources['youtube']['label'] = t('Youtube rocks!');
}

/**
 * Alters an oEmbed resource URL before it is fetched.
 *
 * @param array $parsed_url
 *   A parsed URL, as returned by \Drupal\Component\Utility\UrlHelper::parse().
 * @param \Drupal\media\OEmbed\Provider $provider
 *   The oEmbed provider for the resource.
 *
 * @see \Drupal\media\OEmbed\UrlResolverInterface::getResourceUrl()
 */
function hook_oembed_resource_url_alter(array &$parsed_url, \Drupal\media\OEmbed\Provider $provider) {
  // Always serve YouTube videos from youtube-nocookie.com.
  if ($provider->getName() === 'YouTube') {
    $parsed_url['path'] = str_replace('://youtube.com/', '://youtube-nocookie.com/', $parsed_url['path']);
  }
}

/**
 * Alters the context information that get passed to the oembed iframe.
 *
 * @param array $context
 *   Context information that get passed to media_oembed_iframe theme function.
 * @param \Drupal\Core\Field\FormatterInterface $plugin
 *   The FieldFormatter interface.
 * @param \Drupal\media\OEmbed\Resource $resource
 *   The oembed resource.
 */
function hook_oembed_formatter_context_alter(array &$context, \Drupal\Core\Field\FormatterInterface $plugin, \Drupal\media\OEmbed\Resource $resource) {
  $context['plugin_id'] = $plugin->getPluginId();
}

/**
 * @} End of "addtogroup hooks".
 */
