<?php

namespace Drupal\fullcalendar_view;

use Drupal\Component\Utility\Xss;
use Drupal\Core\Datetime\DrupalDateTime;

class FullcalendarViewPreprocess {
  
  /**
   * Process the view variable array.
   * 
   * @param array $variables
   *   Template variables.
   */
  public function process(array &$variables) {   
    $view = $variables['view'];
    $style = $view->style_plugin;
    $options = $style->options;
    $fields = $view->field;
    
    // Get current language.
    $language = \Drupal::languageManager()->getCurrentLanguage();
    
    // Current user.
    $user = $variables['user'];
    // CSRF token.
    $token = '';
    if (!$user->isAnonymous()) {
      $token = \Drupal::csrfToken()->get($user->id());
    }
    // New event bundle type.
    $event_bundle_type = $options['bundle_type'];
    $entity_type = $view->getBaseEntityType();
    if ($entity_type->id() === 'node') {
      $add_form = 'node/add/' . $event_bundle_type;
    }
    else {
      $entity_links = $entity_type->get('links');
      if (isset($entity_links['add-form'])) {
        $add_form = str_replace('{' . $entity_type->id() . '}', $event_bundle_type, $entity_links['add-form']);
      }
      elseif (isset($entity_links['add-page'])) {
        $add_form = str_replace('{' . $entity_type->id() . '}', $event_bundle_type, $entity_links['add-page']);
      }
    }
    
    // Can the user add a new event?
    $entity_manager = \Drupal::entityTypeManager();
    $access_handler = $entity_manager->getAccessControlHandler($entity_type->id());
    $dbl_click_to_create = FALSE;
    if ($access_handler->createAccess($event_bundle_type)) {
      $dbl_click_to_create = TRUE;
    }
    // Pass entity type to twig template.
    $variables['entity_id'] = $entity_type->id();
    // Update options for twig.
    $variables['options'] = $options;
    // Hide the create event link from user who doesn't have the permission
    // or if this feature is turn off.
    $variables['showAddEvent'] = $dbl_click_to_create
    && $options['createEventLink'];
    // Time format
    $timeFormat = $options['timeFormat'];
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
    // Field machine name of excluding dates field.
    $exc_field = isset($options['excluding_dates']) ? $options['excluding_dates'] : NULL;
    
    // Default date of the calendar.
    switch ($options['default_date_source']) {
      case 'now':
        $default_date = date('Y-m-d');
        break;
        
      case 'fixed':
        $default_date = $options['defaultDate'];
        break;
        
      case 'first':
        // Don't do anything, we'll set it below.
        break;
    }
    // Default Language.
    $default_lang = $options['defaultLanguage'];
    $default_lang = $options['defaultLanguage'] === 'current_lang' ? $this->fullcalendar_view_map_langcodes($language->getId()) : $options['defaultLanguage'];
    // Color for bundle types.
    $color_content = $options['color_bundle'];
    // Color for taxonomies.
    $color_tax = $options['color_taxonomies'];
    // Date fields.
    $start_field_option = $fields[$start_field]->options;
    $end_field_option = empty($end_field) ? NULL : $fields[$end_field]->options;
    $exc_field_option = empty($exc_field) ? NULL : $fields[$exc_field]->options;
    // Custom timezone or user timezone.
    $timezone = !empty($start_field_option['settings']['timezone_override']) ?
    $start_field_option['settings']['timezone_override'] : date_default_timezone_get();
    // Open a new window for details of an event.
    $title_field = (empty($options['title']) || $options['title'] == 'title') ? 'title' : $options['title'];
    // Calendar entries linked to entity.
    $link_to_entity = FALSE;
    if (isset($fields[$title_field]->options['settings']['link_to_entity'])) {
      $link_to_entity = $fields[$title_field]->options['settings']['link_to_entity'];
    }
    elseif (isset($fields[$title_field]->options['settings']['link'])) {
      $link_to_entity = $fields[$title_field]->options['settings']['link'];
    }
    // Set the first day setting.
    $first_day = isset($options['firstDay']) ? intval($options['firstDay']) : 0;
    // Left side buttons.
    $left_buttons = 'prev,next today';
    // Right side buttons.
    $right_buttons = Xss::filter($options['right_buttons']);
    $entries = [];
    
    if (!empty($start_field)) {
      // Timezone conversion service.
      $timezone_service = \Drupal::service('fullcalendar_view.timezone_conversion_service');
      // Save view results into entries array.
      foreach ($view->result as $row) {
        // Set the row_index property used by advancedRender function.
        $view->row_index = $row->index;
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
              $dow[] = $day['value'];
            }
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
        // Description for events. For multiple bundle types,
        // there might be more than one field specified.
        if (!empty($des_field) && is_array($des_field)) {
          // Render all other fields to so they can be used in rewrite.
          foreach ($fields as $field) {
            if (method_exists($field, 'advancedRender')) {
              $field->advancedRender($row);
            }
          }
          // We need to render the description field again,
          // in case a replacement pattern for other field.
          // For exmaple, {{field name}}.
          foreach ($des_field as $des_field_name) {
            if (isset($fields[$des_field_name]) && method_exists($fields[$des_field_name], 'advancedRender')) {
              $des_raw = $fields[$des_field_name]->advancedRender($row);
              $des = empty($des_raw) ? '' : $des_raw;
              // We just need only one description text.
              // Once we got it, quit the loop.
              break;
            }
          }
        }
        if (!isset($des)) {
          $des = '';
        }
        if (is_array($des)) {
          $des = reset($des);
          if (isset($des['value'])) {
            $des = $des['value'];
          }
        }
        // Event title.
        if (empty($options['title']) || $options['title'] == 'title') {
          $title = $fields['title']->advancedRender($row);
        }
        elseif (!empty($fields[$options['title']])) {
          $title = $fields[$options['title']]->advancedRender($row);
        }
        else {
          $title = t('Invalid event title');
        }
        $entry = [
          'title' =>  Xss::filterAdmin($title),
          'description' => $des,
          'id' => $entity_id,
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
          
          // If we don't yet know the default_date (we're configured to use the
          // date from the first row, and we haven't set it yet), do so now.
          if (!isset($default_date)) {
            // Only use the first 10 digits since we only care about the date.
            $default_date = substr($start_date, 0, 10);
          }
          
          $all_day = (strlen($start_date) < 11) ? TRUE : FALSE;
          
          if ($all_day) {
            // Recurring event.
            if (!empty($dow) || !empty($dom)) {
              if (empty($options['business_start'])) {
                $business_start = new DrupalDateTime('2018-02-24T08:00:00');
              }
              else {
                $business_start = new DrupalDateTime($options['business_start']);
              }
              $entry['start'] = $business_start->format('H:i');
              $range['start'] = $start_date;
            }
            else {
              $entry['start'] = $start_date;
            }
          }
          else {
            // Recurring event.
            if (!empty($dow) || !empty($dom)) {
              $format = 'H:i';
              $range['start'] = $timezone_service->utcToLocal($start_date, $timezone, 'Ymd');
            }
            else {
              $format = DATE_ATOM;
            }
            // By default, Drupal store date time in UTC timezone.
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
              $end = new DrupalDateTime($end_date);
              // Recurring event.
              if (!empty($dow) || !empty($dom)) {
                if (!empty($options['business_end'])) {
                  $business_end = new DrupalDateTime($options['business_end']);
                }
                else {
                  $business_end = new DrupalDateTime('2018-02-24T18:00:00');
                }
                $entry['end'] = $business_end->format('H:i');
                $range['end'] = $end->format('Ymd');
              }
              else {
                $entry['end'] = $end->format('Ymd');
              }
            }
            else {
              // Recurring event.
              if (!empty($dow) || !empty($dom)) {
                $format = 'H:i';
                $range['end'] = $timezone_service->utcToLocal($end_date, $timezone, 'Ymd');
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
        
        // Collect excluding dates values for recurring event only.
        if ((!empty($dom) || !empty($dow)) && !empty($exc_field) && !empty($exc_field_option)) {
          $exc_dates = $current_entity->hasField($exc_field) ? $current_entity->get($exc_field)->getValue() : '';
          $exc_dates_array = [];
          $ex_dates = '';
          foreach ($exc_dates as $exc_date) {
            if (!empty($exc_date['value'])) {
              if ($exc_field_option['type'] === 'timestamp') {
                $date_item = date(DATE_ATOM, intval($exc_date['value']));
              }
              elseif (strpos($exc_field_option['type'], 'datetime') !== FALSE) {
                $date_item = $exc_date['value'];
                // Drupal store date and time date in UTC timezone.
                // We need to convert it into local time zone.
                if (strlen($date_item) > 10) {
                  $date_item = $timezone_service->utcToLocal($date_item, $timezone, 'Ymd');
                }
              }
              
              // Only use the first 8 digits since we only care about the date.
              if (!empty($date_item)) {
                $exc_dates_array[] = substr($date_item, 0, 8);
              }
            }
          }
          if (!empty($exc_dates_array)) {
            $range['excluding_dates'] = $exc_dates_array;
            foreach ($range['excluding_dates'] as $ex_date) {
              $ex_dates .= "\nEXDATE:" . $ex_date;
            }
          }
        }
        // Monthly recurring events.
        if (!empty($dom)) {
          $month_day = implode(',', $dom);
          $rrule_options = 'DTSTART:' . $range['start'] .
          $ex_dates .
          "\nRRULE:" .
          'FREQ=MONTHLY;' .
          'BYMONTHDAY=' . $month_day . ';' .
          'UNTIL=' . $range['end'];

          $entry['rrule'] = $rrule_options;
          // Recurring event is not editable.
          $entry['editable'] = FALSE;
        }
        // Weekly recurring events.
        elseif (!empty($dow)) {
          $week_day = '';
          // The week day is interger. We need to convert them
          // into string.
          for ($i = 0; $i < count($dow); $i++) {
            switch ($dow[$i]) {
              case 1: 
                $by_day = 'MO';
                break;
              case 2:
                $by_day = 'TU';
                break;
              case 3:
                $by_day = 'WE';
                break;
              case 4:
                $by_day = 'TH';
                break;
              case 5:
                $by_day = 'FR';
              case 6:
                $by_day = 'SA';
                break;
              default:
                $by_day = 'SU';
            }
            
            if ($i < count($dow) - 1) {
              $week_day .= $by_day . ',';
            }
            else {
              $week_day .= $by_day;
            }
          }
          $rrule_options = 'DTSTART:' . $range['start'] .
              $ex_dates .
              "\nRRULE:" .
              'FREQ=WEEKLY;' .
              'BYDAY=' . $week_day . ';' . 
              'UNTIL=' . $range['end'];
          $entry['rrule'] = $rrule_options;
          // Recurring event is not editable.
          $entry['editable'] = FALSE;
        }
        $entries[] = $entry;
      }
      
      // Remove the row_index property as we don't it anymore.
      unset($view->row_index);
      
      // Control the column header, as used in the week/agenda display.
      $column_header_format = NULL;
      $moduleHandler = \Drupal::service('module_handler');
      // Load the rrule library if the recurring event module is enabled.
      if ($moduleHandler->moduleExists('calendar_recurring_event')){
        $variables['#attached']['library'][] = 'calendar_recurring_event/libraries.rrule';
      }
      // Load tooltip plugin.
      if (!empty($des_field)) {
        $variables['#attached']['library'][] = 'fullcalendar_view/tooltip';
      }
      $calendar_options = [
        'plugins' => [ 'interaction', 'dayGrid', 'timeGrid', 'list', 'rrule' ],
        'defaultView' => isset($options['default_view']) ? $options['default_view'] : 'dayGridMonth',
        'defaultDate' => empty($default_date) ? date('Y-m-d') : $default_date,
        'header' => [
          'left' => $left_buttons,
          'center' => 'title',
          'right' => $right_buttons
        ],
        'timeFormat' => $timeFormat,
        'firstDay' => $first_day,
        'locale' => $default_lang,
        'events' => $entries,
        'navLinks' => $options['nav_links'] !== 0,
        'columnHeaderFormat' => $column_header_format,
        'editable' => $options['updateAllowed'] !== 0,
        'eventLimit' => true, // Allow "more" link when too many events.
        'eventOverlap' => $options['allowEventOverlap'] !== 0,
      ];
      
      // Load the fullcalendar js library.
      $variables['#attached']['library'][] = 'fullcalendar_view/fullcalendar';
      // Pass data to js file.
      $variables['#attached']['drupalSettings'] = [      
        'languageSelector' => $options['languageSelector'],
        'updateConfirm' => $options['updateConfirm'],
        'dialogWindow' => $options['dialogWindow'],
        'linkToEntity' => $link_to_entity,
        'eventBundleType' => $event_bundle_type,
        'startField' => $start_field,
        'endField' => $end_field,
        'dblClickToCreate' => $dbl_click_to_create,
        'entityType' => $entity_type->id(),
        'addForm' => isset($add_form) ? $add_form : '',
        'token' => $token,
        'openEntityInNewTab' => $options['openEntityInNewTab'],       
        'calendar_options' => json_encode($calendar_options),
      ];
    }
  }
  
  /**
   * Map Drupal language codes to those used by FullCalendar.
   *
   * @param string $langcode
   *   Drupal language code.
   *
   * @return string
   *   Returns the mapped langcode.
   */
  private function fullcalendar_view_map_langcodes($langcode) {
    switch ($langcode) {
      case "en-x-simple":
        return "en";
      case "pt-pt":
        return "pt";
      case "zh-hans":
        return "zh-cn";
      case "zh-hant":
        return "zh-tw";
      default:
        return $langcode;
    }
  }
}

