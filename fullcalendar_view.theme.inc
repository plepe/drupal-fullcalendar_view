<?php

/**
 * @file
 * Theme for Full Calendar views.
 */

use Drupal\Core\Datetime\DrupalDateTime;

/**
 * Implement fullcalendar view theme preprocess function.
 *
 * @param array $variables
 *   Template variables.
 */
function template_preprocess_views_view_fullcalendar(array &$variables) {
  global $base_path;

  $view = $variables['view'];
  $style = $view->style_plugin;
  $options = $style->options;
  $fields = $view->field;
  // Get current language.
  $language = \Drupal::languageManager()->getCurrentLanguage();
  if ($language->isDefault()) {
    $variables['language'] = $base_path;
  }
  else {
    $variables['language'] = $base_path . $language->getId() . '/';
  }
  // Current user.
  $user = $variables['user'];
  // New event content type.
  $event_content_type = $options['content_type'];
  // Update options for twig.
  $variables['options'] = $options;
  // Hide the create event link from user who doesn't have the permission
  // or if this feature is turn off.
  $variables['showAddEvent'] = (!empty($user) && $user->hasPermission("create $event_content_type content")) && $options['createEventLink'] ? TRUE : FALSE;

  // Field machine name of start date.
  $start_field = $options['start'];
  // Start date field is critical.
  if (empty($start_field)) {
    return;
  }
  // Field machine name of end date.
  $end_field = $options['end'];
  // Field machine name for event description.
  $des_field = $options['des'];
  // Field machine name of taxonomy field.
  $tax_field = $options['tax_field'];
  // Default date of the calendar.
  $default_date = $options['defaultDate'];
  // Default Language.
  $default_lang = $options['defaultLanguage'];
  // Color for content types.
  $color_content = $options['color_contents'];
  // Color for taxonomies.
  $color_tax = $options['color_taxonomies'];
  // Date fields.
  $start_field_option = $fields[$start_field]->options;
  $end_field_option = empty($end_field) ? NULL : $fields[$end_field]->options;
  // Custom timezone or user timezone.
  $timezone = !empty($start_field_option['settings']['timezone_override']) ?
    $start_field_option['settings']['timezone_override'] : drupal_get_user_timezone();
  // Open a new window for details of an event.
  $link_to_entity = $fields['title']->options['settings']['link_to_entity'];
  // Right side buttons.
  $right_buttons = 'month';
  foreach ($options['right_buttons'] as $key => $value) {
    if ($value !== 0) {
      $right_buttons .= ',' . $key;
    }
  }
  $entries = [];

  if (!empty($start_field)) {
    // Timezone conversion service.
    $timezone_service = \Drupal::service('fullcalendar_view.timezone_conversion_service');
    // Save view results into entries array.
    foreach ($view->result as $row) {
      // Days of a week for a recurring event.
      $dow = NULL;
      // Days of a month for a recurring event.
      $dom = NULL;
      $monthly = NULL;
      $weekly = NULL;
      // Recurring range.
      $range = NULL;
      // Result entity of current row.
      $current_entity = $row->_entity;
      // Start field is vital, if it doesn't exist then ignore this entity.
      if (!$current_entity->hasField($start_field)) {
        continue;
      }
      // Monthly recurring.
      if ($current_entity->hasField('field_monthly_event')) {
        $monthly = $current_entity->get('field_monthly_event')->getValue();
      }
      // If the monthly recurring is set, the weekly recurring will be ignored.
      if (!empty($monthly)) {
        $dom = [];
        foreach ($monthly as $day) {
          if (!empty($day['value'])) {
            $dom[] = $day['value'];
          }
        }
      }
      // Weekly recurring.
      elseif ($current_entity->hasField('field_weekly_event')) {
        $weekly = $current_entity->get('field_weekly_event')->getValue();
      }
      if (!empty($weekly)) {
        $dow = [];
        foreach ($weekly as $day) {
          if (isset($day['value'])) {
            // Sunday is 0.
            $dow[] = $day['value'] % 7;
          }
        }

        if (!empty($dow)) {
          $dow = '[' . implode(',', $dow) . ']';
        }
      }
      // Entity id.
      $entity_id = $current_entity->id();
      // Entity bundle type.
      $entity_bundle = $current_entity->bundle();
      // Background color based on taxonomy field.
      if (!empty($tax_field) && $current_entity->hasField($tax_field)) {
        // Event type.
        $event_type = $current_entity->get($tax_field)->target_id;
      }
      // Calendar event start date.
      $start_date = $current_entity->get($start_field)->getValue();
      // Calendar event end date.
      $end_date = empty($end_field) || !$current_entity->hasField($end_field) ? '' : $current_entity->get($end_field)->getValue();
      // Description for events.
      $des = empty($des_field) || !$current_entity->hasField($des_field) ? '' : $current_entity->get($des_field)->getValue();
      if (is_array($des)) {
        $des = reset($des);
        if (isset($des['value'])) {
          $des = $des['value'];
        }
      }
      // Event title.
      $title = $current_entity->label();
      $entry = [
        'title' => $title,
        'description' => $des,
        'id' => $entity_id,
        'url' => $current_entity->toUrl()->toString(),
      ];

      if (!empty($start_date)) {
        // There might be more than one value for a field,
        // but we only need the first one and ignore others.
        $start_date = reset($start_date)['value'];
        // Examine the field type.
        if ($start_field_option['type'] === 'timestamp') {
          $start_date = intval($start_date);
          $start_date = date(DATE_ATOM, $start_date);
        }
        elseif (strpos($start_field_option['type'], 'datetime') === FALSE && strpos($start_field_option['type'], 'daterange') === FALSE) {
          // This field is not a valid date time field.
          continue;
        }
        // A user who doesn't have the permission can't edit an event.
        if (!$current_entity->access('update')) {
          $entry['editable'] = FALSE;
        }

        $all_day = (strlen($start_date) < 11) ? TRUE : FALSE;

        if ($all_day) {
          // By default, Drupal store date time date in UTC timezone.
          // So we need to convert it into user timezone.
          $start = $timezone_service->utcToLocal($start_date, $timezone, 'Y-m-d');
          // Recurring event.
          if (!empty($dow) || !empty($dom)) {
            if (empty($options['business_start'])) {
              $business_start = new DrupalDateTime('2018-02-24T08:00:00');
            }
            else {
              $business_start = new DrupalDateTime($options['business_start']);
            }
            $entry['start'] = $business_start->format('H:i');
            $range['start'] = $start;
          }
          else {
            $entry['start'] = $start;
          }
        }
        else {
          // Recurring event.
          if (!empty($dow) || !empty($dom)) {
            $format = 'H:i';
            $range['start'] = $timezone_service->utcToLocal($start_date, $timezone, 'Y-m-d');
          }
          else {
            $format = DATE_ATOM;
          }
          // By default, Drupal store date time date in UTC timezone.
          // So we need to convert it into user timezone.
          $entry['start'] = $timezone_service->utcToLocal($start_date, $timezone, $format);
        }
      }
      else {
        continue;
      }
      // Cope with end date in the same way as start date above.
      if (!empty($end_date)) {

        if ($end_field_option['type'] === 'timestamp') {
          $end_date = reset($end_date)['value'];
          $end_date = intval($end_date);
          $end_date = date(DATE_ATOM, $end_date);
        }
        elseif (strpos($end_field_option['type'], 'daterange') !== FALSE) {
          $end_date = $end_date[0]['end_value'];
        }
        elseif (strpos($end_field_option['type'], 'datetime') === FALSE) {
          // This field is not a valid date time field.
          $end_date = '';
        }
        else {
          $end_date = reset($end_date)['value'];
        }

        if (!empty($end_date)) {
          $all_day = (strlen($end_date) < 11) ? TRUE : FALSE;
          if ($all_day) {
            // As designed, the end date is inclusive for all day event,
            // which is not what we want. So we need one day offset.
            $end = $timezone_service->utcToLocal($end_date, $timezone, 'Y-m-d', '+1 day');
            // Recurring event.
            if (!empty($dow) || !empty($dom)) {
              if (!empty($options['business_end'])) {
                $business_end = new DrupalDateTime($options['business_end']);
              }
              else {
                $business_end = new DrupalDateTime('2018-02-24T18:00:00');
              }
              $entry['end'] = $business_end->format('H:i');
              $range['end'] = $end;
            }
            else {
              $entry['end'] = $end;
            }
          }
          else {
            // Recurring event.
            if (!empty($dow) || !empty($dom)) {
              $format = 'H:i';
              $range['end'] = $timezone_service->utcToLocal($end_date, $timezone, 'Y-m-d', '+1 day');
            }
            else {
              $format = DATE_ATOM;
            }

            $entry['end'] = $timezone_service->utcToLocal($end_date, $timezone, $format);
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

      if (!empty($dom)) {
        $entry['dom'] = $dom;
        $entry['ranges'] = [$range];
        // Recurring event is not editable.
        $entry['editable'] = FALSE;
      }
      elseif (!empty($dow)) {
        $entry['dow'] = $dow;
        $entry['ranges'] = [$range];
        // Recurring event is not editable.
        $entry['editable'] = FALSE;
      }
      $entries[] = $entry;
    }

    // Can the user add a new event?
    $dbl_click_to_create = FALSE;
    if ($user->hasPermission("create $event_content_type content")) {
      $dbl_click_to_create = TRUE;
    }

    // Load tooltip plugin.
    if (!empty($des_field)) {
      $variables['#attached']['library'][] = 'fullcalendar_view/tooltip';
    }
    // Load the fullcalendar js library.
    $variables['#attached']['library'][] = 'fullcalendar_view/fullcalendar';
    // Pass data to js file.
    $variables['#attached']['drupalSettings'] = [
      'fullCalendarView' => $entries,
      'defaultDate' => empty($default_date) ? date('Y-m-d') : $default_date,
      'defaultLang' => $default_lang,
      'languageSelector' => $options['languageSelector'],
      'alloweventOverlap' => $options['alloweventOverlap'],
      'linkToEntity' => $link_to_entity,
      'eventContentType' => $event_content_type,
      'startField' => $start_field,
      'endField' => $end_field,
      'rightButtons' => $right_buttons,
      'dblClickToCreate' => $dbl_click_to_create,
	  'navLinks' => $options['nav_links'],
    ];
  }
}
