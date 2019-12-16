<?php

namespace Drupal\fullcalendar_view\Plugin\FullcalendarViewProcessor;

use Drupal\fullcalendar_view\Plugin\FullcalendarViewProcessorBase;

/**
 * Current language plugin.
 *
 * @FullcalendarViewProcessor(
 *   id = "fullcalendar_view_language",
 *   label = @Translation("Current language")
 * )
 */
class CurrentLanguage extends FullcalendarViewProcessorBase {
  public function process(array &$variables) {
    $view = $variables['view'];
    $style = $view->style_plugin;
    $options = $style->options;
    
    // Update the default language, only if it is specified as current active language.
    if ($options['defaultLanguage'] === 'current_lang') {
      // Get current language.
      $language = \Drupal::languageManager()->getCurrentLanguage();
      
      $default_lang = $this->fullcalendar_view_map_langcodes($language->getId());
      $entries = isset($variables['#attached']['drupalSettings']['fullCalendarView']) ? $variables['#attached']['drupalSettings']['fullCalendarView'] : [];
      
      // Go through all results of the view.
      foreach ($view->result as $row) {
        // Result entity of current row.
        $current_entity = $row->_entity;
        
        // Update the URL for the view entry refer to current row.
        foreach ($entries as &$entry) {
          if ($entry['id'] === $current_entity->id()) {
            $entry['url'] = $current_entity->toUrl('canonical', ['language' => $language])->toString();
            break;
          }
        }
      }
      
      // Set the default language.
      $variables['#attached']['drupalSettings']['defaultLang'] = $default_lang;
      // Update the entry URL in default language.
      if ($entries) {
        $variables['#attached']['drupalSettings']['fullCalendarView'] = $entries;
      }
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

