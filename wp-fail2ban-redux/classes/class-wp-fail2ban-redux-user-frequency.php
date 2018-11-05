<?php
// Bail if accessed directly.
defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'WP_Fail2Ban_Redux' ) ) {

  class WP_Fail2Ban_Redux_User_Frequency implements JsonSerializable {
    public $timeout = 60;
    public $ratelimit = 5;
    public function __construct($key, $testFn,  $timeout=null, $ratelimit=null ){
      $this->key = $key;
      $this->testFn = $testFn;
      if($timeout) $this->timeout = $timeout;
      if($ratelimit) $this->ratelimit = $ratelimit;
      $this->freqs = Array();
    }

    public function save(){
      $this->do_expiration();
      update_option("user-frequency-$this->key", json_encode($this));
    }

    public function load(){
      $opt = get_option("user-frequency-$this->key");
      $this->freqs = json_decode($opt, true);
      if(!$this->freqs) $this->freqs = Array();
      $this->do_expiration();
    }

    public function do_expiration(){
      $now = time();
      foreach($this->freqs as $user=>$tss){
        $remain = Array();
        if(!$tss) $tss = Array();
        foreach($tss as $ts){
          if(!(($now - $ts) > $this->timeout)){
            $remain[] = $ts;
          }
        }
        $this->freqs[$user] = $remain;
      }
    }

    public function is_over_threshold($user=null){
      $fn = $this->testFn; // needed since its not a method
      if(!$fn($user)){ return false; }
      $this->load();
      $tss = Array();
      if(isset($this->freqs[$user])){
        $tss = $this->freqs[$user];
      }
      if(!$tss) $tss = Array();
      $tss[] = time();
      $this->freqs[$user] = $tss;
      $this->save();
      // error_log('User '.$user. ' over? '.count($tss) .' > '.$this->ratelimit);
      return count($tss) > $this->ratelimit;
    }

    public function jsonSerialize(){
      return $this->freqs;
    }
  }
}
