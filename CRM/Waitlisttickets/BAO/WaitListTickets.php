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

  public static function addWaitlist($params) {
    $waitlist = new CRM_Waitlisttickets_DAO_WaitListTickets();
    $waitlist->copyValues($params);
    $waitlist->save();
  }

  public static function getWaitlistTickets($pid) {
    $waitlist = new CRM_Waitlisttickets_DAO_WaitListTickets();
    $waitlist->participant_id = $pid;
    $waitlist->find();
    $priceDetails = "";
    $totalCount = 0;
    // We construct details of the number of selected tickets to display as Fee level.
    while ($waitlist->fetch()) {
      $pricelabel = CRM_Core_DAO::singleValueQuery("SELECT label
        FROM civicrm_price_field
        WHERE id = %1", [1 => [$waitlist->price_field_id, 'Integer']]);
      $priceDetails .= "<span style='color:#ff5854'>" . $pricelabel . " - " . $waitlist->participant_count;
      $priceDetails .= "<br/>";
      $totalCount = $totalCount + $waitlist->participant_count;
    }
    if (!empty($priceDetails)) {
      $priceDetails .= "Participant Count - " . $totalCount . "</span>";
    }
    return $priceDetails;
  }

  public static function deleteWaitlist($pid) {
    $waitlist = new CRM_Waitlisttickets_DAO_WaitListTickets();
    $waitlist->participant_id = $pid;
    $waitlist->find(TRUE);
    $waitlist->delete();
  }

}
