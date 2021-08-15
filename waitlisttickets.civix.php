<?php

// AUTO-GENERATED FILE -- Civix may overwrite any changes made to this file

/**
 * The ExtensionUtil class provides small stubs for accessing resources of this
 * extension.
 */
class CRM_Waitlisttickets_ExtensionUtil {
  const SHORT_NAME = "waitlisttickets";
  const LONG_NAME = "biz.jmaconsulting.waitlisttickets";
  const CLASS_PREFIX = "CRM_Waitlisttickets";

  /**
   * Translate a string using the extension's domain.
   *
   * If the extension doesn't have a specific translation
   * for the string, fallback to the default translations.
   *
   * @param string $text
   *   Canonical message text (generally en_US).
   * @param array $params
   * @return string
   *   Translated text.
   * @see ts
   */
  public static function ts($text, $params = array()) {
    if (!array_key_exists('domain', $params)) {
      $params['domain'] = array(self::LONG_NAME, NULL);
    }
    return ts($text, $params);
  }

  /**
   * Get the URL of a resource file (in this extension).
   *
   * @param string|NULL $file
   *   Ex: NULL.
   *   Ex: 'css/foo.css'.
   * @return string
   *   Ex: 'http://example.org/sites/default/ext/org.example.foo'.
   *   Ex: 'http://example.org/sites/default/ext/org.example.foo/css/foo.css'.
   */
  public static function url($file = NULL) {
    if ($file === NULL) {
      return rtrim(CRM_Core_Resources::singleton()->getUrl(self::LONG_NAME), '/');
    }
    return CRM_Core_Resources::singleton()->getUrl(self::LONG_NAME, $file);
  }

  /**
   * Get the path of a resource file (in this extension).
   *
   * @param string|NULL $file
   *   Ex: NULL.
   *   Ex: 'css/foo.css'.
   * @return string
   *   Ex: '/var/www/example.org/sites/default/ext/org.example.foo'.
   *   Ex: '/var/www/example.org/sites/default/ext/org.example.foo/css/foo.css'.
   */
  public static function path($file = NULL) {
    // return CRM_Core_Resources::singleton()->getPath(self::LONG_NAME, $file);
    return __DIR__ . ($file === NULL ? '' : (DIRECTORY_SEPARATOR . $file));
  }

  /**
   * Get the name of a class within this extension.
   *
   * @param string $suffix
   *   Ex: 'Page_HelloWorld' or 'Page\\HelloWorld'.
   * @return string
   *   Ex: 'CRM_Foo_Page_HelloWorld'.
   */
  public static function findClass($suffix) {
    return self::CLASS_PREFIX . '_' . str_replace('\\', '_', $suffix);
  }
  
