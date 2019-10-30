<?php
use CRM_Waitlisttickets_ExtensionUtil as E;

class CRM_Waitlisttickets_BAO_WaitListTickets extends CRM_Waitlisttickets_DAO_WaitListTickets {

  /**
   * Create a new WaitListTickets based on array-data
   *
   * @param array $params key-value pairs
   * @return CRM_Waitlisttickets_DAO_WaitListTickets|NULL
   *
  public static function create($params) {
    $className = 'CRM_Waitlisttickets_DAO_WaitListTickets';
    $entityName = 'WaitListTickets';
    $hook = empty($params['id']) ? 'create' : 'edit';

    CRM_Utils_Hook::pre($hook, $entityName, CRM_Utils_Array::value('id', $params), $params);
    $instance = new $className();
    $instance->copyValues($params);
    $instance->save();
    CRM_Utils_Hook::post($hook, $entityName, $instance->id, $instance);

    return $instance;
  } */

}
