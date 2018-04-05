<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class State extends Model
{
  public function subscriptions(){
    return $this->hasMany('App\Subscription');
  }
}