  /**
   * @param array $params
   *
   * @return array
   */
  public static function process($params) {

    $returnMessages = [];

    $pendingStatuses = CRM_Event_PseudoConstant::participantStatus(NULL, "class = 'Pending'");
    $expiredStatuses = CRM_Event_PseudoConstant::participantStatus(NULL, "class = 'Negative'");
    $waitingStatuses = CRM_Event_PseudoConstant::participantStatus(NULL, "class = 'Waiting'");

    $participantDetails = $fullEvents = [];
    $expiredParticipantCount = $waitingConfirmCount = $waitingApprovalCount = 0;

    //get all participant who's status in class pending and waiting
    $query = "
   SELECT  participant.id,
           participant.contact_id,
           participant.status_id,
           participant.register_date,
           participant.registered_by_id,
           participant.event_id,
           event.title as eventTitle,
           event.registration_start_date,
           event.registration_end_date,
           event.end_date,
           event.expiration_time,
           event.requires_approval
           wlt.participant_count
     FROM  civicrm_wait_list_tickets wlt
     INNER JOIN civicrm_participant participant ON participant.id = wlt.participant_id
     LEFT JOIN  civicrm_event event ON ( event.id = participant.event_id )
    WHERE  (event.end_date > now() OR event.end_date IS NULL)
     AND   event.is_active = 1
 ORDER BY  wlt.participant_count DESC, wlt.id
";
    $dao = CRM_Core_DAO::executeQuery($query);
    while ($dao->fetch()) {
      $participantDetails[$dao->id] = [
        'id' => $dao->id,
        'event_id' => $dao->event_id,
        'status_id' => $dao->status_id,
        'contact_id' => $dao->contact_id,
        'register_date' => $dao->register_date,
        'registered_by_id' => $dao->registered_by_id,
        'eventTitle' => $dao->eventTitle,
        'registration_start_date' => $dao->registration_start_date,
        'registration_end_date' => $dao->registration_end_date,
        'end_date' => $dao->end_date,
        'expiration_time' => $dao->expiration_time,
        'requires_approval' => $dao->requires_approval,
        'participant_count' => $dao->participant_count,
      ];
    }

    if (!empty($participantDetails)) {
      //cron 1. move participant from pending to expire if needed
      foreach ($participantDetails as $participantId => $values) {
        //process the additional participant at the time of
        //primary participant, don't process separately.
        if (!empty($values['registered_by_id'])) {
          continue;
        }

        $expirationTime = $values['expiration_time'] ?? NULL;
        if ($expirationTime && array_key_exists($values['status_id'], $pendingStatuses)) {

          //get the expiration and registration pending time.
          $expirationSeconds = $expirationTime * 3600;
          $registrationPendingSeconds = CRM_Utils_Date::unixTime($values['register_date']);

          // expired registration since registration cross allow confirmation time.
          if (($expirationSeconds + $registrationPendingSeconds) < time()) {

            //lets get the transaction mechanism.
            $transaction = new CRM_Core_Transaction();

            $ids = [$participantId];
            $expiredId = array_search('Expired', $expiredStatuses);
            $results = CRM_Event_BAO_Participant::transitionParticipants($ids, $expiredId, $values['status_id'], TRUE, TRUE);
            $transaction->commit();

            if (!empty($results)) {
              //diaplay updated participants
              if (is_array($results['updatedParticipantIds']) && !empty($results['updatedParticipantIds'])) {
                foreach ($results['updatedParticipantIds'] as $processedId) {
                  $expiredParticipantCount += 1;
                  $returnMessages[] .= "<br />Status updated to: Expired";

                  //mailed participants.
                  if (is_array($results['mailedParticipants']) &&
                    array_key_exists($processedId, $results['mailedParticipants'])
                  ) {
                    $returnMessages[] .= "<br />Expiration Mail sent to: {$results['mailedParticipants'][$processedId]}";
                  }
                }
              }
            }
          }
        }
      }
      //cron 1 end.

      //cron 2. lets move participants from waiting list to pending status
      foreach ($participantDetails as $participantId => $values) {
        //process the additional participant at the time of
        //primary participant, don't process separately.
        if (!empty($values['registered_by_id'])) {
          continue;
        }

        if (array_key_exists($values['status_id'], $waitingStatuses) &&
          !array_key_exists($values['event_id'], $fullEvents)
        ) {

          if ($waitingStatuses[$values['status_id']] == 'On waitlist' &&
            CRM_Event_BAO_Event::validRegistrationDate($values)
          ) {

            //check the target event having space.
            $eventOpenSpaces = CRM_Event_BAO_Participant::eventFull($values['event_id'], TRUE, FALSE);

            if ($eventOpenSpaces && is_numeric($eventOpenSpaces) || ($eventOpenSpaces === NULL)) {

              //get the additional participant if any.
              $additionalIds = CRM_Event_BAO_Participant::getAdditionalParticipantIds($participantId);

              $allIds = [$participantId];
              if (!empty($additionalIds)) {
                $allIds = array_merge($allIds, $additionalIds);
              }
              $pClause = ' participant.id IN ( ' . implode(' , ', $allIds) . ' )';
              $requiredSpaces = CRM_Event_BAO_Event::eventTotalSeats($values['event_id'], $pClause);

              //need to check as to see if event has enough speces
              if (($requiredSpaces <= $eventOpenSpaces && $values['participant_count'] <= $requiredSpaces) || ($eventOpenSpaces === NULL)) {
                $transaction = new CRM_Core_Transaction();

                $ids = [$participantId];
                $updateStatusId = array_search('Pending from waitlist', $pendingStatuses);

                //lets take a call to make pending or need approval
                if ($values['requires_approval']) {
                  $updateStatusId = array_search('Awaiting approval', $waitingStatuses);
                }
                $results = CRM_Event_BAO_Participant::transitionParticipants($ids, $updateStatusId,
                  $values['status_id'], TRUE, TRUE
                );
                //commit the transaction.
                $transaction->commit();

                if (!empty($results)) {
                  //diaplay updated participants
                  if (is_array($results['updatedParticipantIds']) &&
                    !empty($results['updatedParticipantIds'])
                  ) {
                    foreach ($results['updatedParticipantIds'] as $processedId) {
                      if ($values['requires_approval']) {
                        $waitingApprovalCount += 1;
                        $returnMessages[] .= "<br /><br />- status updated to: Awaiting approval";
                        $returnMessages[] .= "<br />Will send you Confirmation Mail when registration gets approved.";
                      }
                      else {
                        $waitingConfirmCount += 1;
                        $returnMessages[] .= "<br /><br />- status updated to: Pending from waitlist";
                        if (is_array($results['mailedParticipants']) &&
                          array_key_exists($processedId, $results['mailedParticipants'])
                        ) {
                          $returnMessages[] .= "<br />Confirmation Mail sent to: {$results['mailedParticipants'][$processedId]}";
                        }
                      }
                    }
                  }
                }
              }
              else {
                //target event is full.
                $fullEvents[$values['event_id']] = $values['eventTitle'];
              }
            }
            else {
              //target event is full.
              $fullEvents[$values['event_id']] = $values['eventTitle'];
            }
          }
        }
      }
      //cron 2 ends.
    }

    $returnMessages[] .= "<br /><br />Number of Expired registration(s) = {$expiredParticipantCount}";
    $returnMessages[] .= "<br />Number of registration(s) require approval =  {$waitingApprovalCount}";
    $returnMessages[] .= "<br />Number of registration changed to Pending from waitlist = {$waitingConfirmCount}<br /><br />";
    if (!empty($fullEvents)) {
      foreach ($fullEvents as $eventId => $title) {
        $returnMessages[] .= "Full Event : {$title}<br />";
      }
    }

    return ['is_error' => 0, 'messages' => $returnMessages];
  }

}

