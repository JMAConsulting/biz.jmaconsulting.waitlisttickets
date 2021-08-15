<?php
use CRM_Waitlisttickets_ExtensionUtil as E;

/**
 * This job performs various housekeeping actions related to the Stripe payment processor
 *
 * @param array $params
 *
 * @return array
 *   API result array.
 * @throws CiviCRM_API3_Exception
 */
function civicrm_api3_job_process_participant_by_count($params) {
  $result = E::process($params);
  if (!$result['is_error']) {
    return civicrm_api3_create_success(implode("\r\r", $result['messages']));
  }
  else {
    return civicrm_api3_create_error('Error while processing participant statuses');
  }
}


/**
 * Action Payment.
 *
 * @param array $params
 */
function _civicrm_api3_job_process_participant_by_count_spec(&$params) {
  $params['event_id']['title'] = 'Event ID';
}
