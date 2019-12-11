<?php

/**
 * @file
 * Enables modules and site configuration for the Blackbird install profile.
 */

use Drupal\Core\Form\FormStateInterface;

/**
 * Implements hook_form_FORM_ID_alter() for install_configure_form.
 * @see \Drupal\Core\Installer\Form\SiteConfigureForm
 */
function blackbird_form_install_configure_form_alter(&$form, \Drupal\Core\Form\FormStateInterface $form_state) {
  // Remove messages from installed modules.
  \Drupal::messenger()->deleteByType('status');

  // Attach the relevant submit handler for this profile.
  $form['#submit'][] = 'blackbird_form_install_configure_submit';

  $form['environment_settings'] = [
    '#type' => 'fieldgroup',
    '#title' => t('Environment settings'),
  ];

  $form['environment_settings']['domain_local'] = [
    '#type' => 'url',
    '#title' => t('Domain: Local'),
    '#description' => t('The environment url. IE https://www.bazo.docksal'),
    '#required' => TRUE,
  ];
  $form['environment_settings']['domain_remote_dev'] = [
    '#type' => 'url',
    '#title' => t('Domain: Dev'),
    '#description' => t('The environment url. I.E. https://dev-www.bazo.com'),
    '#required' => TRUE,
  ];
  $form['environment_settings']['domain_remote_stage'] = [
    '#type' => 'url',
    '#title' => t('Domain: Stage'),
    '#description' => t('The environment url. I.E. https://stg-www.bazo.com'),
    '#required' => TRUE,
  ];
  $form['environment_settings']['domain_remote_prod'] = [
    '#type' => 'url',
    '#title' => t('Domain: Prod'),
    '#description' => t('The environment url. I.E. https://www.bazo.com'),
    '#required' => TRUE,
  ];
  // _test_function_here();
}

/**
 * Submission handler for @see blackbird_form_install_configure_form_alter().
 *
 * Note: A split's stage_file_proxy url is updated in the batch process
 * @see _blackbird_install_tasks_config_split() during site install using
 * the remote_prod environment indicator url.
 */
function blackbird_form_install_configure_submit($form, \Drupal\Core\Form\FormStateInterface $form_state) {
  $environment_storage = \Drupal::entityTypeManager()->getStorage('environment_indicator');
  foreach ($environment_storage->loadMultiple() as $environment_indicator) {
    /** @var \Drupal\environment_indicator\Entity\EnvironmentIndicator $environment_indicator */
    $environment_indicator->set('url', $form_state->getValue("domain_{$environment_indicator->id()}"));
    $environment_indicator->save();
  }
}
