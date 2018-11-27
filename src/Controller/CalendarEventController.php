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
      $eid = $request->request->get('eid', '');
      $entity_type = $request->request->get('entity_type', '');
      $start_date = $request->request->get('start', '');
      $end_date = $request->request->get('end', '');
      $start_field = $request->request->get('start_field', '');
      $end_field = $request->request->get('end_field', '');

      if (!empty($eid) && !empty($start_date) && !empty($start_field) && !empty($entity_type)) {
        $entity = $this->entityTypeManager()->getStorage($entity_type)->load($eid);

        if (!empty($entity) && $entity->access('update')) {
          if ($entity->hasField($start_field)) {
            // Field definitions.
            $fields_def = $entity->getFieldDefinition($start_field);
            $start_type = $fields_def->getType();
            if (isset($entity->$end_field) && !empty($end_date)) {
              $fields_def = $entity->getFieldDefinition($end_field);
              $end_type = $fields_def->getType();
            }

            // Multiple value of start field.
            if (is_array($entity->$start_field)) {
              if ($start_type === 'datetime' || $start_type === 'daterange') {
                $length = strlen($entity->$start_field[0]);

                if ($length > 10) {
                  // Only update the first value.
                  $entity->$start_field[0] = [
                    'value' => gmdate("Y-m-d\TH:i:s", strtotime($start_date)),
                  ];
                }
                else {
                  $entity->$start_field[0] = ['value' => $start_date];
                }
              }
            }
            // Single value field.
            else {
              // Datetime field.
              if (is_numeric($entity->$start_field->value)) {
                $entity->$start_field->value = strtotime($start_date);
              }
              else {    
                $length = strlen($entity->$start_field->value);
                
                if ($length > 10) {
                  // UTC Date with time.
                  $entity->$start_field->value = gmdate("Y-m-d\TH:i:s", strtotime($start_date));
                }
                else {
                  $entity->$start_field->value = $start_date;
                }
              }
            }

            // End date.
            if (isset($end_type)) {
              // Multiple value of end field.
              if (is_array($entity->$end_field)) {
                if ($end_type === 'datetime') {
                  $length = strlen($entity->$end_field[0]);

                  if ($length > 10) {
                    // Only update the first value.
                    $entity->$end_field[0] = [
                      'value' => gmdate("Y-m-d\TH:i:s", strtotime($end_date)),
                    ];
                  }
                  else {
                    $entity->$end_field[0] = ['value' => $end_date];
                  }
                }
                // Daterange field.
                elseif ($end_type === 'daterange') {
                  $length = strlen($entity->$end_field[0]->end_value);

                  if ($length > 10) {
                    // UTC Date with time.
                    $entity->$end_field[0]->end_value = gmdate("Y-m-d\TH:i:s", strtotime($end_date));
                  }
                  else {
                    $entity->$end_field[0]->end_value = $end_date;
                  }
                }
                // Timestamp field.
                elseif (is_numeric($entity->$end_field[0]->value)) {
                  $entity->$end_field[0]->value = strtotime($end_date);
                }
              }
              // Single value field.
              else {
                // Datetime field.
                if ($end_type === 'datetime') {
                  $length = strlen($entity->$end_field->value);

                  if ($length > 10) {
                    // UTC Date with time.
                    $entity->$end_field->value = gmdate("Y-m-d\TH:i:s", strtotime($end_date));
                  }
                  else {
                    $entity->$end_field->value = $end_date;
                  }
                }
                // Daterange field.
                elseif ($end_type === 'daterange') {
                  $length = strlen($entity->$end_field->end_value);

                  if ($length > 10) {
                    // UTC Date with time.
                    $entity->$end_field->end_value = gmdate("Y-m-d\TH:i:s", strtotime($end_date));
                  }
                  else {
                    $entity->$end_field->end_value = $end_date;
                  }
                }
                // Timestamp field.
                elseif ($end_type === 'timestamp') {
                  $entity->$end_field->value = strtotime($end_date);
                }
              }
            }

            $entity->save();
            // Log the content changed.
            $this->loggerFactory->get('content')->notice($entity->getType() . ': updated ' . $entity->getTitle());
            return new Response($entity->getTitle() . ' is updated to from ' . $start_date . ' to ' . $end_date);
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
   *   A event entity form render array
   */
  public function addEvent(Request $request) {
    $entity_id = $request->get('entity', '');
    $type = $request->get('bundle', '');
    $start_field = $request->get('start_field', '');
    $end_field = $request->get('end_field', '');
    $form = [];

    if (!empty($type)) {
      $user = $this->currentUser();
      // Check the user permission.
      if (!empty($user) && $user->hasPermission("create $type " . $entity_id)) {
        $data = [
          'type' => $type,
        ];
        // Create a new event entity for this form.
        $entity = $this->entityTypeManager()
        ->getStorage($entity_id)
          ->create($data);

        if (!empty($entity)) {
          // Add form.
          $form = $this->entityFormBuilder()->getForm($entity);
          // Field definitions of this entity.
          $field_def = $entity->getFieldDefinitions();
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
