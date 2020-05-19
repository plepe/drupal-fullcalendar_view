/**
 * @file
 * Fullcalendar View plugin JavaScript file.
 */

(function($, Drupal) {
  var initialLocaleCode = 'en';
  // Dialog index.
  var dialogIndex = 0;
  // Dialog objects.
  var dialogs = [];
  // Date entry clicked.
  var slotDate;

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
        hour: '2-digit',
        minute: '2-digit',
        second: '2-digit',
        locale: 'sv-SE'
      };
    // define the end date string in 'YYYY-MM-DD' format.
    if (end) {
      // The end date of an all-day event is exclusive.
      // For example, the end of 2018-09-03
      // will appear to 2018-09-02 in the calendar.
      // So we need one day subtract
      // to ensure the day stored in Drupal
      // is the same as when it appears in
      // the calendar.
      if (end.getHours() == 0 && end.getMinutes() == 0 && end.getSeconds() == 0) {
        end.setDate(end.getDate() - 1);
      }
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

    if (
        drupalSettings.updateConfirm === 1 &&
        !confirm(msg)
    ) {
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
          if (data !== '1') {
            alert("Error: " + data);
            info.revert();
          }
        });
    }
  }
  
  // Day entry click call back function.
  function dayClickCallback(info) {
    slotDate = info.dateStr;
  }
  
  // Event click call back function.
  function eventClick(info) {
    slotDate = null;
    info.jsEvent.preventDefault();
    let thisEvent = info.event;
    // Show the event detail in a pop up dialog.
    if (drupalSettings.dialogWindow) {
      let dataDialogOptionsDetails = {};
      if (thisEvent.url == '') {
        return false;
      }
      
      const jsFrame = new JSFrame({
        parentElement:document.body,//Set the parent element to which the jsFrame is attached here
      });
      // Position offset.
      let posOffset = dialogIndex * 20;
      // Dialog options.
      let dialogOptions = JSON.parse(drupalSettings.dialog_options);
      dialogOptions.left += posOffset;
      dialogOptions.top += posOffset;
      dialogOptions.title = thisEvent.title.replace(/(<([^>]+)>)/ig,"");
      dialogOptions.url = thisEvent.url;
      //Create window
      dialogs[dialogIndex] = jsFrame.create(dialogOptions);
      
      dialogs[dialogIndex].show();
      dialogIndex++;

      return false;
    }
    // Open a new window to show the details of the event.
    if (thisEvent.url) {
      if (drupalSettings.openEntityInNewTab) {
        // Open a new window to show the details of the event.
       window.open(thisEvent.url);
       return false;
      }
      else {
        // Open in same window
        window.location.href = thisEvent.url;
        return false;
      }
    }

    return false;
  }
  
  // Event drop call back function.
  function eventDrop(info) {
    const end = info.event.end;
    const start = info.event.start;
    let strEnd = '';
    let strStart = '';
    const formatSettings = {
        month: '2-digit',
        year: 'numeric',
        day: '2-digit',
        hour: '2-digit',
        minute: '2-digit',
        second: '2-digit',
        locale: 'sv-SE'
      };
    // define the end date string in 'YYYY-MM-DD' format.
    if (end) {
      // The end date of an all-day event is exclusive.
      // For example, the end of 2018-09-03
      // will appear to 2018-09-02 in the calendar.
      // So we need one day subtract
      // to ensure the day stored in Drupal
      // is the same as when it appears in
      // the calendar.
      if (end.getHours() == 0 && end.getMinutes() == 0 && end.getSeconds() == 0) {
        end.setDate(end.getDate() - 1);
      }
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

    if (
        drupalSettings.updateConfirm === 1 &&
        !confirm(msg)
    ) {
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
          if (data !== '1') {
            alert("Error: " + data);
            info.revert();
          }
        });

    }
  }
  
  // Todo: 
  // Set up the weith for the behavior.
  // Make sure it run after all others.
  // @see https://www.drupal.org/project/drupal/issues/2367655
  Drupal.behaviors.fullcalendarView = {
    attach: function(context, settings) {
      var calendarOptions = JSON.parse(drupalSettings.calendar_options);
      // Bind the render event handler.
      calendarOptions.eventRender = eventRender;
      // Bind the resize event handler.
      calendarOptions.eventResize = eventResize;
      // Bind the day click handler.
      calendarOptions.dateClick = dayClickCallback;
      // Bind the event click handler.
      calendarOptions.eventClick = eventClick;
      // Bind the drop event handler.
      calendarOptions.eventDrop = eventDrop;
      
      // If the BigPipe module is enabled,
      // We need to rebuild the Calendar during Drupal.behavior loops,
      // In case the DOM has changed.
      // @see https://www.drupal.org/project/fullcalendar_view/issues/3136764
      if (drupalSettings.calendar  && settings.bigPipePlaceholderIds) {
        // Rebuild the calendar.
        drupalSettings.calendar.destroy();
        drupalSettings.calendar.render();
      }
      else {
        $('.js-drupal-fullcalendar', context)
        .once("fullCalendarBehavior")
        .each(function(index) {
          // Language select element.
          var localeSelectorEl = document.getElementById('locale-selector');
              
          var calendarEl = this;
          // Initial the calendar.
          if (calendarEl) { 
            drupalSettings.calendar = new FullCalendar.Calendar(calendarEl, calendarOptions); 
            drupalSettings.calendar.render();
            // Language dropdown box.
            if (drupalSettings.languageSelector) {
              // build the locale selector's options
              drupalSettings.calendar.getAvailableLocaleCodes().forEach(function(localeCode) {
                var optionEl = document.createElement('option');
                optionEl.value = localeCode;
                optionEl.selected = localeCode == calendarOptions.locale;
                optionEl.innerText = localeCode;
                localeSelectorEl.appendChild(optionEl);
              });
              // when the selected option changes, dynamically change the calendar option
              localeSelectorEl.addEventListener('change', function() {
                if (this.value) {
                  drupalSettings.calendar.setOption('locale', this.value);
                }
              });
            }
            else {
              localeSelectorEl.style.display = "none";
            }
            
            // Double click event.
            calendarEl.addEventListener('dblclick' , function(e) {
              // New event window can be open if following conditions match.
              // * The new event content type are specified.
              // * Allow to create a new event by double click.
              // * User has the permission to create a new event.
              // * The add form for the new event type is known.
              if (
                  slotDate &&
                  drupalSettings.eventBundleType &&
                  drupalSettings.dblClickToCreate &&
                  drupalSettings.addForm !== ""
                ) {
                  // Open a new window to create a new event (content).
                  window.open(
                    drupalSettings.path.baseUrl +
                      drupalSettings.addForm +
                      "?start=" +
                      slotDate +
                      "&start_field=" +
                      drupalSettings.startField,
                    "_blank"
                  );
                }

            });
          }
        });
      }
    }
  };
})(jQuery, Drupal);
