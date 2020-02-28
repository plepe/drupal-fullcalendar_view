/**
 * @file
 * Fullcalendar View plugin JavaScript file.
 */

(function($, Drupal) {
  Drupal.behaviors.fullcalendarView = {
    attach: function(context, settings) {
      var calendarObjs = [];
      $('.js-drupal-fullcalendar', context)
        .once("fullcalendarCustomBehavior")
        .each(function() {
          
          var calendarEl = document.getElementsByClassName("js-drupal-fullcalendar");
          let calendarOptions = JSON.parse(drupalSettings.calendar_options);
          // Bind the render event handler.
          calendarOptions.eventRender = eventRender;
          // Bind the resize event handler.
          calendarOptions.eventResize = eventResize;
          // Define calendar elemetns.
          if (calendarEl) { 
            for (let i = 0; i < calendarEl.length; i++) {
              var calendar = new FullCalendar.Calendar(calendarEl[i], calendarOptions); 
              // Render the calendar.
              calendar.render();
              // Put into the calendar array.
              calendarObjs[i] = calendar;
            }
            
          }
        });
      /**
       * Event render handler
       */
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
      /**
       * Event resize handler
       */
      function eventResize(info) {
        const end = info.event.end;
        const start = info.event.start;
        let strEnd = '';
        let strStart = '';
        const formatSettings = {
            month: '2-digit',
            year: 'numeric',
            day: '2-digit',
            locale: 'sv-SE'
          };
        // define the end date string in 'YYYY-MM-DD' format.
        if (end) {
          // The end day of an event is exclusive.
          // For example, the end of 2018-09-03
          // will appear to 2018-09-02 in the calendar.
          // So we need one day subtract
          // to ensure the day stored in Drupal
          // is the same as when it appears in
          // the calendar.
          end.setDate(end.getDate() - 1);
          // String of the end date.
          strEnd = FullCalendar.formatDate(end, formatSettings);
        }
        // define the start date string in 'YYYY-MM-DD' format.
        if (start) {
          strStart = FullCalendar.formatDate(start, formatSettings);
        }
        const title = info.event.title.replace(/(<([^>]+)>)/ig,"");;
        const msg = Drupal.t('@title end is now @event_end. Do you want to save this change?', {
          '@title': title,
          '@event_end': strEnd
        });

        if (!confirm(msg)) {
          info.revert();
        }
        else {
          /**
           * Perform ajax call for event update in database.
           */
          jQuery
            .post(
              drupalSettings.path.baseUrl +
                "fullcalendar-view-event-update",
              {
                eid: info.event.id,
                entity_type: drupalSettings.entityType,
                start: strStart,
                end: strEnd,
                start_field: drupalSettings.startField,
                end_field: drupalSettings.endField,
                token: drupalSettings.token
              }
            )
            .done(function(data) {
              // alert("Response: " + data);
            });
        }
      }
    }
  };
})(jQuery, Drupal);
