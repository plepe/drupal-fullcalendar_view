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
    // Field machine name of event duration.
    $duration_field = isset($options['duration']) ? $options['duration'] : NULL;
    // Field machine name of excluding dates field.
    $rrule_field = isset($options['rrule']) ? $options['rrule'] : NULL;
    
    // Default date of the calendar.
    switch ($options['default_date_source']) {
      case 'now':
        $default_date = date('Y-m-d');
        break;
        
      case 'fixed':
        $default_date = $options['defaultDate'];
        break;
        
      default:
        // Don't do anything, we'll set it below.
    }
    // Default Language.
    $default_lang = $options['defaultLanguage'] === 'current_lang' ? $this->fullcalendar_view_map_langcodes($language->getId()) : $options['defaultLanguage'];
    // Color for bundle types.
    $color_content = $options['color_bundle'];
    // Color for taxonomies.
    $color_tax = $options['color_taxonomies'];
    // Date fields.
    $start_field_option = $fields[$start_field]->options;
    $end_field_option = empty($end_field) ? NULL : $fields[$end_field]->options;
    // Custom timezone or user timezone.
    $timezone = !empty($start_field_option['settings']['timezone_override']) ?
    $start_field_option['settings']['timezone_override'] : date_default_timezone_get();
    // Title field machine name.
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
    $left_buttons = Xss::filter($options['left_buttons']);
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
        // Result entity of current row.
        $current_entity = $row->_entity;
        // Start field is vital, if it doesn't exist then ignore this entity.
        if (!$current_entity->hasField($start_field)) {
          continue;
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
            if (isset($fields[$des_field_name])
                && method_exists($fields[$des_field_name], 'advancedRender')) {
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
          'description' => Xss::filterAdmin($des),
          'id' => $entity_id,
          'url' => $current_entity->toUrl()->toString(),
        ];
        // Event duration.
        if (!empty($duration_field) && !empty($fields[$duration_field])) {
          $entry['duration'] = $fields[$duration_field]->advancedRender($row);
        }
        if (!empty($start_date)) {
          // There might be more than one value for a field,
          // but we only need the first one and ignore others.
          $start_date = $start_date[0]['value'];
          // Examine the field type.
          if ($start_field_option['type'] === 'timestamp') {
            $start_date = intval($start_date);
            $start_date = date(DATE_ATOM, $start_date);
          }
          elseif (strpos($start_field_option['type'], 'datetime') === FALSE
              && strpos($start_field_option['type'], 'daterange') === FALSE) {
                if (empty($variables['fullcalendar_fieldtypes'])) {
                  // This field is not a valid date time field.
                  continue;
                }
                else {
                  $valid = FALSE;
                  // checking supported field types form plugin defintions
                  foreach($variables['fullcalendar_fieldtypes'] as $fieldtype) {
                    if (strpos($start_field_option['type'], $fieldtype) === 0) {
                      $valid = TRUE;
                      break;
                    }
                  }
                  if (!$valid) {
                    // This field is not a valid date time field.
                    continue;
                  }
                }
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
            $entry['start'] = $start_date;
            $entry['allDay'] = true;
          }
          else {
            // Drupal store date time in UTC timezone.
            // So we need to convert it into user timezone.
            $entry['start'] = $timezone_service->utcToLocal($start_date, $timezone, DATE_ATOM);
          }
        }
        else {
          continue;
        }
        
        // Deal with the end date in the same way as start date above.
        if (!empty($end_date)) {
          if ($end_field_option['type'] === 'timestamp') {
            $end_date = $end_date[0]['value'];
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
            $end_date = $end_date[0]['value'];
          }
          
          if (!empty($end_date)) {
            $all_day = (strlen($end_date) < 11) ? TRUE : FALSE;
            if ($all_day) {
              $end = new DrupalDateTime($end_date);
              // The end date is inclusive for a all day event,
              // which is not what we want. So we need one day offset.
              $end->modify('+1 day');
              $entry['end'] = $end->format('Y-m-d');
              $entry['allDay'] = true;
            }
            else {
              // Drupal store date time in UTC timezone.
              // So we need to convert it into user timezone.
              $entry['end'] = $timezone_service->utcToLocal($end_date, $timezone, DATE_ATOM);
            }
          }
        }
        else {
          // Without end date field, this event can't be resized.
          $entry['eventDurationEditable'] = FALSE;
        }
        // Set the color for this event.
        if (isset($event_type) && isset($color_tax[$event_type])) {
          $entry['backgroundColor'] = $color_tax[$event_type];
        }
        elseif (isset($color_content[$entity_bundle])) {
          $entry['backgroundColor'] = $color_content[$entity_bundle];
        }
        // Recurring event.
        if (!empty($rrule_field)) {
          $rrule = $current_entity->hasField($rrule_field) ? $current_entity->get($rrule_field)->getString() : '';
          if (!empty($rrule)) {
            $entry['rrule'] = Xss::filter($rrule);
            // Recurring events are read-only.
            $entry['editable'] = FALSE;
          }
        }
        // Add this event into the array.
        $entries[] = $entry;
      }
      
      // Remove the row_index property as we don't it anymore.
      unset($view->row_index);
      // Load tooltip plugin.
      if (!empty($des_field)) {
        $variables['#attached']['library'][] = 'fullcalendar_view/tooltip';
      }
      $calendar_options = [
        'plugins' => [ 'moment','interaction', 'dayGrid', 'timeGrid', 'list', 'rrule' ],
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

