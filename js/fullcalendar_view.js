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
          var options = {
              plugins: [ 'interaction', 'dayGrid', 'timeGrid' ],
              defaultView: 'dayGridMonth',
              defaultDate: '2020-02-07',
              header: {
                left: 'prev,next today',
                center: 'title',
                right: 'dayGridMonth,timeGridWeek,timeGridDay'
              },
          };
          
          if (calendarEl) { 
            for (let i = 0; i < calendarEl.length; i++) {
              var calendar = new FullCalendar.Calendar(calendarEl[i], options);
              
              calendar.render();
            }
            
          }
        });
    }
  };
})(jQuery, Drupal);
