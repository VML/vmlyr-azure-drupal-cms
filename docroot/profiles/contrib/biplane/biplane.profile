<?php

/**
 * @file
 * Enables modules and site configuration for a bazo site installation.
 */

use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\Console\Input\ArgvInput;
use Symfony\Component\Console\Output\ConsoleOutput;

/**
 * Implements hook_form_FORM_ID_alter() for install_configure_form.
 * @see \Drupal\Core\Installer\Form\SiteConfigureForm
 */
function biplane_form_install_configure_form_alter(&$form, FormStateInterface $form_state) {

  $environments = [];
  // CLI interactive install. Lets prompt the user.
  if (PHP_SAPI === 'cli') {
    // Set some defaults up from the URI.
    $input = new ArgvInput();
    $uri = $input->getParameterOption('--uri');
    $project_name = explode('.', $uri)[0];

    // Default values for Environment Switcher.
    $environments['local'] = 'https://www.' . $uri;
    $environments['dev'] = 'https://dev-www.' . $project_name . '.com';
    $environments['stage'] = 'https://stg-www.' . $project_name . '.com';
    $environments['prod'] = 'https://www.' . $project_name . '.com';

    $output = new ConsoleOutput();
    $output->writeln('Installation CLI mode assumes sensible defaults. You can change these options in the admin pages here: ' . $environments['local'] . '/admin/config/development/environment-indicator/switcher');
    $output->writeln('Domain: Local = ' . $environments['local']);
    $output->writeln('Domain: Dev = ' . $environments['dev']);
    $output->writeln('Domain: Stage = ' . $environments['stage']);
    $output->writeln('Domain: Prod = ' . $environments['prod']);
  }
  else {
    $server_name = $_SERVER['SERVER_NAME'];
    $server_name_local = preg_replace('#^www.#', '', $server_name);
    $server_name_root = preg_replace('#.docksal#', '', $server_name_local);

    // Default values for Environment Switcher.
    $environments['local'] = 'https://www.' . $server_name_local;
    $environments['dev'] = 'https://dev-www.' . $server_name_root . '.com';
    $environments['stage'] = 'https://stg-www.' . $server_name_root . '.com';
    $environments['prod'] = 'https://www.' . $server_name_root . '.com';
  }

  // Remove messages from installed modules.
  \Drupal::messenger()->deleteByType('status');

  // Attach the relevant submit handler for this profile.
  $form['#submit'][] = 'biplane_form_install_configure_submit';

  $form['environment_settings'] = [
    '#type' => 'fieldgroup',
    '#title' => t('Environment settings'),
  ];

  $form['environment_settings']['domain_local'] = [
    '#type' => 'url',
    '#title' => t('Domain: Local'),
    '#description' => t('The environment url. IE https://www.bazo.docksal'),
    '#required' => TRUE,
    '#default_value' => $environments['local'] ?? '',
  ];
  $form['environment_settings']['domain_remote_dev'] = [
    '#type' => 'url',
    '#title' => t('Domain: Dev'),
    '#description' => t('The environment url. I.E. https://dev-www.bazo.com'),
    '#required' => TRUE,
    '#default_value' => $environments['dev'] ?? '',
  ];
  $form['environment_settings']['domain_remote_stage'] = [
    '#type' => 'url',
    '#title' => t('Domain: Stage'),
    '#description' => t('The environment url. I.E. https://stg-www.bazo.com'),
    '#required' => TRUE,
    '#default_value' => $environments['stage'] ?? '',
  ];
  $form['environment_settings']['domain_remote_prod'] = [
    '#type' => 'url',
    '#title' => t('Domain: Prod'),
    '#description' => t('The environment url. I.E. https://www.bazo.com'),
    '#required' => TRUE,
    '#default_value' => $environments['prod'] ?? '',
  ];
  // _test_function_here();
}

/**
 * Submission handler for @see biplane_form_install_configure_form_alter().
 *
 * Note: A split's stage_file_proxy url is updated in the batch process
 * @see _biplane_install_tasks_config_split() during site install using
 * the remote_prod environment indicator url.
 */
function biplane_form_install_configure_submit($form, \Drupal\Core\Form\FormStateInterface $form_state) {
  $environment_storage = \Drupal::entityTypeManager()->getStorage('environment_indicator');
  foreach ($environment_storage->loadMultiple() as $environment_indicator) {
    /** @var \Drupal\environment_indicator\Entity\EnvironmentIndicator $environment_indicator */
    $environment_indicator->set('url', $form_state->getValue("domain_{$environment_indicator->id()}"));
    $environment_indicator->save();
  }
}
