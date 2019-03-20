/**
 * @file
 * Fullcalendar View plugin JavaScript file.
 */

(function($, Drupal) {
  Drupal.behaviors.fullcalendarView = {
    attach(context, settings) {
      $("body", context)
        .once("absCustomBehavior")
        .each(function() {
          // Date entry clicked.
          let slotDate;
          // Day entry click call back function.
          function dayClickCallback(date) {
            slotDate = date;
          }
          $("#calendar").fullCalendar({
            header: {
              left: "prev,next today",
              center: "title",
              right: drupalSettings.rightButtons
            },
            defaultDate: drupalSettings.defaultDate,
            locale: drupalSettings.defaultLang,
            // Can click day/week names to navigate views.
            navLinks: drupalSettings.navLinks !== 0,
            editable: true,
            eventLimit: true, // Allow "more" link when too many events.
            events: drupalSettings.fullCalendarView,
            eventOverlap: drupalSettings.alloweventOverlap !== 0,
            dayClick: dayClickCallback,
            eventRender(event, $el) {
              // Event title with HTML markup.
              $el.find("span.fc-title").html($el.find("span.fc-title").text());
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
                    if (event.dom) {
                      let isTheDay = false;
                      const [dom] = event.dom;
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
            eventResize(event, delta, revertFunc) {
              // As designed, the end date is inclusive for all day event,
              // which is not what we want. So we need one day subtract.
              if (event.allDay) {
                event.end.subtract(1, "days");
              }
              // Event title.
              const title = $($.parseHTML(event.title)).text();
              if (
                drupalSettings.updateConfirm === 1 &&
                !confirm(
                  `${title} end is now ${event.end.format()}. Do you want to save the change?`
                )
              ) {
                revertFunc();
              } else {
                /**
                 * Perform ajax call for event update in database.
                 */
                jQuery
                  .post(
                    `${
                      drupalSettings.path.baseUrl
                    }fullcalendar-view-event-update`,
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
            eventDrop(event, delta, revertFunc) {
              // Event title.
              const title = $($.parseHTML(event.title)).text();
              const msg = `${title} was updated to ${event.start.format()}. Are you sure about this change?`;
              // As designed, the end date is inclusive for all day event,
              // which is not what we want. So we need one day subtract.
              if (event.allDay && event.end) {
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
                    `${
                      drupalSettings.path.baseUrl
                    }fullcalendar-view-event-update`,
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
            eventClick(calEvent, jsEvent, view) {
              slotDate = null;
              if (drupalSettings.linkToEntity) {
                // Open a new window to show the details of the event.
                if (calEvent.url) {
                  window.open(calEvent.url);
                  return false;
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
                $("#calendar").fullCalendar("option", "locale", this.value);
              }
            });
          } else {
            $(".locale-selector").hide();
          }

          $("#calendar").dblclick(function() {
            if (
              slotDate &&
              drupalSettings.eventBundleType &&
              drupalSettings.dblClickToCreate &&
              drupalSettings.addForm !== ""
            ) {
              const date = slotDate.format();
              // Open a new window to create a new event (content).
              window.open(
                `${drupalSettings.path.baseUrl +
                  drupalSettings.addForm}?start=${date}&start_field=${
                  drupalSettings.startField
                }`,
                "_blank"
              );
            }
          });
        });
    }
  };
})(jQuery, Drupal);
