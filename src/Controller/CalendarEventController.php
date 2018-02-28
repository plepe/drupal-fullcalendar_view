<?php

namespace Drupal\fullcalendar_view\Controller;

use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Entity\EntityTypeManager;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;

/**
 * Calendar Event Controller.
 */
class CalendarEventController extends ControllerBase {

  /**
   * Construct the Controller.
   *
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $loggerFactory
   *   Logger factory object.
   */
  public function __construct(LoggerChannelFactoryInterface $loggerFactory) {
    $this->loggerFactory = $loggerFactory;
  }

  /**
   * Create a CalendarEventController instance.
   *
   * @param \Symfony\Component\DependencyInjection\ContainerInterface $container
   *   Container object.
   *
   * @return \Drupal\fullcalendar_view\Controller\CalendarEventController
   *   The instance of CalendarEventController.
   */
  public static function create(ContainerInterface $container) {
    $loggerFactory = $container->get('logger.factory');
    return new static($loggerFactory);
  }

  /**
   * Event Update Handler.
   */
  public function updateEvent(Request $request) {
    $user = $this->currentUser();
    if (!empty($user)) {
      $nid = $request->request->get('nid', '');
      $start_date = $request->request->get('start', '');
      $end_date = $request->request->get('end', '');
      $start_field = $request->request->get('start_field', '');
      $end_field = $request->request->get('end_field', '');

      if (!empty($nid) && !empty($start_date) && !empty($start_field)) {
        $node = $this->entityTypeManager()->getStorage('node')->load($nid);

        if (!empty($node) && $node->access('update')) {
          if (isset($node->$start_field)) {
            // Field definitions.
            $fields_def = $node->getFieldDefinition($start_field);
            $start_type = $fields_def->getType();
            if (isset($node->$end_field) && !empty($end_date)) {
              $fields_def = $node->getFieldDefinition($end_field);
              $end_type = $fields_def->getType();
            }

            // Multiple value of start field.
            if (is_array($node->$start_field)) {
              if ($start_type === 'datetime') {
                $length = strlen($node->$start_field[0]);

                if ($length > 10) {
                  // Only update the first value.
                  $node->$start_field[0] = [
                    'value' => substr(gmdate(DATE_ATOM, strtotime($start_date)), 0, $length),
                  ];
                }
                else {
                  $node->$start_field[0] = ['value' => $start_date];
                }
              }
            }
            // Single value field.
            else {
              // Dateime field.
              if ($start_type === 'datetime') {
                $length = strlen($node->$start_field->value);

                if ($length > 10) {
                  // UTC Date with time.
                  $node->$start_field->value = gmdate("Y-m-d\TH:i:s", strtotime($start_date));
                }
                else {
                  $node->$start_field->value = $start_date;
                }
              }
              // Timestamp field.
              elseif ($start_type === 'timestamp') {
                $node->$start_field->value = strtotime($start_date);
              }
            }

            // End date.
            if (isset($end_type)) {
              // Multiple value of end field.
              if (is_array($node->$end_field)) {
                if ($end_type === 'datetime') {
                  $length = strlen($node->$end_field[0]);

                  if ($length > 10) {
                    // Only update the first value.
                    $node->$end_field[0] = [
                      'value' => substr(gmdate(DATE_ATOM, strtotime($end_date)), 0, $length),
                    ];
                  }
                  else {
                    $node->$end_field[0] = ['value' => $end_date];
                  }
                }
              }
              // Single value field.
              else {
                // Dateime field.
                if ($end_type === 'datetime') {
                  $length = strlen($node->$end_field->value);

                  if ($length > 10) {
                    // UTC Date with time.
                    $node->$end_field->value = gmdate("Y-m-d\TH:i:s", strtotime($end_date));
                  }
                  else {
                    $node->$end_field->value = $end_date;
                  }
                }
                // Timestamp field.
                elseif ($end_type === 'timestamp') {
                  $node->$end_field->value = strtotime($end_date);
                }
              }
            }

            $node->save();
            // Log the content changed.
            $this->loggerFactory->get('content')->notice($node->getType() . ': updated ' . $node->getTitle());
            return new Response($node->getTitle() . ' is updated to from ' . $start_date . ' to ' . $end_date);
          }

        }
        else {
          return new Response('Access denied!');
        }
      }
      else {
        return new Response('Parameter Missing.');
      }
    }
    else {
      return new Response('Invalid User!');
    }
  }

}
