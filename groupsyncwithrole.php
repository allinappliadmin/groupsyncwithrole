<?php

require_once 'groupsyncwithrole.civix.php';

use CRM_Groupsyncwithrole_ExtensionUtil as E;

/**
 * Implements hook_civicrm_config().
 *
 * @link https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_config/
 */
function groupsyncwithrole_civicrm_config(&$config): void {
  _groupsyncwithrole_civix_civicrm_config($config);
  Civi::resources()->addStyleFile('groupsyncwithrole', 'css/styles.css');
  Civi::resources()->addScriptFile('groupsyncwithrole', 'js/script.js');
  
  // in the future hook symfony in this place !!
  //Civi::dispatcher()->addListener('hook_civicrm_post', ['CRM_Groupsyncwithrole_SyncWpRole', 'syncWpRole'], -1000);
}

/**
 * Implements hook_civicrm_install().
 *
 * @link https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_install
 */
function groupsyncwithrole_civicrm_install(): void {
  _groupsyncwithrole_civix_civicrm_install();
}

/**
 * Implements hook_civicrm_enable().
 *
 * @link https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_enable
 */
function groupsyncwithrole_civicrm_enable(): void {
  _groupsyncwithrole_civix_civicrm_enable();
}
  
  /**
   * This hook is called after a db write on entities.
   *
   * @param string $op
   *   The type of operation being performed.
   * @param string $objectName
   *   The name of the object.
   * @param int $objectId
   *   The unique identifier for the object.
   * @param object $objectRef
   *   The reference to the object.
   */
  function groupsyncwithrole_civicrm_post(string $op, string $objectName, int $objectId, &$objectRef) {
    // name groupe table group civicrm => rôle WP,
    // example
    /*$map = [
      'medical_2' => 'medical',
      'Auteur_3' => 'author'
    ];*/
    
    $map = CRM_Groupsyncwithrole_Utils::getSettingsGroupSyncWPRoleForMap();
  
    if (in_array($objectName, ['GroupContact']) && in_array($op,
        ['create', 'edit', 'delete'])) {
    
      if (CRM_Core_Transaction::isActive()) {
      
        CRM_Core_Transaction::addCallback(CRM_Core_Transaction::PHASE_POST_COMMIT,
          'groupsyncwithrole_civicrm_post_groupcontact_callback',
          [$op, $objectName, $objectId, $objectRef, $map]);
      } else {
        
        groupsyncwithrole_civicrm_post_groupcontact_callback($op,
          $objectName,
          $objectId,
          $objectRef,
          $map);
      }
    }
  }
  
  /**
   * Synchronize the WP role and CiviCRM contact group
   */
  function groupsyncwithrole_civicrm_post_groupcontact_callback($op, $objectName, $objectId, $objectRef, $map)
  {
    if(!empty($objectRef)) {
    
      // search uf_id
      $uFMatches = \Civi\Api4\UFMatch::get(FALSE)
        ->addSelect('uf_id')
        ->addWhere('contact_id', 'IN', $objectRef)
        ->execute()
        ->first();
    
      if(!empty($uFMatches)) {
        $u = new WP_User($uFMatches['uf_id']);
      
        $groupContacts = \Civi\Api4\GroupContact::get(FALSE)
          ->addSelect('*', 'custom.*','group_id.name')
          ->addWhere('group_id', '=', $objectId)
          ->addWhere('contact_id', 'IN', $objectRef)
          ->execute()
          ->first();
      
        // addition or reintegration contact in the group
        if($op == 'create') {
          foreach ($map as $groupName => $roleName) {
            if ($groupContacts['status'] == 'Added' && $groupName == $groupContacts['group_id.name']) {
              Civi::log()->debug('Has role ' . $groupName);
              $u->add_role($roleName);
            }
          }
        }
      
        // remove the contact of group
        if($op == 'delete') {
          foreach ($map as $groupName => $roleName) {
            if ($groupContacts['status'] == 'Removed' && $groupName == $groupContacts['group_id.name']) {
              Civi::log()->debug('Remove role ' . $groupName);
              $u->remove_role($roleName);
            }
          }
        }
      
        // delete contact of group
        if($op == 'delete' && empty($groupContacts)) {
          $groupDeleted = \Civi\Api4\Group::get(FALSE)
            ->addSelect('*', 'custom.*')
            ->addWhere('id', '=', $objectId)
            ->execute()
            ->first();
        
          foreach ($map as $groupName => $roleName) {
            if ($groupName == $groupDeleted['name']) {
              Civi::log()->debug('Remove role after delete contact of group ' . $groupName);
              $u->remove_role($roleName);
            }
          }
        }
      
      } else {
        Civi::log()->debug('Contact ID '. $objectRef[0] .' doesn\'t exist in table UFMatch');
      }
    
    } else {
      Civi::log()->debug('synchronisation groups doesn\'t works -- role : $objectRef is empty');
    }
  }