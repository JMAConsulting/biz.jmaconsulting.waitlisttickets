<?php

  require_once 'waitlisttickets.civix.php';
  use CRM_Waitlisttickets_ExtensionUtil as E;

  /**
   * Implements hook_civicrm_config().
   *
   * @link https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_config/
   */
  function waitlisttickets_civicrm_config(&$config) {
    _waitlisttickets_civix_civicrm_config($config);
  }

  /**
   * Implements hook_civicrm_xmlMenu().
   *
   * @link https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_xmlMenu
   */

  /**
   * Implements hook_civicrm_install().
   *
   * @link https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_install
   */
  function waitlisttickets_civicrm_install() {
    _waitlisttickets_civix_civicrm_install();
  }

  /**
   * Implements hook_civicrm_postInstall().
   *
   * @link https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_postInstall
   */

  /**
   * Implements hook_civicrm_uninstall().
   *
   * @link https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_uninstall
   */

  /**
   * Implements hook_civicrm_enable().
   *
   * @link https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_enable
   */
  function waitlisttickets_civicrm_enable() {
    _waitlisttickets_civix_civicrm_enable();
  }

  /**
   * Implements hook_civicrm_disable().
   *
   * @link https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_disable
   */

  /**
   * Implements hook_civicrm_upgrade().
   *
   * @link https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_upgrade
   */

  /**
   * Implements hook_civicrm_managed().
   *
   * Generate a list of entities to create/deactivate/delete when this module
   * is installed, disabled, uninstalled.
   *
   * @link https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_managed
   */

  /**
   * Implements hook_civicrm_caseTypes().
   *
   * Generate a list of case-types.
   *
   * Note: This hook only runs in CiviCRM 4.4+.
   *
   * @link https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_caseTypes
   */

  /**
   * Implements hook_civicrm_angularModules().
   *
   * Generate a list of Angular modules.
   *
   * Note: This hook only runs in CiviCRM 4.5+. It may
   * use features only available in v4.6+.
   *
   * @link https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_angularModules
   */

  /**
   * Implements hook_civicrm_alterSettingsFolders().
   *
   * @link https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_alterSettingsFolders
   */

  /**
   * Implements hook_civicrm_entityTypes().
   *
   * Declare entity types provided by this module.
   *
   * @link https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_entityTypes
   */

  /**
   * Implements hook_civicrm_thems().
   */

  function waitlisttickets_civicrm_alterReportVar($type, &$vars, &$form) {
    if ('CRM_AOReports_Form_Report_ExtendedParticipantListing' == get_class($form)) {
      if ($type == 'rows') {
        foreach ($vars as &$var) {
          if (!empty($var['civicrm_participant_participant_record']) && empty($var['civicrm_participant_participant_fee_level'])) {
            $var['civicrm_participant_participant_fee_level'] = CRM_Waitlisttickets_BAO_WaitListTickets::getWaitlistTickets($var['civicrm_participant_participant_record']);
          }
        }
      }
    }
  }

  /**
   * Implementation of hook_civicrm_buildForm
   *
   * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_buildForm
   */
  function waitlisttickets_civicrm_buildForm($formName, &$form) {
    if ($formName == "CRM_Event_Form_Registration_Register" && $form->_allowWaitlist) {
      // We allow the user to also specify number of tickets while adding himself on the waitlist.
      // Get the price fields associated with the event.
      $priceFields = getPriceFieldInfo($form->_eventId);
      if (!empty($priceFields)) {
        foreach ($priceFields as $priceField) {
          // We check if the price field has a limit specified.
          $isAllowed = CRM_Core_DAO::singleValueQuery("SELECT max_value FROM civicrm_max_tickets WHERE price_field_id = %1 AND event_id = %2", [1 => [$priceField["id"], "Integer"], 2 => [$form->_eventId, "Integer"]]);
          if ($isAllowed === '0') {
            continue;
          }
          $key = "price_field_id_" . $priceField["id"];
          $selectOptions = [];
          if (in_array($priceField["html_type"], ["Select", "Radio", "CheckBox"])) {
            $fieldOptions = (array) civicrm_api3("PriceFieldValue", "get", [
              "price_field_id" => $priceField["id"],
              "return" => ["name", "label"],
            ])['values'];
            if (!empty($fieldOptions)) {
              foreach ($fieldOptions as $fieldOption) {
                $selectOptions[$fieldOption['name']] = $fieldOption['label'];
              }
              $selectOptions = $selectOptions + [0 => ts('None')];
            }
          }
          if ($priceField["html_type"] == "Radio") {
            $form->addRadio($key, ts($priceField['label']), $selectOptions);
          }
          else {
            $form->add($priceField["html_type"], $key, ts($priceField['label']), $selectOptions);
          }
          $ticketOptions[] = $key;
        }
        if (!empty($ticketOptions)) {
          $ticketLabel = ts("Number of tickets");
          if (\Drupal::languageManager()->getCurrentLanguage()->getId() == 'fr') {
            $ticketLabel = ts("Nombre de billets");
          }
          $form->assign('ticketLabel', $ticketLabel);
          $form->assign('ticketOptions', $ticketOptions);
          $form->_ticketOptions = $ticketOptions;
          CRM_Core_Region::instance('page-body')->add(array(
            'template' => 'CRM/WaitlistPriceFields.tpl',
          ));
        }
      }
    }
    if ($formName == "CRM_Event_Form_Registration_Register" && !empty($form->getVar('_participantId'))) {
      // This is a waitlisted registration.
      $defaults = CRM_Waitlisttickets_BAO_WaitListTickets::setWaitlistTickets($form->getVar('_participantId'));
      if (!empty($defaults)) {
        $form->setDefaults($defaults);
      }
    }
    if ($formName == "CRM_Event_Form_Participant" && !empty($form->get_template_vars('participantId'))) {
      $defaults = CRM_Waitlisttickets_BAO_WaitListTickets::setWaitlistTickets($form->get_template_vars('participantId'));
      if (!empty($defaults)) {
        $form->setDefaults($defaults);
      }
    }
  }

  function waitlisttickets_civicrm_validateForm($formName, &$fields, &$files, &$form, &$errors) {
    if ($formName == "CRM_Event_Form_Registration_Register" && $form->_allowWaitlist && !empty($form->_ticketOptions)) {
      // Check to see if atleast one ticketing option is selected.
      $flag = TRUE;
      foreach ($form->_ticketOptions as $option) {
        if (!empty($fields[$option])) {
          $flag = FALSE;
          break;
        }
      }
      if ($flag) {
        $errors['_qf_default'] = ts("Please select atleast one of the ticket options");
      }
    }
    if (($formName == "CRM_Event_Form_Registration_Register" && !empty($form->getVar('_participantId'))) ||
      ($formName == "CRM_Event_Form_Participant" && !empty($form->get_template_vars('participantId')))) {
      if ($formName === "CRM_Event_Form_Registration_Register") {
        $originalCount = CRM_Waitlisttickets_BAO_WaitListTickets::getWaitlistCount($form->getVar('_participantId'));
      }
      elseif ($formName === "CRM_Event_Form_Participant") {
        $originalCount = CRM_Waitlisttickets_BAO_WaitListTickets::getWaitlistCount($form->get_template_vars('participantId'));
      }
      foreach ($fields as $field => $value) {
        if (!empty($value) && substr($field, 0, strlen('price_')) === 'price_') {
          $selectedPriceFields[] = $value;
        }
      }
      if (!empty($selectedPriceFields)) {
        $participantCount = CRM_Core_DAO::singleValueQuery("SELECT SUM(count) FROM civicrm_price_field_value WHERE id IN (" . implode(',', $selectedPriceFields) . ")");
        if ($participantCount > $originalCount) {
          $errors['_qf_default'] = ts("You have selected more than the requested number of seats.");
        }
      }
    }
  }

  function waitlisttickets_civicrm_post($op, $objectName, $objectId, &$objectRef) {
    if ($op == "delete" && $objectName == "Participant") {
      CRM_Waitlisttickets_BAO_WaitListTickets::deleteWaitlist($objectId);
    }
  }

  /**
   * Implementation of hook_civicrm_postProcess
   *
   * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_postProcess
   */
  function waitlisttickets_civicrm_postProcess($formName, &$form) {
    if ($formName == "CRM_Event_Form_Registration_Confirm" && $form->_allowWaitlist) {
      $params = $form->getVar('_params');

      $priceFields = getPriceFieldInfo($form->_eventId);
      $priceParams = [];
      if (!empty($priceFields)) {
        foreach ($priceFields as $priceField) {
          if (!empty($params['price_field_id_' . $priceField['id']])) {
            if (in_array($priceField["html_type"], ["Select", "Radio", "CheckBox"])) {
              // We get the participant count of the price field value.
              $sql = CRM_Core_DAO::executeQuery("SELECT id, count FROM civicrm_price_field_value WHERE name = %1 AND price_field_id = %2",
                [1 => [$params["price_field_id_" . $priceField["id"]], "String"], 2 => [$priceField["id"], "Integer"]])->fetchAll();
              if (!empty($sql[0])) {
                $priceParams[] = [
                  'price_field_id' => $priceField['id'],
                  'price_field_value_id' => $sql[0]['id'],
                  'participant_count' => $sql[0]['count'],
                  'event_id' => $form->getVar('_eventId'),
                  'participant_id' => $form->getVar('_participantId'),
                ];
              }
            }
            else {
              $priceParams[] = [
                'price_field_id' => $priceField['id'],
                'price_field_value_id' => CRM_Core_DAO::singleValueQuery("SELECT id FROM civicrm_price_field_value WHERE price_field_id = %1", [1 => [$priceField['id'], 'Integer']]),
                'participant_count' => $params['price_field_id_' . $priceField['id']],
                'event_id' => $form->getVar('_eventId'),
                'participant_id' => $form->getVar('_participantId'),
              ];
            }
          }
        }
      }
      if (!empty($priceParams)) {
        foreach ($priceParams as $priceParam) {
          CRM_Waitlisttickets_BAO_WaitListTickets::addWaitlist($priceParam);
        }
      }
    }
  }

  function waitlisttickets_civicrm_searchColumns($objectName, &$headers, &$rows, &$selector) {
    if ($objectName == 'event' && !empty($rows)) {
      $statusTypes = CRM_Event_PseudoConstant::participantStatus();
      foreach ($rows as &$row) {
        if (in_array($statusTypes[$row['participant_status_id']], ["On waitlist", "Pending from waitlist"])) {
          $waitlist = CRM_Waitlisttickets_BAO_WaitListTickets::getWaitlistTickets($row['participant_id']);
          if (!empty($waitlist)) {
            $row['participant_fee_level'] = $waitlist;
          }
        }
      }
    }
  }

  function getPriceFieldInfo($eventId) {
    $priceSetId = CRM_Price_BAO_PriceSet::getFor('civicrm_event', $eventId);
    if (!$priceSetId) {
      return FALSE;
    }
    $priceFields = (array)civicrm_api3('PriceField', 'get', [
      'sequential' => 1,
      'return' => ["label", "html_type", "id"],
      'price_set_id' => $priceSetId,
      'is_active' => 1,
    ])['values'];
    if (empty($priceFields)) {
      return FALSE;
    }
    return $priceFields;
  }

  // --- Functions below this ship commented out. Uncomment as required. ---

  /**
   * Implements hook_civicrm_preProcess().
   *
   * @link https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_preProcess
   *

 // */

  /**
   * Implements hook_civicrm_navigationMenu().
   *
   * @link https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_navigationMenu
   *
  function waitlisttickets_civicrm_navigationMenu(&$menu) {
    _waitlisttickets_civix_insert_navigation_menu($menu, 'Mailings', array(
      'label' => E::ts('New subliminal message'),
      'name' => 'mailing_subliminal_message',
      'url' => 'civicrm/mailing/subliminal',
      'permission' => 'access CiviMail',
      'operator' => 'OR',
      'separator' => 0,
    ));
    _waitlisttickets_civix_navigationMenu($menu);
  } // */