use CRM_Waitlisttickets_ExtensionUtil as E;

/**
 * (Delegated) Implements hook_civicrm_config().
 *
 * @link https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_config
 */
function _waitlisttickets_civix_civicrm_config(&$config = NULL) {
  static $configured = FALSE;
  if ($configured) {
    return;
  }
  $configured = TRUE;

  $template =& CRM_Core_Smarty::singleton();

  $extRoot = dirname(__FILE__) . DIRECTORY_SEPARATOR;
  $extDir = $extRoot . 'templates';

  if (is_array($template->template_dir)) {
    array_unshift($template->template_dir, $extDir);
  }
  else {
    $template->template_dir = array($extDir, $template->template_dir);
  }

  $include_path = $extRoot . PATH_SEPARATOR . get_include_path();
  set_include_path($include_path);
}

/**
 * (Delegated) Implements hook_civicrm_xmlMenu().
 *
 * @param $files array(string)
 *
 * @link https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_xmlMenu
 */
function _waitlisttickets_civix_civicrm_xmlMenu(&$files) {
  foreach (_waitlisttickets_civix_glob(__DIR__ . '/xml/Menu/*.xml') as $file) {
    $files[] = $file;
  }
}

/**
 * Implements hook_civicrm_install().
 *
 * @link https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_install
 */
function _waitlisttickets_civix_civicrm_install() {
  _waitlisttickets_civix_civicrm_config();
  if ($upgrader = _waitlisttickets_civix_upgrader()) {
    $upgrader->onInstall();
  }
}

