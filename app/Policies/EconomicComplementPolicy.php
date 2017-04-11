<?php

namespace Muserpol\Policies;

use Illuminate\Auth\Access\HandlesAuthorization;
use Muserpol\Role;
use Muserpol\Module;
use Muserpol\User;

class EconomicComplementPolicy
{
    use HandlesAuthorization;

    /**
     * Create a new policy instance.
     *
     * @return void
     */
    public function __construct()
    {
        //
    }
    public function before($user, $economicComplement){
      $roles=$user->roles;
      foreach ($roles as $role) {
        if($role->id==9 || $role->id==1)
          return  true;
      }
      return null;
    }

    public function recepcionEco($user, $economicComplement){
        $roles=$user->roles;
        foreach ($roles as $role) {
          if($role->id==7)
            return  true;
        }
        return false;

    }
    public function revisionEco($user, $economicComplement){
    //  dd($user);
    $roles=$user->roles;
    foreach ($roles as $role) {
      if($role->id==8)
        return  true;
    }
    return false;
      //  return $user->role_id==8;
    }

}
