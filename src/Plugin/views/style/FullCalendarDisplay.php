<?php
namespace Drupal\fullcalendar_view\Plugin\views\style;

use Drupal\core\form\FormStateInterface;
use Drupal\views\Plugin\views\style\StylePluginBase;
use Drupal\Core\Datetime\DrupalDateTime;

/**
 * Style plugin to render content for FullCalendar
 *
 * @ingroup views_style_plugins
 *
 * @ViewsStyle(
 *   id = "fullcalendar_view_display",
 *   title = @Translation("Full Calendar Dispaly"),
 *   help = @Translation("Render contents in Full Calendar view."),
 *   theme = "views_view_fullcalendar",
 *   display_types = { "normal" }
 * )
 */
class FullCalendarDisplay extends StylePluginBase {
  
  /**
   * Does the style plugin for itself support to add fields to it's output.
   *
   * @var bool
   */
  protected $usesFields = TRUE;
  
  /**
   * {@inheritdoc}
   */
  protected function defineOptions() {
    $options = parent::defineOptions();
    $options['defaultDate'] = array('default' => '');
    $options['start'] = array('default' => '');
    $options['end'] = array('default' => '');
    $options['business_start'] = array('default' => '');
    $options['business_end'] = array('default' => '');
    $options['content_type'] = array('default' => '');
    $options['tax_field'] = array('default' => '');
    $options['color_contents'] = ['default' => []];
    $options['color_taxonomies'] = ['default' => []];
    $options['vocabularies'] = array('default' => '');
    $options['right_buttons'] = array('default' => ['agendaWeek' => 'agendaWeek', 'agendaDay' => 'agendaDay', 'listYear' => 'listYear']);
    return $options;
  }
  
  /**
   * {@inheritdoc}
   */
  public function buildOptionsForm(&$form, FormStateInterface $form_state) {
    parent::buildOptionsForm($form, $form_state);
    
    // Remove the grouping setting.
    if (isset($form['grouping'])) {
      unset($form['grouping']);
    }
    // Default date of the calendar.
    $form['defaultDate'] = array(
      '#type' => 'date',
      '#title' => t('Default Date'),
      '#default_value' => (isset($this->options['defaultDate'])) ? $this->options['defaultDate'] : '',
      '#description' => t('The initial date displayed when the calendar first loads. It should be in ISO 8601 format. For example: 2018-01-24'),
    );
    // All selected fields.
    $field_names = $this->displayHandler->getFieldLabels();
    // Field name of start date.
    $form['start'] = [
      '#title' => $this->t('Start Date Field'),
      '#type' => 'select',
      '#options' => $field_names,
      '#default_value' => (!empty($this->options['start'])) ? $this->options['start'] : '',
    ];
    // Field name of end date.
    $form['end'] = [
      '#title' => $this->t('End Date Field'),
      '#type' => 'select',
      '#options' => $field_names,
      '#empty_value' => '',
      '#default_value' => (!empty($this->options['end'])) ? $this->options['end'] : '',
    ];
    // Display settings.
    $form['display'] = array(
        '#type' => 'details',
        '#title' => t('Display'),
        '#description' =>  t('Calendar display settings.'),
    );
    // Right side buttons.
    $form['right_buttons'] = array(
        '#type' => 'checkboxes',
        '#fieldset' => 'display',
        '#options' => array('agendaWeek' => $this->t('Week'), 'agendaDay' => $this->t('Day'), 'listYear' => $this->t('List')),
        '#default_value' => (empty($this->options['right_buttons'])) ? [] : $this->options['right_buttons'],
        '#title' => $this->t('Right side buttons'),
    );
    // Legend colors.
    $form['colors'] = array(
      '#type' => 'details',
      '#title' => t('Legend Colors'),
      '#description' =>  t('Set color value of legends for each content type or each taxonomy.'),
    );
    // All vocabularies.
    $cabNames = taxonomy_vocabulary_get_names();
    // Taxonomy reference field
    $tax_fields = [];
    // Find out all taxonomy reference fields of this View.
    foreach ($field_names as $field_name => $lable) {
      $field_conf = \Drupal\field\Entity\FieldStorageConfig::loadByName('node', $field_name);
      if (empty($field_conf)) {
        continue;
      }
      if ($field_conf->getType() == 'entity_reference') {
        $tax_fields[$field_name] = $lable;
      }
    }
    // Field name of event taxonomy.
    $form['tax_field'] = [
      '#title' => $this->t('Event Taxonomy Field'),
      '#description' =>  t('In order to specify colors for event taxonomies, you must select a taxonomy reference field for the View.'),
      '#type' => 'select',
      '#options' => $tax_fields,
      '#empty_value' => '',
      '#disabled' => empty($tax_fields),
      '#fieldset' => 'colors',
      '#default_value' => (!empty($this->options['tax_field'])) ? $this->options['tax_field'] : '',
    ];
    // Color for vocabularies.
    $form['vocabularies'] = [
      '#title' => $this->t('Vocabularies'),
      '#type' => 'select',
      '#options' => $cabNames,
      '#empty_value' => '',
      '#fieldset' => 'colors',
      '#description' =>  t('Specify which vocabulary is using for calendar event color. If the vocabulary selected is not the one that the taxonomy field belonging to, the color setting would be ignored.'),
      '#default_value' => (!empty($this->options['vocabularies'])) ? $this->options['vocabularies'] : '',
      '#states' => array(
        // Only show this field when the 'tax_field' is selected.
        'invisible' => [
          [':input[name="style_options[tax_field]"]' => ['value' => '']],
        ],
      ),
      '#ajax' => [
        'callback' => 'Drupal\fullcalendar_view\Plugin\views\style\FullCalendarDisplay::taxonomyColorCallback',
        'event' => 'change',
        'wrapper' => 'color-taxonomies-div',
        'progress' => [
          'type' => 'throbber',
          'message' => t('Verifying entry...'),
        ],
      ],
    ];
    
    if (!isset($form_state->getUserInput()['style_options'])) {
      // Taxonomy color service.
      $taxonomy_color_service = \Drupal::service('fullcalendar_view.taxonomy_color');
      // Taxonomy color input boxes.
      $form['color_taxonomies'] = $taxonomy_color_service->colorInputBoxs($this->options['vocabularies'], $this->options['color_taxonomies']);
    }
    // Content type colors.
    $form['color_contents'] = array(
      '#type' => 'details',
      '#title' => t('Colors for Content Types'),
      '#description' =>  t('Specify colors for each content type. If taxonomy color is specified, this settings would be ignored.'),
      '#fieldset' => 'colors',
    );
    // All content types.
    $contentTypes = \Drupal::service('entity_type.manager')
    ->getStorage('node_type')
    ->loadMultiple();
    // Options list.
    $contentTypesList = [];
    foreach ($contentTypes as $contentType) {
      $id = $contentType->id();
      $label = $contentType->label();
      $contentTypesList[$id] = $label;
      // Content type colors.
      $form['color_contents'][$id] = array(
        '#title' => $label,
        '#default_value' => isset($this->options['color_contents'][$id]) ? $this->options['color_contents'][$id] : '#3a87ad',
        '#type' => 'color',
      );
    }
    $moduleHandler = \Drupal::service('module_handler');
    if ($moduleHandler->moduleExists('calendar_recurring_event')){
      // Recurring event.
      $form['recurring'] = array(
          '#type' => 'details',
          '#title' => t('Recurring event settings'),
          // '#description' =>  t('Settings for recurring event.'),
      );
      // Recurring business start time.
      $form['business_start'] = array(
          '#type' => 'datetime',
          '#title' => t('Business start time'),
          '#description' =>  t('The time of a day when a recurring all day event starts. The recurring events whose start date include hour and minute will use their respective start time instead.'),
          '#fieldset' => 'recurring',
          '#default_value' => empty($this->options['business_start']) ? new DrupalDateTime('2018-02-24T08:00:00') : new DrupalDateTime($this->options['business_start']),
          '#date_date_element' => 'none', // hide date element
          '#date_time_element' => 'time', // you can use text element here as well
          '#date_time_format' => 'H:i'
      );
      // Recurring business end time.
      $form['business_end'] = array(
          '#type' => 'datetime',
          '#title' => t('Business end time'),
          '#description' =>  t('The time of a day when a recurring event ends. The recurring events whose end date include hour and minute will use their respective end time instead.'),
          '#fieldset' => 'recurring',
          '#default_value' => empty($this->options['business_end']) ? new DrupalDateTime('2018-02-24T18:00:00') : new DrupalDateTime($this->options['business_end']),
          '#date_date_element' => 'none', // hide date element
          '#date_time_element' => 'time', // you can use text element here as well
          '#date_time_format' => 'H:i'
      );
    }
    // New event content type.
    $form['content_type'] = [
      '#title' => $this->t('Event content type'),
      '#description' => $this->t('The content type of a new event. Once this is set, you can create a new event by double clicking a calendar entry.'),
      '#type' => 'select',
      '#options' => $contentTypesList,
      '#default_value' => (!empty($this->options['content_type'])) ? $this->options['content_type'] : '',
    ];
    // Extra CSS classes.
    $form['classes'] = array(
      '#type' => 'textfield',
      '#title' => t('CSS classes'),
      '#default_value' => (isset($this->options['classes'])) ? $this->options['classes'] : '',
      '#description' => t('CSS classes for further customization of this view.'),
    );
  }
  
