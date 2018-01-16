<?php
/**
 * @file
 * Theme for Full Calendar views.
 */

/**
 * Implement fullcalendar view theme preprocess function
 * @param $variables
 */
function template_preprocess_views_view_fullcalendar(&$variables) {
  
  // Load the fullcalendar js library.
  $variables['#attached']['library'][] =  'fullcalendar_view/fullcalendar';
  
  $view = $variables['view'];

  $style = $view->style_plugin;
  $options = $style->options;
  $fields = $view->field;
  // Update options for twig.
  $variables['options'] = $options;
  
  // Field machine name of start date.
  $start_field = $options['start'];
  // Field machine name of end date.
  $end_field = $options['end'];
  // Default date of the calendar.
  $default_date = $options['defaultDate'];
  
  $start_field_option = $fields[$start_field]->options;
  $end_field_option = $fields[$end_field]->options;
  // Custom timezone or user timezone.
  $timezone = !empty($start_field_option['settings']['timezone_override']) ? $start_field_option['settings']['timezone_override'] : drupal_get_user_timezone();
  // Open a new window for details of an event.
  $link_to_entity = $fields['title']->options['settings']['link_to_entity'];
  $entries = array();
  
  if (!empty($start_field) && !empty($end_field)) {
    // Timezone conversion service.
    $timezone_service = \Drupal::service('fullcalendar_view.timezone_conversion_service');
    // Save view results into entries array.
    foreach ($view->result as $index => $row) {
      // Result entity of current row.
      $current_entity = $row->_entity;
      // Entity id.
      $entity_id = $current_entity->id();
      // Calendar event start date. 
      $start_date = $current_entity->get($start_field)->getValue();
      // Calendar event end date.
      $end_date = $current_entity->get($end_field)->getValue();
      // Event title.
      $title = strip_tags($style->getField($index, 'title'));
      $entry = [
        'title' => $title,
        'id' => $entity_id,
        'url' => '/' . $current_entity->getEntityTypeId() . '/' . $entity_id];
       
      if (!empty($start_date)) {
        // There might be more than one value for a field,
        // but we only need the first one and ignore others.
        $start_date = reset($start_date)['value'];  
        // Examine the field type
        if ($start_field_option['type'] === 'timestamp') {
          $start_date = intval($start_date);
          $start_date = date(DATE_ATOM, $start_date);
        }
        elseif (strpos($start_field_option['type'], 'datetime') === FALSE) {
          // This field is not a valid date time field.
          continue;
        }
        
        // By default, Drupal store date time date in UTC timezone.
        // So we need to convert it into user timezone.
        $start_date = $timezone_service->utcToLocal($start_date, $timezone);
        $entry['start'] = $start_date;
      }
      // Cope with end date in the same way as start date above.
      if (!empty($end_date)) {
        $end_date = reset($end_date)['value'];
        
        if ($end_field_option['type'] === 'timestamp') {
          $end_date = intval($end_date);
          $end_date = date(DATE_ATOM, $end_date);
        }
        elseif (strpos($end_field_option['type'], 'datetime') === FALSE) {
          // This field is not a valid date time field.
          $end_date = '';
        }
        
        if (!empty($end_date)) {         
          $entry['end'] = $timezone_service->utcToLocal($end_date, $timezone);
        }       
      }
      
      $entries[] = $entry;
    }
    // Pass data to js file.
    $variables['#attached']['drupalSettings'] = array(
      'fullCalendarView' => $entries,
      'defaultDate' => empty($default_date) ? date('Y-m-d') : $default_date,
      'linkToEntity' => $link_to_entity,
    );
  }
}