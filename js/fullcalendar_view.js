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
          let calendarOptions = JSON.parse(drupalSettings.calendar_options);
          calendarOptions.eventRender = eventRender;
          if (calendarEl) { 
            for (let i = 0; i < calendarEl.length; i++) {
              var calendar = new FullCalendar.Calendar(calendarEl[i], calendarOptions); //
              
              calendar.render();
            }
            
          }
        });
      
      function eventRender (info) {
        // Event title html markup.
        let eventTitleEle = info.el.getElementsByClassName('fc-title');
        if(eventTitleEle.length > 0) {
          eventTitleEle[0].innerHTML = info.event.title;
        }
        // Event list tile html markup.
        let eventListTitleEle = info.el.getElementsByClassName('fc-list-item-title');
        if(eventListTitleEle.length > 0) {
          eventListTitleEle[0].innerHTML = info.event.title;
        }
      }
    }
  };
})(jQuery, Drupal);
