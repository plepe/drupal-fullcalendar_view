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
  // Start date field is critical.
  if (empty($start_field)) {
    return;
  }
  // Field machine name of end date.
  $end_field = $options['end'];
  // Field machine name of taxonomy field.
  $tax_field = $options['tax_field'];
  // Default date of the calendar.
  $default_date = $options['defaultDate'];
  // New event content type.
  $event_content_type = $options['content_type'];
  // Color for content types.
  $color_content = $options['color_contents'];
  // Color for taxonomies.
  $color_tax = $options['color_taxonomies'];
  // Date fields.
  $start_field_option = $fields[$start_field]->options;
  $end_field_option = empty($end_field) ? null : $fields[$end_field]->options;
  // Custom timezone or user timezone.
  $timezone = !empty($start_field_option['settings']['timezone_override']) ? 
    $start_field_option['settings']['timezone_override'] : drupal_get_user_timezone();
  // Open a new window for details of an event.
  $link_to_entity = $fields['title']->options['settings']['link_to_entity'];
  $entries = array();
  
  if (!empty($start_field)) {
    // Timezone conversion service.
    $timezone_service = \Drupal::service('fullcalendar_view.timezone_conversion_service');
    // Save view results into entries array.
    foreach ($view->result as $index => $row) {
      // Result entity of current row.
      $current_entity = $row->_entity;
      // Start field is vital, if it doesn't exist for this entity then ignore it.
      if (!$current_entity->hasField($start_field)) {
        continue;
      }
      // Entity id.
      $entity_id = $current_entity->id();
      // Entity bundle type.
      $entity_bundle = $current_entity->bundle();
      // Entity type.
      $entity_type = $current_entity->getEntityTypeId();
      // Background color based on taxonomy field.
      if (!empty($tax_field) && $current_entity->hasField($tax_field)) {
        // Event type.
        $event_type = $current_entity->get($tax_field)->target_id;
      }
      // Calendar event start date. 
      $start_date = $current_entity->get($start_field)->getValue();
      // Calendar event end date.
      $end_date = empty($end_field) || !$current_entity->hasField($end_field) ? '' : $current_entity->get($end_field)->getValue();
      // Event title.
      $title = strip_tags($style->getField($index, 'title'));
      $entry = [
        'title' => $title,
        'id' => $entity_id,
        'url' => '/' . $entity_type . '/' . $entity_id];
       
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
        
        $all_day = (strlen($start_date) < 11) ? TRUE : FALSE;
        // By default, Drupal store date time date in UTC timezone.
        // So we need to convert it into user timezone.
        $entry['start'] = $timezone_service->utcToLocal($start_date, $timezone, $all_day);
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
          $all_day = (strlen($end_date) < 11) ? TRUE : FALSE;
          // As designed, the end date is inclusive for all day event,
          // which is not what we want. So we need one day offset.
          if ($all_day) {
            $entry['end'] = $timezone_service->utcToLocal($end_date, $timezone, $all_day, '+1 day');
          }
          else {
            $entry['end'] = $timezone_service->utcToLocal($end_date, $timezone, $all_day);
          }
        }       
      }
      else {
        // Without end date field, this event can't be resized.
        $entry['durationEditable'] = FALSE;
      }
      // Set the color for this event.
      if (isset($event_type) && isset($color_tax[$event_type])) {
        $entry['backgroundColor'] = $color_tax[$event_type];
      }
      elseif (isset($color_content[$entity_bundle])) {
        $entry['backgroundColor'] = $color_content[$entity_bundle];
      }
      
      
      $entries[] = $entry;
    }
    // Pass data to js file.
    $variables['#attached']['drupalSettings'] = array(
      'fullCalendarView' => $entries,
      'defaultDate' => empty($default_date) ? date('Y-m-d') : $default_date,
      'linkToEntity' => $link_to_entity,
      'eventContentType' => $event_content_type,
      'startField' => $start_field,
      'endField' => $end_field,
    );
  }
}