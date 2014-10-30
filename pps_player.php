<?php 
/*
This file 'pps_palyer.php' is part of the PPS project <http://code.google.com/p/fracsnetpps/>

Copyright: (C) 2009 Arthur, B. aka ]{ing <whinemore@gmail.com>

 This program is free software; you can redistribute it and/or
 modify it under the terms of the GNU General Public License
 as published by the Free Software Foundation; either version 2
 of the License, or (at your option) any later version.
 .
 This program is distributed in the hope that it will be useful,
 but WITHOUT ANY WARRANTY; without even the implied warranty of
 MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 GNU General Public License for more details.
 .
 You should have received a copy of the GNU General Public License
 along with this program; if not, write to the Free Software
 Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301, USA.
 .

 */
 
class base_player{

    var $name;                      
    var $acc_name;
    var $code;
    var $k;
    var $d;
    var $c;
    var $g;
    var $r; /* returns */
    var $w; /* Weapons array */
    public $tp;
    var $kdr;
    var $cgr;
    var $ckr;
    /* misc */
    var $t; /* tier */
    public $p_id; 
    var $ip;
    var $hwid;
    var $maps;
    var $wins;
    var $minutes;
    var $spot; /* ingame spot */
    var $mlt; /* mltiple kill array */


    /* 
     * Buffers for all the ingame-stats 
     * Stats get saved to the buffer and writen
     * to the database after map is done.
     *
     * */
    public $k_buffer;
    public $d_buffer;
    public $c_buffer;
    public $g_buffer;
    public $r_buffer;
    public $mlt_buffer;
    public $tnum;
    /* Timers */
    public $kill_timer;
    public $kill_count;
    public $team; /* This is now a referense to the team array the player is in... */
    public $map_timer; /* Time Played in current Map */
    var $flag; 
    public $is_rated; /* boolean; 200 kills 20 caps */
    public $active; /* WIP */
    public $wstreak;
    public $plus_minus;
    public $u_id;
    public $dominated;
    public $dominations;
    /* Rank variables */
    public $mu = 25;
    public $sigma = 8.3;
    public $rating;
    public $old_rating;
    public $act;
    /* Auth var for the gathers */
    public $auth;
    public $left_early = false;/* Dirty flag, for leavers */ 

    public function __construct( $record = null,  $name = 'Soldier'){
        /* Available database records make all other jobs a lot easier */
        $this->minutes = time();

        $this->mlt = array();

        $this->mlt[0] = 0;

        if( $record ){
		
            $this->acc_name = $record['name'];
            $this->name = $name;
            $this->k = $record['kills'];
            $this->d = $record['deaths'];
            $this->c = $record['caps'];
            $this->g = $record['grabs'];
            $this->r = $record['returns'];
            $this->t = $record['tier'];
            $this->maps = $record['maps'];
            $this->wins = $record['wins'];
            $this->kdr = $record['kd'];
            $this->cgr = $record['cg'];
            $this->tp = $record['time_played'];
            $this->code = $record['code']; 

            $this->hwid = $record['hwid'];
            //$this->ip = $record['ip'];

            $this->mlt[1] = $record['doubles'];
            $this->mlt[2] = $record['triples'];
            $this->mlt[3] = $record['multi'];

            $this->wstreak = $record['streak'];

            $this->kill_count = 0;

            $this->plus_minus = $record['plusminus'];

            /* Rank variables */
            $this->mu = $record['mu'];
            $this->sigma = $record['sigma'];
            $this->rating = $record['rating'];
            $this->old_rating = $record['old_rating'];
            $this->is_rated = true;
            
            $this->dominations = $record['dominations'];
            $this->auth = $record['auth'];
        }else{
		
            $this->name = $name;
            $this->code = "";
            $this->acc_name = null;
            $this->k = 0;
            $this->d = 0;
            $this->c = 0;
            $this->g = 0;
            $this->r = 0;
            $this->t = 0;
            $this->mlt[1] = 0;
            $this->mlt[2] = 0;
            $this->mlt[3] = 0;
            $this->kdr = 0;
            $this->cgr = 0;
            $this->tp = 0;
            //$this->points = 0;
            $this->ip = "localhost";
            $this->hwid = null;
            $this->kill_count = 0;
            $this->maps = 0;
            $this->wins = 0;

            $this->is_rated = false;

            $this->sigma = 8.3;
            $this->mu = 25;
            $this->rating = "Not rated";
            $this->auth = "Q";
        }
		
        $this->kill_timer = $this->minutes; /* This is sorta important */
        $this->map_timer = $this->minutes;
        $this->clear_buffers();
        
        $this->flag = 0;

        /* In game dominations */
        $this->dominated = array();
        for( $i = 0; $i < 33; $i++ )
            $this->dominated[$i] = 0;
         
        /* Personal Weapon Stats are never fetched */
        $this->w = array(
            'Desert Eagles' => 0,
            'HK MP5' => 0,
            'Ak-74' => 0,
            'Steyr AUG' => 0,
            'Spas-12' => 0,
            'Ruger 77' => 0,
            'M79' => 0,
            'Hands' => 0,
            'Barrett M82A1' => 0,
            'FN Minimi' => 0,
            'XM214 Minigun' => 0,
            'Selfkill' => 0,
            'Combat Knife' => 0,
            'Chainsaw' => 0,
            'LAW' => 0,
            'Grenade' => 0
        );
	
    }
	
    public function full_map( $tm ) {
        if( ($tm - $this->map_timer) > PPS_FULL_MAP_TIME ) {
            return true;
        } 

        return false;
    }

    /* Update Functions */
    public function update_kills($weapon){
        if( isset($weapon) ){			
            $this->k_buffer++;
            $this->w[$weapon]++;
        }
    }
	
    public function update_kdr(){
        if( $this->k ){
            if( $this->d ){
            $this->kdr = $this->k / $this->d ;
            $this->kdr = round($this->kdr, 2);
            }else{
            $this->kdr = $this->k;
            }
        }else{
            if( $this->d ){
            $this->kdr = 0.001; /* As soon as the player gets a kill this is set to a proper value */
            }else{
            $this->kdr = 0;
            }
        }
    }
	
    public function update_cgr(){
        if( $this->c ){

            $this->cgr = ( $this->g ) ? $this->c / $this->g : 0;
        }else{

            if( $this->g ){

                $this->cgr = 0.001; /* As soon as the player gets a cap this is set to a proper value */
            }else{

                $this->cgr = 0;
            }
        }
        $this->cgr = round($this->cgr, 2);
    }
	
    public function update_ckr() {

        if( $this->c ){

            if( $this->k ){

                $this->ckr = ( $this->k != 0 ) ? $this->c / $this->k : 0 ;
            }else{

                $this->ckr = $this->c ;
            }
        }else{
            if( $this->k ){

                $this->ckr = 0.001; 
            }else{

                $this->ckr = 0;
            }
        }

        $this->ckr = round($this->ckr, 2);
    }
	
    public function update_time_played(){
        $this->tp += round( ((time() - $this->minutes)/60) , 0 );
        $this->minutes = time();
    }
    
    public function merge_buffers(){
        $this->k += $this->k_buffer;
        $this->d += $this->d_buffer;
        $this->c += $this->c_buffer;
        $this->g += $this->g_buffer;
        $this->r += $this->r_buffer;         
   }
   
   public function clear_buffers(){
        $this->k_buffer = 0;
        $this->d_buffer = 0;
        $this->c_buffer = 0;
        $this->g_buffer = 0;
        $this->r_buffer = 0; 
   }

   public function has_rating()
   {
       return ( $mu != 25 && $mu != 8.3 );
   }
}



?>