/**
 * Implements hook_civicrm_postInstall().
 *
 * @link https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_postInstall
 */
function _waitlisttickets_civix_civicrm_postInstall() {
  _waitlisttickets_civix_civicrm_config();
  if ($upgrader = _waitlisttickets_civix_upgrader()) {
    if (is_callable(array($upgrader, 'onPostInstall'))) {
      $upgrader->onPostInstall();
    }
  }
}

/**
 * Implements hook_civicrm_uninstall().
 *
 * @link https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_uninstall
 */
function _waitlisttickets_civix_civicrm_uninstall() {
  _waitlisttickets_civix_civicrm_config();
  if ($upgrader = _waitlisttickets_civix_upgrader()) {
    $upgrader->onUninstall();
  }
}

/**
 * (Delegated) Implements hook_civicrm_enable().
 *
 * @link https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_enable
 */
function _waitlisttickets_civix_civicrm_enable() {
  _waitlisttickets_civix_civicrm_config();
  if ($upgrader = _waitlisttickets_civix_upgrader()) {
    if (is_callable(array($upgrader, 'onEnable'))) {
      $upgrader->onEnable();
    }
  }
}

/**
 * (Delegated) Implements hook_civicrm_disable().
 *
 * @link https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_disable
 * @return mixed
 */
function _waitlisttickets_civix_civicrm_disable() {
  _waitlisttickets_civix_civicrm_config();
  if ($upgrader = _waitlisttickets_civix_upgrader()) {
    if (is_callable(array($upgrader, 'onDisable'))) {
      $upgrader->onDisable();
    }
  }
}

/**
 * (Delegated) Implements hook_civicrm_upgrade().
 *
 * @param $op string, the type of operation being performed; 'check' or 'enqueue'
 * @param $queue CRM_Queue_Queue, (for 'enqueue') the modifiable list of pending up upgrade tasks
 *
 * @return mixed  based on op. for 'check', returns array(boolean) (TRUE if upgrades are pending)
 *                for 'enqueue', returns void
 *
 * @link https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_upgrade
 */
function _waitlisttickets_civix_civicrm_upgrade($op, CRM_Queue_Queue $queue = NULL) {
  if ($upgrader = _waitlisttickets_civix_upgrader()) {
    return $upgrader->onUpgrade($op, $queue);
  }
}

/**
 * @return CRM_Waitlisttickets_Upgrader
 */
function _waitlisttickets_civix_upgrader() {
  if (!file_exists(__DIR__ . '/CRM/Waitlisttickets/Upgrader.php')) {
    return NULL;
  }
  else {
    return CRM_Waitlisttickets_Upgrader_Base::instance();
  }
}

/**
 * Search directory tree for files which match a glob pattern
 *
 * Note: Dot-directories (like "..", ".git", or ".svn") will be ignored.
 * Note: In Civi 4.3+, delegate to CRM_Utils_File::findFiles()
 *
 * @param $dir string, base dir
 * @param $pattern string, glob pattern, eg "*.txt"
 * @return array(string)
 */
function _waitlisttickets_civix_find_files($dir, $pattern) {
  if (is_callable(array('CRM_Utils_File', 'findFiles'))) {
    return CRM_Utils_File::findFiles($dir, $pattern);
  }

  $todos = array($dir);
  $result = array();
  while (!empty($todos)) {
    $subdir = array_shift($todos);
    foreach (_waitlisttickets_civix_glob("$subdir/$pattern") as $match) {
      if (!is_dir($match)) {
        $result[] = $match;
      }
    }
    if ($dh = opendir($subdir)) {
      while (FALSE !== ($entry = readdir($dh))) {
        $path = $subdir . DIRECTORY_SEPARATOR . $entry;
        if ($entry{0} == '.') {
        }
        elseif (is_dir($path)) {
          $todos[] = $path;
        }
      }
      closedir($dh);
    }
  }
  return $result;
}
/**
 * (Delegated) Implements hook_civicrm_managed().
 *
 * Find any *.mgd.php files, merge their content, and return.
 *
 * @link https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_managed
 */
