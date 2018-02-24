(function ($, Drupal) {
	Drupal.behaviors.fullcalendarView = {
	  attach: function (context, settings) {
		  console.log(drupalSettings.fullCalendarView);
		  $('#calendar').fullCalendar({
		      header: {
		        left: 'prev,next today',
		        center: 'title',
		        right: 'month,agendaWeek,agendaDay,listMonth'
		      },
		      defaultDate: drupalSettings.defaultDate,
		      navLinks: true, // Can click day/week names to navigate views.
		      editable: true,
		      eventLimit: true, // Allow "more" link when too many events.
		      events: drupalSettings.fullCalendarView,
		      dayClick: dayClickCallback,
		      eventRender: function(event){
		    	  if (event.ranges) {
		    		  return (event.ranges.filter(function(range){ 
		    			  if (event.dom) {
		    				  var isTheDay = false;
		    				  var length = event.dom.length;
		    				  for (var i = 0; i < length; i++) {
		    					  if (event.dom[i] == event.start.format('D')) {
		    						  isTheDay = true;
		    						  break;
		    					  }  
		    				  }
		    				  if (!isTheDay) {
		    					  return false;
		    				  }
		    			  }
		    			  // test event against all the ranges
		    			  if (range.end) {
		    				  return (event.start.isBefore(moment.utc(range.end,'YYYY-MM-DD')) &&
				    	                event.end.isAfter(moment.utc(range.start,'YYYY-MM-DD')));
		    			  }
		    			  else {
		    				  return event.start.isAfter(moment.utc(range.start,'YYYY-MM-DD'));
		    			  }
			    	    }).length)>0; //if it isn't in one of the ranges, don't render it (by returning false)
		    	  }
		      },
		      eventResize: function(event, delta, revertFunc) {  
		    	  // As designed, the end date is inclusive for all day event,
		          // which is not what we want. So we need one day subtract.
		    	  if (event.allDay) {
		    		  event.end.subtract(1, 'days');
		    	  }
		          if (!confirm(event.title + " end is now " + event.end.format() + ". Do you want to save the change?")) {
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
		      eventDrop: function(event, delta, revertFunc) {
		    	  var msg = event.title + " was updated to " + event.start.format() + ". Are you sure about this change?";
      	    	  // As designed, the end date is inclusive for all day event,
		          // which is not what we want. So we need one day subtract.
		    	  if (event.allDay && event.end) {
		    		  event.end.subtract(1, 'days');
		    	  }
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