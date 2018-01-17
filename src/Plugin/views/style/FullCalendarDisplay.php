<?php
namespace Drupal\fullcalendar_view\Plugin\views\style;

use Drupal\core\form\FormStateInterface;
use Drupal\views\Plugin\views\style\StylePluginBase;

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
    $options['content_type'] = array('default' => '');
    return $options;
  }
  
  /**
   * {@inheritdoc}
   */
  public function buildOptionsForm(&$form, FormStateInterface $form_state) {
    parent::buildOptionsForm($form, $form_state);
    
    // Default date of the calendar.
    $form['defaultDate'] = array(
      '#type' => 'date',
      '#title' => t('Default Date'),
      '#default_value' => (isset($this->options['defaultDate'])) ? $this->options['defaultDate'] : '',
      '#description' => t('The initial date displayed when the calendar first loads. It should be in ISO 8601 format. For example: 2018-01-24'),
    );
    
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
    // All content types.
    $contentTypes = \Drupal::service('entity_type.manager')
    ->getStorage('node_type')
    ->loadMultiple();
    // Options list.
    $contentTypesList = [];
    foreach ($contentTypes as $contentType) {
      $contentTypesList[$contentType->id()] = $contentType->label();
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
  
}