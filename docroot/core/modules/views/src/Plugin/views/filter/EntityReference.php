<?php

namespace Drupal\views\Plugin\views\filter;

use Drupal\Component\Utility\Html;
use Drupal\Core\Entity\Element\EntityAutocomplete;
use Drupal\Core\Entity\EntityReferenceSelection\SelectionPluginManagerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Element;
use Drupal\views\FieldAPIHandlerTrait;
use Drupal\views\Plugin\views\display\DisplayPluginBase;
use Drupal\views\ViewExecutable;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Filter handler which allows to search on multiple fields.
 *
 * @ingroup views_filter_handlers
 *
 * @ViewsFilter("entity_reference")
 */
class EntityReference extends ManyToOne {

  use FieldAPIHandlerTrait;

  /**
   * Type for the auto complete filter format.
   */
  const WIDGET_AUTOCOMPLETE = 'autocomplete';

  /**
   * Type for the select list filter format.
   */
  const WIDGET_SELECT = 'select';


  /**
   * Validated exposed input that will be set as value in case.
   *
   * @var array
   */
  protected $validatedExposedInput;

  /**
   * The selection plugin manager service.
   *
   * @var \Drupal\Core\Entity\EntityReferenceSelection\SelectionPluginManagerInterface
   */
  protected $selectionPluginManager;

  /**
   * The entity type manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * {@inheritdoc}
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, SelectionPluginManagerInterface $selection_plugin_manager, EntityTypeManagerInterface $entity_type_manager) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->selectionPluginManager = $selection_plugin_manager;
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('plugin.manager.entity_reference_selection'),
      $container->get('entity_type.manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function buildExtraOptionsForm(&$form, FormStateInterface $form_state) {
    $entity_type = $this->getReferencedEntityType();

    // Get all selection plugins for this entity type.
    $selection_plugins = $this->selectionPluginManager->getSelectionGroups($entity_type->id());
    $handlers_options = [];
    foreach (array_keys($selection_plugins) as $selection_group_id) {
      // We only display base plugins (e.g. 'default', 'views', ...) and not
      // entity type specific plugins (e.g. 'default:node', 'default:user',
      // ...).
      if (array_key_exists($selection_group_id, $selection_plugins[$selection_group_id])) {
        $handlers_options[$selection_group_id] = Html::escape($selection_plugins[$selection_group_id][$selection_group_id]['label']);
      }
      elseif (array_key_exists($selection_group_id . ':' . $entity_type->id(), $selection_plugins[$selection_group_id])) {
        $selection_group_plugin = $selection_group_id . ':' . $entity_type->id();
        $handlers_options[$selection_group_plugin] = Html::escape($selection_plugins[$selection_group_id][$selection_group_plugin]['base_plugin_label']);
      }
    }

    $form['#type'] = 'container';
    $form['#process'][] = [get_class($this), 'fieldSettingsAjaxProcess'];

    $form['handler'] = [
      '#type' => 'details',
      '#title' => t('Reference type'),
      '#open' => TRUE,
      '#attributes' => ['id' => 'handler_wrapper'],
    ];

    // @todo When changing selection handler Ajax request doesn't get processed
    // correctly so need to add this hack.
    $input = $form_state->getUserInput();
    if (isset($input['options']['handler']) && array_key_exists($input['options']['handler'], $handlers_options)) {
      $this->options['handler'] = $input['options']['handler'];
    }
    if (isset($input['options']['handler_settings'])) {
      $this->options['handler_settings'] = $input['options']['handler_settings'];
    }

    $form['handler']['handler'] = [
      '#type' => 'select',
      '#title' => t('Reference method'),
      '#options' => $handlers_options,
      '#default_value' => $this->options['handler'],
      '#required' => TRUE,
      '#ajax' => TRUE,
      '#limit_validation_errors' => [['options', 'handler', 'handler']],
      '#parents' => ['options', 'handler'],
    ];

    $form['handler']['handler_submit'] = [
      '#type' => 'submit',
      '#value' => t('Change handler'),
      '#limit_validation_errors' => [],
      '#attributes' => [
        'class' => ['js-hide'],
      ],
      '#submit' => [[get_class($this), 'settingsAjaxSubmit']],
      '#parents' => ['handler_submit'],
    ];

    $form['handler']['handler_settings'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['entity_reference-settings']],
      '#parents' => ['options', 'handler_settings'],
      '#process' => [[get_class($this), 'fixSubmitParents']],
    ];

    $options = $this->options['handler_settings'];
    $options += [
      'target_type' => $entity_type->id(),
      'handler' => $this->options['handler'],
    ];

    $handler = $this->selectionPluginManager->getInstance($options);
    $form['handler']['handler_settings'] += $handler->buildConfigurationForm([], $form_state);
    // There is no need in polluting filter config form.
    $form['handler']['handler_settings']['auto_create']['#access'] = FALSE;
    $form['handler']['handler_settings']['auto_create_bundle']['#access'] = FALSE;

    $form['widget'] = [
      '#type' => 'radios',
      '#title' => $this->t('Selection type'),
      '#default_value' => $this->options['widget'],
      '#options' => [
        self::WIDGET_SELECT => $this->t('Select list'),
        self::WIDGET_AUTOCOMPLETE => $this->t('Autocomplete'),
      ],
      '#parents' => ['options', 'widget'],
    ];
  }

  /**
   * Render API callback.
   *
   * Processes the field settings form and allows access to the form state.
   *
   * @see static::fieldSettingsForm()
   */
  public static function fixSubmitParents($form, FormStateInterface $form_state) {
    static::fixSubmitParentsElement($form, 'root');
    return $form;
  }

