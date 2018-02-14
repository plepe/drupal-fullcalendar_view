(function ($, Drupal) {
	Drupal.behaviors.fullcalendarView = {
	  attach: function (context, settings) {
		  //console.log(drupalSettings.fullCalendarView);
		  $('#calendar').fullCalendar({
		      header: {
		        left: 'prev,next today',
		        center: 'title',
		        right: 'month,basicWeek,basicDay,listMonth'
		      },
		      defaultDate: drupalSettings.defaultDate,
		      navLinks: true, // Can click day/week names to navigate views.
		      editable: true,
		      eventLimit: true, // Allow "more" link when too many events.
		      events: drupalSettings.fullCalendarView,
		      dayClick: dayClickCallback,
		      eventDrop: function(event, delta, revertFunc) {
		    	  var msg = event.title + " was updated to " + event.start.format() + ". Are you sure about this change?";
		          if (!confirm(msg)) {
		              revertFunc();
		          }
		          else {
		        	  /**
		               * perform ajax call for event update in database
		               */            
		              jQuery.post(
		                  '/fullcalendar-view-event-update'
		                  , { 
		                      nid: event.id
		                      , start: event.start.format()
		                      , end: (event.end) ? event.end.format() : '' 
		                      , start_field: drupalSettings.startField
		                      , end_field: drupalSettings.endField
		                  }
		              ).done(function( data ) {
		            	  alert("Response: " + data);
		              });  
		          }

		      },
		      eventClick: function(calEvent, jsEvent, view) {
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
		  
		    var slotDate;

		    function dayClickCallback(date){
		        slotDate = date;
		    }
		        
		    $("#calendar").dblclick(function() {
		        if(slotDate && drupalSettings.eventContentType){
		        	var date = slotDate.format();
		            // Open a new window to create a new event (content).
		        	window.open('/node/add/' + drupalSettings.eventContentType + '?start=' + date + '&start_field=' + drupalSettings.startField, '_blank');
		        }
		    });
	  }
	};
})(jQuery, Drupal);