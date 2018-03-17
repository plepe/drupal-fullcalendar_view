<?php

namespace Drupal\fullcalendar_view\Controller;

use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\DependencyInjection\ContainerInterface;
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
              if ($start_type === 'datetime' || $start_type === 'daterange') {
                $length = strlen($node->$start_field[0]);

                if ($length > 10) {
                  // Only update the first value.
                  $node->$start_field[0] = [
                    'value' => gmdate("Y-m-d\TH:i:s", strtotime($start_date)),
                  ];
                }
                else {
                  $node->$start_field[0] = ['value' => $start_date];
                }
              }
            }
            // Single value field.
            else {
              // Datetime field.
              if ($start_type === 'datetime' || $start_type === 'daterange') {
                $length = strlen($node->$start_field->value);

                if ($length > 10) {
                  // UTC Date with time.
                  $node->$start_field->value = gmdate("Y-m-d\TH:i:s", strtotime($start_date));
                }
                else {
                  $node->$start_field->value = $start_date;
                }
              }
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
                      'value' => gmdate("Y-m-d\TH:i:s", strtotime($end_date)),
                    ];
                  }
                  else {
                    $node->$end_field[0] = ['value' => $end_date];
                  }
                }
                // Daterange field.
                elseif ($end_type === 'daterange') {
                  $length = strlen($node->$end_field[0]->end_value);

                  if ($length > 10) {
                    // UTC Date with time.
                    $node->$end_field[0]->end_value = gmdate("Y-m-d\TH:i:s", strtotime($end_date));
                  }
                  else {
                    $node->$end_field[0]->end_value = $end_date;
                  }
                }
                // Timestamp field.
                elseif ($end_type === 'timestamp') {
                  $node->$end_field[0]->value = strtotime($end_date);
                }
              }
              // Single value field.
              else {
                // Datetime field.
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
                // Daterange field.
                elseif ($end_type === 'daterange') {
                  $length = strlen($node->$end_field->end_value);

                  if ($length > 10) {
                    // UTC Date with time.
                    $node->$end_field->end_value = gmdate("Y-m-d\TH:i:s", strtotime($end_date));
                  }
                  else {
                    $node->$end_field->end_value = $end_date;
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

  /**
   * New event handler function.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   Http Request object.
   *
   * @return array
   *   A event node form render array
   */
  public function addEvent(Request $request) {
    $type = $request->get('type', '');
    $start_field = $request->get('start_field', '');
    $end_field = $request->get('end_field', '');
    $form = [];

    if (!empty($type)) {
      $user = $this->currentUser();
      // Check the user permission.
      if (!empty($user) && $user->hasPermission("create $type content")) {
        $data = [
          'type' => $type,
        ];
        // Create a new event node for this form.
        $node = $this->entityTypeManager()
          ->getStorage('node')
          ->create($data);

        if (!empty($node)) {
          // Node form.
          $form = $this->entityFormBuilder()->getForm($node);
          // Field definitions of this node.
          $field_def = $node->getFieldDefinitions();
          // Hide those fields we don't need for this form.
          foreach ($form as $name => &$element) {
            switch ($name) {
              case 'advanced';
              case 'body';
                $element['#access'] = FALSE;
            }
            // Hide all fields that are irrelevant to the event date.
            if (substr($name, 0, 6) === 'field_' && $name !== $start_field && $name !== $end_field && $name !== 'field_monthly_event' && $name !== 'field_weekly_event' && !$field_def[$name]->isRequired()) {
              $element['#access'] = FALSE;
            }
          }
          // Hide preview button.
          if (isset($form['actions']['preview'])) {
            $form['actions']['preview']['#access'] = FALSE;
          }
          // Move the Save button to the bottom of this form.
          $form['actions']['#weight'] = 10000;

          return $form;
        }
      }
    }
    // Return access denied for users don't have the permission.
    throw new AccessDeniedHttpException();
  }

}