  /**
   * Process element callback.
   *
   * Adds entity_reference specific properties to AJAX form elements from the
   * field settings form.
   *
   * @see static::fixSubmitParents()
   */
  public static function fixSubmitParentsElement(&$element, $key) {
    if (isset($element['#type']) && ($element['#type'] === 'button' || $element['#type'] == 'submit')) {
      if ($key !== 'root') {
        $element['#parents'] = [$key];
      }
    }

    foreach (Element::children($element) as $key) {
      static::fixSubmitParentsElement($element[$key], $key);
    }
  }

  /**
   * Submit handler for the handler settings.
   */
  public static function settingsAjaxSubmit($form, FormStateInterface $form_state) {
    $form_state->setRebuild();
  }

  /**
   * Ajax callback for the handler settings form.
   *
   * @see static::fieldSettingsForm()
   */
  public static function settingsAjax($form, FormStateInterface $form_state) {
    return $form['options']['handler'];
  }

  /**
   * Render API callback.
   *
   * Processes the field settings form and allows access to the form state.
   *
   * @see static::fieldSettingsForm()
   */
  public static function fieldSettingsAjaxProcess($form, FormStateInterface $form_state) {
    static::fieldSettingsAjaxProcessElement($form, $form);
    return $form;
  }

  /**
   * Process element callback.
   *
   * Adds entity_reference specific properties to AJAX form elements from the
   * field settings form.
   *
   * @see static::fieldSettingsAjaxProcess()
   */
  public static function fieldSettingsAjaxProcessElement(&$element, $main_form) {
    if (!empty($element['#ajax'])) {
      $element['#ajax'] = [
        'callback' => [get_called_class(), 'settingsAjax'],
        'wrapper' => 'handler_wrapper',
      ];
    }

    foreach (Element::children($element) as $key) {
      static::fieldSettingsAjaxProcessElement($element[$key], $main_form);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function validateExtraOptionsForm($form, FormStateInterface $form_state) {
    // Copy options to settings to be compatible with plugins.
    $form_state->setValue(['settings', 'handler'], $form_state->getValue(['options', 'handler']));
    $form_state->setValue(['settings', 'handler_settings'], $form_state->getValue(['options', 'handler_settings']));

    $options = $this->options['handler_settings'];
    $options += [
      'target_type' => $this->getReferencedEntityType()->id(),
      'handler' => $this->options['handler'],
    ];
    $handler = $this->selectionPluginManager->getInstance($options);
    $handler->validateConfigurationForm($form, $form_state);

    // Copy settings back into options.
    // Important because DefaultSelection::validateConfigurationForm() correctly
    // sets 'target_bundles' to NULL if an empty array is selected. It does this
    // by manipulating the form state values.
    $form_state->setValue(['options', 'handler'], $form_state->getValue(['settings', 'handler']));
    $form_state->setValue(['options', 'handler_settings'], $form_state->getValue(['settings', 'handler_settings']));

    parent::validateExtraOptionsForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  protected function valueForm(&$form, FormStateInterface $form_state) {
    $referenced_type = $this->getReferencedEntityType();
    $is_exposed = $form_state->get('exposed');

    if ($this->options['widget'] == self::WIDGET_AUTOCOMPLETE) {
      $form['value'] = [
        '#title' => $this->t('Select %entity_types', ['%entity_types' => $referenced_type->getPluralLabel()]),
        '#type' => 'entity_autocomplete',
        '#default_value' => EntityAutocomplete::getEntityLabels($this->getDefaultSelectedEntities()),
        '#tags' => TRUE,
        '#process_default_value' => TRUE,
        '#target_type' => $this->getReferencedEntityType()->id(),
        '#selection_handler' => $this->options['handler'],
        '#selection_settings' => $this->options['handler_settings'],
        '#validate_reference' => FALSE,
        '#process_default_value' => FALSE,
      ];
    }
    else {
      $options = $this->buildReferenceEntityOptions();
      $default_value = (array) $this->value;

      if ($is_exposed) {
        $identifier = $this->options['expose']['identifier'];

        if (!empty($this->options['expose']['reduce'])) {
          $options = $this->reduceValueOptions($options);

          if (!empty($this->options['expose']['multiple']) && empty($this->options['expose']['required'])) {
            $default_value = [];
          }
        }

        if (empty($this->options['expose']['multiple'])) {
          if (empty($this->options['expose']['required']) && (empty($default_value) || !empty($this->options['expose']['reduce']))) {
            $default_value = 'All';
          }
          elseif (empty($default_value)) {
            $keys = array_keys($options);
            $default_value = array_shift($keys);
          }
          // Due to https://www.drupal.org/node/1464174 there is a chance that
          // [''] was saved in the admin ui. Let's choose a safe default value.
          elseif ($default_value == ['']) {
            $default_value = 'All';
          }
          else {
            $copy = $default_value;
            $default_value = array_shift($copy);
          }
        }
      }

      $form['value'] = [
        '#type' => 'select',
        '#title' => $this->t('Select @entity_types', ['@entity_types' => $referenced_type->getPluralLabel()]),
        '#multiple' => TRUE,
        '#options' => $options,
        '#size' => min(9, count($options)),
        '#default_value' => $default_value,
      ];

      $user_input = $form_state->getUserInput();
      if ($is_exposed && isset($identifier) && !isset($user_input[$identifier])) {
        $user_input[$identifier] = $default_value;
        $form_state->setUserInput($user_input);
      }
    }

    // Show or hide the value field depending on the operator field.
    $visible = [];
    if ($is_exposed) {
      $operator_field = ($this->options['expose']['use_operator'] && $this->options['expose']['operator_id']) ? $this->options['expose']['operator_id'] : NULL;
    }
    else {
      $operator_field = 'options[operator]';
      $visible[] = [
          ':input[name="options[expose_button][checkbox][checkbox]"]' => ['checked' => TRUE],
          ':input[name="options[expose][use_operator]"]' => ['checked' => TRUE],
          ':input[name="options[expose][operator_id]"]' => ['empty' => FALSE],
      ];
    }
    if ($operator_field) {
      foreach ($this->operatorValues(1) as $operator) {
        $visible[] = [
          ':input[name="' . $operator_field . '"]' => ['value' => $operator],
        ];
      }
      $form['value']['#states'] = ['visible' => $visible];
    }

    if (!$is_exposed) {
      // Retain the helper option.
      $this->helper->buildOptionsForm($form, $form_state);

      // Show help text if not exposed to end users.
      $form['value']['#description'] = $this->t('Leave blank for all. Otherwise, the first selected item will be the default instead of "Any".');
    }
  }

  /**
   * Gets all entities selected by default.
   *
   * @return string
   *   The auto-complete value.
   */
  protected function getDefaultSelectedEntities() {
    $referenced_type_id = $this->getReferencedEntityType()->id();
    /** @var \Drupal\Core\Entity\EntityStorageInterface $entity_storage */
    $entity_storage = $this->entityTypeManager->getStorage($referenced_type_id);

    return $this->value && !isset($this->value['all']) ? $entity_storage->loadMultiple($this->value) : [];
  }

  /**
   * Builds the options for select filter.
   *
   * @return array
   *   The options.
   */
  protected function buildReferenceEntityOptions() {
    $options = $this->options['handler_settings'];
    $options += [
      'target_type' => $this->getReferencedEntityType()->id(),
      'handler' => $this->options['handler'],
    ];
    $entities = $this->selectionPluginManager->getInstance($options)->getReferenceableEntities();
    $options = [];

    foreach ($entities as $bundle) {
      foreach ($bundle as $id => $entity_label) {
        $options[$id] = $entity_label;
      }
    }

    return $options;
  }

  /**
   * {@inheritdoc}
   */
  protected function valueValidate($form, FormStateInterface $form_state) {
    // We only validate if they've chosen the text field style.
    if ($this->options['widget'] != self::WIDGET_AUTOCOMPLETE) {
      return;
    }

    $ids = [];
    if ($values = $form_state->getValue(['options', 'value'])) {
      foreach ($values as $value) {
        $ids[] = $value['target_id'];
      }
    }

    $form_state->setValue(['options', 'value'], $ids);
  }

  /**
   * {@inheritdoc}
   */
  public function acceptExposedInput($input) {
    if (empty($this->options['exposed'])) {
      return TRUE;
    }
    // We need to know the operator, which is normally set in
    // \Drupal\views\Plugin\views\filter\FilterPluginBase::acceptExposedInput(),
    // before we actually call the parent version of ourselves.
    if (!empty($this->options['expose']['use_operator']) && !empty($this->options['expose']['operator_id']) && isset($input[$this->options['expose']['operator_id']])) {
      $this->operator = $input[$this->options['expose']['operator_id']];
    }

    // If view is an attachment and is inheriting exposed filters, then assume
    // exposed input has already been validated.
    if (!empty($this->view->is_attachment) && $this->view->display_handler->usesExposed()) {
      $this->validatedExposedInput = (array) $this->view->exposed_raw_input[$this->options['expose']['identifier']];
    }

    // If we're checking for EMPTY or NOT, we don't need any input, and we can
    // say that our input conditions are met by just having the right operator.
    if ($this->operator == 'empty' || $this->operator == 'not empty') {
      return TRUE;
    }

    // If it's non-required and there's no value don't bother filtering.
    if (!$this->options['expose']['required'] && empty($this->validatedExposedInput)) {
      return FALSE;
    }

    $rc = parent::acceptExposedInput($input);
    if ($rc) {
      // If we have previously validated input, override.
      if (isset($this->validatedExposedInput)) {
        $this->value = $this->validatedExposedInput;
      }
    }

    return $rc;
  }

  /**
   * {@inheritdoc}
   */
  public function validateExposed(&$form, FormStateInterface $form_state) {
    if (empty($this->options['exposed'])) {
      return;
    }

    $identifier = $this->options['expose']['identifier'];

    // We only validate if they've chosen the select field style.
    if ($this->options['widget'] != self::WIDGET_AUTOCOMPLETE) {

      if ($form_state->getValue($identifier) != 'All') {
        $this->validatedExposedInput = (array) $form_state->getValue($identifier);
      }
      return;
    }

    if (empty($this->options['expose']['identifier'])) {
      return;
    }

    if ($values = $form_state->getValue($identifier)) {
      foreach ($values as $value) {
        $this->validatedExposedInput[] = $value['target_id'];
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  protected function defineOptions() {
    $options = parent::defineOptions();

    $options['handler'] = ['default' => 'default:' . $this->getReferencedEntityType()->id()];

    $options['handler_settings'] = ['default' => []];

    $options['widget'] = ['default' => self::WIDGET_AUTOCOMPLETE];

    return $options;
  }

  /**
   * {@inheritdoc}
   */
  public function hasExtraOptions() {
    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function init(ViewExecutable $view, DisplayPluginBase $display, array &$options = NULL) {
    if (empty($this->definition['field_name'])) {
      $this->definition['field_name'] = $options['field'];
    }

    parent::init($view, $display, $options);
  }

  /**
   * {@inheritdoc}
   */
  protected function valueSubmit($form, FormStateInterface $form_state) {
    // Prevent array_filter from messing up our arrays in parent submit.
  }

  /**
   * {@inheritdoc}
   */
  public function getValueOptions() {
    if (empty($this->valueOptions)) {
      $this->valueOptions = $this->buildReferenceEntityOptions();
    }
    return $this->valueOptions;
  }

  /**
   * Gets the target referenced entity type by this field.
   *
   * @return \Drupal\Core\Entity\EntityTypeInterface
   *   Entity type.
   */
  protected function getReferencedEntityType() {
    $field_def = $this->getFieldDefinition();
    $entity_type_id = $field_def->getItemDefinition()->getSetting('target_type');
    return $this->entityTypeManager->getDefinition($entity_type_id);
  }

  /**
   * {@inheritdoc}
   */
  public function calculateDependencies() {
    $dependencies = parent::calculateDependencies();

    /** @var \Drupal\Core\Entity\EntityInterface $entity */
    foreach ($this->getDefaultSelectedEntities() as $entity) {
      $dependencies[$entity->getConfigDependencyKey()][] = $entity->getConfigDependencyName();
    }

    return $dependencies;
  }

}
