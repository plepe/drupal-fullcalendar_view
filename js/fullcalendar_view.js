/**
 * @file
 * Fullcalendar View plugin JavaScript file.
 */

(function($, Drupal) {
  Drupal.behaviors.fullcalendarView = {
    attach: function(context, settings) {
      $('.js-drupal-fullcalendar', context)
        .once("absCustomBehavior")
        .each(function() {
          
          var calendarEl = document.getElementsByClassName("js-drupal-fullcalendar");
          
          if (calendarEl) { 
            for (let i = 0; i < calendarEl.length; i++) {
              var calendar = new FullCalendar.Calendar(calendarEl[i], JSON.parse(drupalSettings.calendar_options)); //
              
              calendar.render();
            }
            
          }
        });
    }
  };
})(jQuery, Drupal);