function _waitlisttickets_civix_civicrm_managed(&$entities) {
  $mgdFiles = _waitlisttickets_civix_find_files(__DIR__, '*.mgd.php');
  sort($mgdFiles);
  foreach ($mgdFiles as $file) {
    $es = include $file;
    foreach ($es as $e) {
      if (empty($e['module'])) {
        $e['module'] = E::LONG_NAME;
      }
      if (empty($e['params']['version'])) {
        $e['params']['version'] = '3';
      }
      $entities[] = $e;
    }
  }
}

/**
 * (Delegated) Implements hook_civicrm_caseTypes().
 *
 * Find any and return any files matching "xml/case/*.xml"
 *
 * Note: This hook only runs in CiviCRM 4.4+.
 *
 * @link https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_caseTypes
 */
function _waitlisttickets_civix_civicrm_caseTypes(&$caseTypes) {
  if (!is_dir(__DIR__ . '/xml/case')) {
    return;
  }

  foreach (_waitlisttickets_civix_glob(__DIR__ . '/xml/case/*.xml') as $file) {
    $name = preg_replace('/\.xml$/', '', basename($file));
    if ($name != CRM_Case_XMLProcessor::mungeCaseType($name)) {
      $errorMessage = sprintf("Case-type file name is malformed (%s vs %s)", $name, CRM_Case_XMLProcessor::mungeCaseType($name));
      CRM_Core_Error::fatal($errorMessage);
      // throw new CRM_Core_Exception($errorMessage);
    }
    $caseTypes[$name] = array(
      'module' => E::LONG_NAME,
      'name' => $name,
      'file' => $file,
    );
  }
}

/**
 * (Delegated) Implements hook_civicrm_angularModules().
 *
 * Find any and return any files matching "ang/*.ang.php"
 *
 * Note: This hook only runs in CiviCRM 4.5+.
 *
 * @link https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_angularModules
 */
function _waitlisttickets_civix_civicrm_angularModules(&$angularModules) {
  if (!is_dir(__DIR__ . '/ang')) {
    return;
  }

  $files = _waitlisttickets_civix_glob(__DIR__ . '/ang/*.ang.php');
  foreach ($files as $file) {
    $name = preg_replace(':\.ang\.php$:', '', basename($file));
    $module = include $file;
    if (empty($module['ext'])) {
      $module['ext'] = E::LONG_NAME;
    }
    $angularModules[$name] = $module;
  }
}

/**
 * (Delegated) Implements hook_civicrm_themes().
 *
 * Find any and return any files matching "*.theme.php"
 */
function _waitlisttickets_civix_civicrm_themes(&$themes) {
  $files = _waitlisttickets_civix_glob(__DIR__ . '/*.theme.php');
  foreach ($files as $file) {
    $themeMeta = include $file;
    if (empty($themeMeta['name'])) {
      $themeMeta['name'] = preg_replace(':\.theme\.php$:', '', basename($file));
    }
    if (empty($themeMeta['ext'])) {
      $themeMeta['ext'] = E::LONG_NAME;
    }
    $themes[$themeMeta['name']] = $themeMeta;
  }
}

/**
 * Glob wrapper which is guaranteed to return an array.
 *
 * The documentation for glob() says, "On some systems it is impossible to
 * distinguish between empty match and an error." Anecdotally, the return
 * result for an empty match is sometimes array() and sometimes FALSE.
 * This wrapper provides consistency.
 *
 * @link http://php.net/glob
 * @param string $pattern
 * @return array, possibly empty
 */
function _waitlisttickets_civix_glob($pattern) {
  $result = glob($pattern);
  return is_array($result) ? $result : array();
}

/**
 * Inserts a navigation menu item at a given place in the hierarchy.
 *
 * @param array $menu - menu hierarchy
 * @param string $path - path to parent of this item, e.g. 'my_extension/submenu'
 *    'Mailing', or 'Administer/System Settings'
 * @param array $item - the item to insert (parent/child attributes will be
 *    filled for you)
 */
