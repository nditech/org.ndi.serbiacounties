<?php

/**
 * Provide config options for the extension.
 *
 * @return array
 *   Array of:
 *   - whether to remove existing states/provinces,
 *   - ISO abbreviation of the country,
 *   - list of states/provinces with abbreviation,
 *   - list of states/provinces to rename,
 */
function serbiacounties_stateConfig() {
  $config = array(
    // CAUTION: only use `overwrite` on fresh databases.
    'overwrite' => TRUE,
    'countryIso' => 'RS',
    'states' => array(
      // 'state name' => 'abbreviation',
      'Beograd' => '1',
      'Severnobački upravni okrug' => '2',
      'Srednjobanatski upravni okrug' => '3',
      'Severnobanatski upravni okrug' => '4',
      'Južnobanatski upravni okrug' => '5',
      'Zapadnobački uprvni okrug' => '6',
      'Južnobački upravni okrug' => '7',
      'Sremski upravni okrug' => '8',
      'Mačvanski upravni okrug' => '9',
      'Kolubarski upravni okrug' => '10',
      'Zlatiborski upravni okrug' => '11',
      'Podunavski upravni okrug' => '12',
      'Šumadijski upravni okrug' => '13',
      'Pomoravski upravni okrug' => '14',
      'Moravički upravni okrug' => '15',
      'Raški upravni okrug' => '16',
      'Rasinski upravni okrug' => '17',
      'Braničevski upravni okrug' => '18',
      'Borski upravni okrug' => '19',
      'Zaječarski upravni okrug' => '20',
      'Nišavski upravni okrug' => '21',
      'Toplički upravni okrug' => '22',
      'Pirotski upravni okrug' => '23',
      'Jablanički upravni okrug' => '24',
      'Pčinjski upravni okrug' => '25',
    ),
    'rewrites' => array(
      // List states to rewrite in the format:
      // 'Default State Name' => 'Corrected State Name',
    ),
  );
  return $config;
}

/**
 * Check and load states/provinces.
 *
 * @return bool
 *   Success true/false.
 */
function serbiacounties_loadProvinces() {
  $stateConfig = serbiacounties_stateConfig();

  if (empty($stateConfig['states']) || empty($stateConfig['countryIso'])) {
    return FALSE;
  }

  static $dao = NULL;
  if (!$dao) {
    $dao = new CRM_Core_DAO();
  }

  $statesToAdd = $stateConfig['states'];

  try {
    $countryId = civicrm_api3('Country', 'getvalue', array(
      'return' => 'id',
      'iso_code' => $stateConfig['countryIso'],
    ));
  }
  catch (CiviCRM_API3_Exception $e) {
    $error = $e->getMessage();
    CRM_Core_Error::debug_log_message(ts('API Error: %1', array(
      'domain' => 'org.ndi.serbiacounties',
      1 => $error,
    )));
    return FALSE;
  }

  // Rewrite states.
  if (!empty($stateConfig['rewrites'])) {
    foreach ($stateConfig['rewrites'] as $old => $new) {
      $sql = 'UPDATE civicrm_state_province SET name = %1 WHERE name = %2 and country_id = %3';
      $stateParams = array(
        1 => array(
          $new,
          'String',
        ),
        2 => array(
          $old,
          'String',
        ),
        3 => array(
          $countryId,
          'Integer',
        ),
      );
      CRM_Core_DAO::executeQuery($sql, $stateParams);
    }
  }

  // Find states that are already there.
  $stateIdsToKeep = array();
  foreach ($statesToAdd as $state => $abbr) {
    $sql = 'SELECT id FROM civicrm_state_province WHERE name = %1 AND country_id = %2 LIMIT 1';
    $stateParams = array(
      1 => array(
        $state,
        'String',
      ),
      2 => array(
        $countryId,
        'Integer',
      ),
    );
    $foundState = CRM_Core_DAO::singleValueQuery($sql, $stateParams);

    if ($foundState) {
      unset($statesToAdd[$state]);
      $stateIdsToKeep[] = $foundState;
      continue;
    }
  }

  // Wipe out states to remove.
  if (!empty($stateConfig['overwrite'])) {
    $sql = 'SELECT id FROM civicrm_state_province WHERE country_id = %1';
    $params = array(
      1 => array(
        $countryId,
        'Integer',
      ),
    );
    $dbStates = CRM_Core_DAO::executeQuery($sql, $params);
    $deleteIds = array();
    while ($dbStates->fetch()) {
      if (!in_array($dbStates->id, $stateIdsToKeep)) {
        $deleteIds[] = $dbStates->id;
      }
    }

    // Go delete the remaining old ones.
    foreach ($deleteIds as $id) {
      $sql = "DELETE FROM civicrm_state_province WHERE id = %1";
      $params = array(
        1 => array(
          $id,
          'Integer',
        ),
      );
      CRM_Core_DAO::executeQuery($sql, $params);
    }
  }

  // Add new states.
  $insert = array();
  foreach ($statesToAdd as $state => $abbr) {
    $stateE = $dao->escape($state);
    $abbrE = $dao->escape($abbr);
    $insert[] = "('$stateE', '$abbrE', $countryId)";
  }

  // Put it into queries of 50 states each.
  for ($i = 0; $i < count($insert); $i = $i + 50) {
    $inserts = array_slice($insert, $i, 50);
    $query = "INSERT INTO civicrm_state_province (name, abbreviation, country_id) VALUES ";
    $query .= implode(', ', $inserts);
    CRM_Core_DAO::executeQuery($query);
  }

  return TRUE;
}

/**
 * Implements hook_civicrm_install().
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_install
 */
function serbiacounties_civicrm_install() {
  serbiacounties_loadProvinces();
}

/**
 * Implements hook_civicrm_enable().
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_enable
 */
function serbiacounties_civicrm_enable() {
  serbiacounties_loadProvinces();
}

/**
 * Implements hook_civicrm_upgrade().
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_upgrade
 */
function serbiacounties_civicrm_upgrade($op, CRM_Queue_Queue $queue = NULL) {
  serbiacounties_loadProvinces();
}
