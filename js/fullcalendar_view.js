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
          // Date entry clicked.
          let slotDate;
          // Day entry click call back function.
          function dayClickCallback(date) {
            slotDate = date;
          }
          $(".js-drupal-fullcalendar").fullCalendar({
            header: {
              left: drupalSettings.leftButtons,
              center: "title",
              right: drupalSettings.rightButtons
            },
            titleFormat: drupalSettings.titleFormat,
            defaultDate: drupalSettings.defaultDate,
            firstDay: drupalSettings.firstDay,
            defaultView: drupalSettings.defaultView,
            locale: drupalSettings.defaultLang,
            timeFormat: drupalSettings.timeFormat,
            // Can click day/week names to navigate views.
            navLinks: drupalSettings.navLinks !== 0,
            columnHeaderFormat: drupalSettings.columnHeaderFormat,
            editable: drupalSettings.updateAllowed !== 0,
            eventLimit: true, // Allow "more" link when too many events.
            events: drupalSettings.fullCalendarView,
            eventOverlap: drupalSettings.allowEventOverlap !== 0,
            dayClick: dayClickCallback,
            eventRender: function(event, $el) {
              // Event title with HTML markup.
              $el.find(".fc-title, .fc-list-item-title").html(event.title);
              // Popup tooltip.
              if (event.description) {
                if ($el.fullCalendarTooltip !== "undefined") {
                  $el.fullCalendarTooltip(event.title, event.description);
                }
              }
              // Recurring event.
              if (event.ranges) {
                return (
                  event.ranges.filter(function(range) {
                    // Exclude dates from range if exists.
                    if (range.excluding_dates) {
                      for (let i = 0; i < range.excluding_dates.length; i++) {
                        if (event.start.isSame(moment.utc(range.excluding_dates[i], "YYYY-MM-DD"), 'day')) {
                          return false;
                        }
                      }
                    }

                    if (event.dom) {
                      let isTheDay = false;
                      const dom = event.dom;
                      for (let i = 0; i < dom.length; i++) {
                        if (dom[i] === event.start.format("D")) {
                          isTheDay = true;
                          break;
                        }
                      }
                      if (!isTheDay) {
                        return false;
                      }
                    }
                    // Test event against all the ranges.
                    if (range.end) {
                      return (
                        event.start.isBefore(
                          moment.utc(range.end, "YYYY-MM-DD")
                        ) &&
                        event.end.isAfter(moment.utc(range.start, "YYYY-MM-DD"))
                      );
                    }
                    return event.start.isAfter(
                      moment.utc(range.start, "YYYY-MM-DD")
                    );
                  }).length > 0
                ); // If it isn't in one of the ranges, don't render it (by returning false)
              }
            },
            eventResize: function(event, delta, revertFunc) {
              // The end day of an event is exclusive.
              // For example, the end of 2018-09-03
              // will appear to 2018-09-02 in the calendar.
              // So we need one day subtract
              // to ensure the day stored in Drupal
              // is the same as when it appears in
              // the calendar.
              if (event.end && event.end.format("HH:mm:ss") === "00:00:00") {
                event.end.subtract(1, "days");
              }
              // Event title.
              const title = $($.parseHTML(event.title)).text();
              if (
                drupalSettings.updateConfirm === 1 &&
                !confirm(
                  title +
                    " end is now " +
                    event.end.format() +
                    ". Do you want to save the change?"
                )
              ) {
                revertFunc();
              } else {
                /**
                 * Perform ajax call for event update in database.
                 */
                jQuery
                  .post(
                    drupalSettings.path.baseUrl +
                      "fullcalendar-view-event-update",
                    {
                      eid: event.id,
                      entity_type: drupalSettings.entityType,
                      start: event.start.format(),
                      end: event.end ? event.end.format() : "",
                      start_field: drupalSettings.startField,
                      end_field: drupalSettings.endField,
                      token: drupalSettings.token
                    }
                  )
                  .done(function(data) {
                    // alert("Response: " + data);
                  });
              }
            },
            eventDrop: function(event, delta, revertFunc) {
              // Event title.
              const title = $($.parseHTML(event.title)).text();
              const msg =
                title +
                " was updated to " +
                event.start.format() +
                ". Are you sure about this change?";
              // The end day of an event is exclusive.
              // For example, the end of 2018-09-03
              // will appear to 2018-09-02 in the calendar.
              // So we need one day subtract
              // to ensure the day stored in Drupal
              // is the same as when it appears in
              // the calendar.
              if (event.end && event.end.format("HH:mm:ss") === "00:00:00") {
                event.end.subtract(1, "days");
              }
              if (drupalSettings.updateConfirm === 1 && !confirm(msg)) {
                revertFunc();
              } else {
                /**
                 * Perform ajax call for event update in database.
                 */
                jQuery
                  .post(
                    drupalSettings.path.baseUrl +
                      "fullcalendar-view-event-update",
                    {
                      eid: event.id,
                      entity_type: drupalSettings.entityType,
                      start: event.start.format(),
                      end: event.end ? event.end.format() : "",
                      start_field: drupalSettings.startField,
                      end_field: drupalSettings.endField,
                      token: drupalSettings.token
                    }
                  )
                  .done(function(data) {
                    // alert("Response: " + data);
                  });
              }
            },
            eventClick: function(calEvent, jsEvent, view) {
              slotDate = null;
              if (drupalSettings.linkToEntity) {
                // Open a time slot details in a dialog
                if (drupalSettings.dialogWindow) {
                  let dataDialogOptionsDetails = {};
                  var modalLink = $('<a id="fullcalendar-view-dialog"></a>');
                  dataDialogOptionsDetails.draggable = true;
                  dataDialogOptionsDetails.autoResize = false;
                  dataDialogOptionsDetails.title = calEvent.title.replace(/(<([^>]+)>)/ig,"");

                  modalLink.addClass('use-ajax');
                  modalLink.attr('href', calEvent.url);
                  modalLink.attr('data-dialog-type', 'dialog');
                  modalLink.attr('data-dialog-options', JSON.stringify(dataDialogOptionsDetails));
                  modalLink.appendTo($('body'));

                  Drupal.attachBehaviors();
                  modalLink.trigger('click').remove();
                  // The entry element object.
                  let $thisEntry = $(this);
                  if (typeof $thisEntry.qtip === "function") {
                    // Hide the pop tip.
                    $thisEntry.qtip("hide");
                  }

                  return false;
                }
                // Open a new window to show the details of the event.
                if (calEvent.url) {
                  if (drupalSettings.openEntityInNewTab) {
                    // Open a new window to show the details of the event.
                   window.open(calEvent.url);
                   return false;
                  }
                  else {
                    // Open in same window
                    return true;
                  }
                }
              }

              return false;
            }
          });

          if (drupalSettings.languageSelector) {
            // Build the locale selector's options.
            $.each($.fullCalendar.locales, function(localeCode) {
              $("#locale-selector").append(
                $("<option/>")
                  .attr("value", localeCode)
                  .prop("selected", localeCode === drupalSettings.defaultLang)
                  .text(localeCode)
              );
            });
            // When the selected option changes, dynamically change the calendar option.
            $("#locale-selector").on("change", function() {
              if (this.value) {
                $(".js-drupal-fullcalendar").fullCalendar("option", "locale", this.value);
              }
            });
          } else {
            $(".locale-selector").hide();
          }

          $(".js-drupal-fullcalendar").dblclick(function() {
            if (
              slotDate &&
              drupalSettings.eventBundleType &&
              drupalSettings.dblClickToCreate &&
              drupalSettings.updateAllowed &&
              drupalSettings.addForm !== ""
            ) {
              const date = slotDate.format();
              // Open a new window to create a new event (content).
              window.open(
                drupalSettings.path.baseUrl +
                  drupalSettings.addForm +
                  "?start=" +
                  date +
                  "&start_field=" +
                  drupalSettings.startField,
                "_blank"
              );
            }
          });
        });
    }
  };
})(jQuery, Drupal);
