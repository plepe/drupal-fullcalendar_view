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
  // Fullcalendar View preprocess service.
  $preprocess_service = \Drupal::service('fullcalendar_view.view_preprocess');
  
  $preprocess_service->process($variables);
  
  // View processor plugins.
  $processor_plugins = \Drupal::service('plugin.manager.fullcalendar_view_processor');
  // The list of available plugins:
  $plugin_definitions = $processor_plugins->getDefinitions();
  // Allow plugins to alter the view variables.
  foreach ($plugin_definitions as $definition) {
    $instance = $processor_plugins->createInstance($definition['id']);
    if (method_exists($instance, 'process')) {
      // Run the process function of the plugin instance.
      $instance->process($variables);
    }
  }
  
}
