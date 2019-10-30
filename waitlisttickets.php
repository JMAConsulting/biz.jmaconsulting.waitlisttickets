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
  function waitlisttickets_civicrm_xmlMenu(&$files) {
    _waitlisttickets_civix_civicrm_xmlMenu($files);
  }

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
  function waitlisttickets_civicrm_postInstall() {
    _waitlisttickets_civix_civicrm_postInstall();
  }

  /**
   * Implements hook_civicrm_uninstall().
   *
   * @link https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_uninstall
   */
  function waitlisttickets_civicrm_uninstall() {
    _waitlisttickets_civix_civicrm_uninstall();
  }

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
  function waitlisttickets_civicrm_disable() {
    _waitlisttickets_civix_civicrm_disable();
  }

  /**
   * Implements hook_civicrm_upgrade().
   *
   * @link https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_upgrade
   */
  function waitlisttickets_civicrm_upgrade($op, CRM_Queue_Queue $queue = NULL) {
    return _waitlisttickets_civix_civicrm_upgrade($op, $queue);
  }

  /**
   * Implements hook_civicrm_managed().
   *
   * Generate a list of entities to create/deactivate/delete when this module
   * is installed, disabled, uninstalled.
   *
   * @link https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_managed
   */
  function waitlisttickets_civicrm_managed(&$entities) {
    _waitlisttickets_civix_civicrm_managed($entities);
  }

  /**
   * Implements hook_civicrm_caseTypes().
   *
   * Generate a list of case-types.
   *
   * Note: This hook only runs in CiviCRM 4.4+.
   *
   * @link https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_caseTypes
   */
  function waitlisttickets_civicrm_caseTypes(&$caseTypes) {
    _waitlisttickets_civix_civicrm_caseTypes($caseTypes);
  }

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
  function waitlisttickets_civicrm_angularModules(&$angularModules) {
    _waitlisttickets_civix_civicrm_angularModules($angularModules);
  }

  /**
   * Implements hook_civicrm_alterSettingsFolders().
   *
   * @link https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_alterSettingsFolders
   */
  function waitlisttickets_civicrm_alterSettingsFolders(&$metaDataFolders = NULL) {
    _waitlisttickets_civix_civicrm_alterSettingsFolders($metaDataFolders);
  }

  /**
   * Implements hook_civicrm_entityTypes().
   *
   * Declare entity types provided by this module.
   *
   * @link https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_entityTypes
   */
  function waitlisttickets_civicrm_entityTypes(&$entityTypes) {
    _waitlisttickets_civix_civicrm_entityTypes($entityTypes);
  }

  /**
   * Implements hook_civicrm_thems().
   */
  function waitlisttickets_civicrm_themes(&$themes) {
    _waitlisttickets_civix_civicrm_themes($themes);
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
          $key = "price_field_id_" . $priceField["id"];
          $fieldOptions = [];
          if ($priceField["html_type"] == "Select") {
            $fieldOptions = (array) civicrm_api3("PriceFieldValue", "get", [
              "price_field_id" => $priceField["id"],
              "return" => ["name", "label"],
            ])['values'];
            if (!empty($fieldOptions)) {
              foreach ($fieldOptions as $fieldOption) {
                $selectOptions[$fieldOption['name']] = $fieldOption['label'];
              }
            }
          }
          $form->add($priceField["html_type"], $key, ts($priceField['label']), $selectOptions);
          $ticketOptions[] = $key;
        }
        $form->assign('ticketOptions', $ticketOptions);
        CRM_Core_Region::instance('page-body')->add(array(
          'template' => 'CRM/WaitlistPriceFields.tpl',
        ));
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
  function waitlisttickets_civicrm_preProcess($formName, &$form) {

  } // */

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