function _waitlisttickets_civix_insert_navigation_menu(&$menu, $path, $item) {
  // If we are done going down the path, insert menu
  if (empty($path)) {
    $menu[] = array(
      'attributes' => array_merge(array(
        'label'      => CRM_Utils_Array::value('name', $item),
        'active'     => 1,
      ), $item),
    );
    return TRUE;
  }
  else {
    // Find an recurse into the next level down
    $found = FALSE;
    $path = explode('/', $path);
    $first = array_shift($path);
    foreach ($menu as $key => &$entry) {
      if ($entry['attributes']['name'] == $first) {
        if (!isset($entry['child'])) {
          $entry['child'] = array();
        }
        $found = _waitlisttickets_civix_insert_navigation_menu($entry['child'], implode('/', $path), $item, $key);
      }
    }
    return $found;
  }
}

/**
 * (Delegated) Implements hook_civicrm_navigationMenu().
 */
function _waitlisttickets_civix_navigationMenu(&$nodes) {
  if (!is_callable(array('CRM_Core_BAO_Navigation', 'fixNavigationMenu'))) {
    _waitlisttickets_civix_fixNavigationMenu($nodes);
  }
}

/**
 * Given a navigation menu, generate navIDs for any items which are
 * missing them.
 */
function _waitlisttickets_civix_fixNavigationMenu(&$nodes) {
  $maxNavID = 1;
  array_walk_recursive($nodes, function($item, $key) use (&$maxNavID) {
    if ($key === 'navID') {
      $maxNavID = max($maxNavID, $item);
    }
  });
  _waitlisttickets_civix_fixNavigationMenuItems($nodes, $maxNavID, NULL);
}

function _waitlisttickets_civix_fixNavigationMenuItems(&$nodes, &$maxNavID, $parentID) {
  $origKeys = array_keys($nodes);
  foreach ($origKeys as $origKey) {
    if (!isset($nodes[$origKey]['attributes']['parentID']) && $parentID !== NULL) {
      $nodes[$origKey]['attributes']['parentID'] = $parentID;
    }
    // If no navID, then assign navID and fix key.
    if (!isset($nodes[$origKey]['attributes']['navID'])) {
      $newKey = ++$maxNavID;
      $nodes[$origKey]['attributes']['navID'] = $newKey;
      $nodes[$newKey] = $nodes[$origKey];
      unset($nodes[$origKey]);
      $origKey = $newKey;
    }
    if (isset($nodes[$origKey]['child']) && is_array($nodes[$origKey]['child'])) {
      _waitlisttickets_civix_fixNavigationMenuItems($nodes[$origKey]['child'], $maxNavID, $nodes[$origKey]['attributes']['navID']);
    }
  }
}

/**
 * (Delegated) Implements hook_civicrm_alterSettingsFolders().
 *
 * @link https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_alterSettingsFolders
 */
function _waitlisttickets_civix_civicrm_alterSettingsFolders(&$metaDataFolders = NULL) {
  $settingsDir = __DIR__ . DIRECTORY_SEPARATOR . 'settings';
  if (!in_array($settingsDir, $metaDataFolders) && is_dir($settingsDir)) {
    $metaDataFolders[] = $settingsDir;
  }
}

/**
 * (Delegated) Implements hook_civicrm_entityTypes().
 *
 * Find any *.entityType.php files, merge their content, and return.
 *
 * @link https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_entityTypes
 */

function _waitlisttickets_civix_civicrm_entityTypes(&$entityTypes) {
  $entityTypes = array_merge($entityTypes, array (
    'CRM_Waitlisttickets_DAO_WaitListTickets' => 
    array (
      'name' => 'WaitListTickets',
      'class' => 'CRM_Waitlisttickets_DAO_WaitListTickets',
      'table' => 'civicrm_wait_list_tickets',
    ),
  ));
}
