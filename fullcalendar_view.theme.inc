<?php

/**
 * @file
 * Theme for Full Calendar views.
 */

/**
 * Implement fullcalendar view theme preprocess function.
 *
 * @param array $variables
 *   Template variables.
 */
function template_preprocess_views_view_fullcalendar(array &$variables) {
  // Fucllcalendar View preprocess service.
  $preprocess_service = \Drupal::service('fullcalendar_view.view_preprocess');
  
  $preprocess_service->process($variables);
}