  /**
   * Options form submit handle function
   * 
   * @see \Drupal\views\Plugin\views\PluginBase::submitOptionsForm()
   */
  public function submitOptionsForm(&$form, FormStateInterface $form_state) {
    $options = &$form_state->getValue('style_options');
    $input_value = $form_state->getUserInput();
    $input_colors = isset($input_value['style_options']['color_taxonomies']) ? $input_value['style_options']['color_taxonomies'] : array();
    // Save the input of colors.
    foreach ($input_colors as $id => $color) {
      if (!empty($color)) {
        $options['color_taxonomies'][$id] = $color;
      }
    }
    // Datetime fields in Drupal 8 are stored as strings.
    if (isset($options['business_start'])) {
      $options['business_start'] = $options['business_start']->format(DATETIME_DATETIME_STORAGE_FORMAT);
    }
    if (isset($options['business_end'])) {
      $options['business_end'] = $options['business_end']->format(DATETIME_DATETIME_STORAGE_FORMAT);
    }
    
    parent::submitOptionsForm($form, $form_state);
  }
  
  /*
   * Taxonomy colors Ajax callback function 
   */
  public static function taxonomyColorCallback(array &$form, FormStateInterface $form_state) {
    $options = $form_state->getValue('style_options');
    $vid = $options['vocabularies'];
    $taxonomy_color_service = \Drupal::service('fullcalendar_view.taxonomy_color');
   
    if (isset($options['color_taxonomies'])) {
      $defaultValues = $options['color_taxonomies'];
    }
    else {
      $defaultValues = array();
    }
    // Taxonomy color boxes.
    $form['color_taxonomies'] = $taxonomy_color_service->colorInputBoxs($vid, $defaultValues, TRUE);
    
    return $form['color_taxonomies'];
  }
}