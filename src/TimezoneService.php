<?php

namespace Drupal\fullcalendar_view;

class TimezoneService {
  public function __construct() {
  }
  
  /**
   * Return the value of the converted date from UTC date.
   */
  public function utcToLocal($utc_date, $local_timezone, $date_only = FALSE) {
    // UTC timezone.
    $UTC = new \DateTimeZone("UTC");
    // Local time zone.
    $localTZ = new \DateTimeZone($local_timezone);
    // Date object in UTC timezone
    $date = new \DateTime( $utc_date, $UTC );
    $date->setTimezone( $localTZ );
    
    return $date_only ? $date->format('Y-m-d') : $date->format(DATE_ATOM);
  }
  
}