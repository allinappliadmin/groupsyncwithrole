<?php
  use CRM_Groupsyncwithrole_ExtensionUtil as E;
  
  class CRM_Groupsyncwithrole_Utils {
    
    public static function getGroupsCiviCRM() {
      $defaultName = ts('-- Select a group --');
      $groupsSelect = [$defaultName];
      $groups = \Civi\Api4\Group::get(FALSE)
        ->addSelect('id', 'name', 'title')
        ->execute();
      foreach ($groups as $group) {
        // do something
        $groupsSelect[$group['name']] = $group['title'];
      }
      
      return $groupsSelect;
    }
  
    public static function getRoleCMSWP() {
      $defaultName = ts('-- Select a WordPress role --');
      $roleSelect = [$defaultName];
      $roles_obj = new WP_Roles();
      $roles_names_array = $roles_obj->get_names();
      foreach ($roles_names_array as $key => $role_name) {
        // do something
        $roleSelect[$key] = $role_name;
      }
    
      return $roleSelect;
    }
  }