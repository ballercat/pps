<?php
/*
This file 'pps_base.php' is part of the PPS project(gather branch) <http://code.google.com/p/fracsnetpps/>

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

/* Some quick notes on the new code:
    If a player leaves in the middle of the game they can no longer rating evade
    meaning they will still get -/+ rating when the map changes.
*/

/*//////////////////////////////////////////////////////////////////////////////////////////////////////////////// */
class base_stats{
/*//////////////////////////////////////////////////////////////////////////////////////////////////////////////// */
    
    public $T; /* Teams */
    public $pc; /* Player count, NOTE: unlike T->pc this does not change if the player leaves in the middle of game */
    var $db;
    var $bots;
    var $server;
    private $map_timer;
    private $server_tier;
    private $stmt_player;
    private $player_index; /* By name index of players */
    
    private $leavers; /* Players that left before the map finished */
    private $left_count;

    public $limit; //Cap limit of the game
    
	/* ---------------------------------------------------------------------------------------------------------------------- */
    public function __construct( &$database, &$server, $refresh = null ){
	/* ---------------------------------------------------------------------------------------------------------------------- */    
        $this->server_tier = 1;

        /* Varriable Refresh array could be used to set stats */
        /* automatically upon creation of the array              */
        if( isset( $database ) ){

            $this->bots = false;
            $this->db =& $database;
            $this->server = & $server;
            
            $this->leavers = array();
            $this->left_count = 0;
            /* Teams */
            $this->T = new teams_container($this->sock);

            $this->map_timer = time();
            
            $this->pc = 0;
            if( isset( $refresh ) ){
                $this->limit = $refresh['limit'];

                if( $refresh['players'] ){
                    $this->server->private_message( 0, "Stats script connected" );

                    for( $i = 0; $i < $refresh['players']; $i++ ){                        
                        $name = $refresh['player'][$i]['name'];
                        $id = $refresh['player'][$i]['id'];
                        $team = $refresh['player'][$i]['team'];
                        $hwid = $refresh['player'][$i]['hwid'];
                        $player = null;

                        $record = $this->db->get_player( $hwid );

                        if( $record ) {
                            $player = new base_player( $record, $name ); 
                            $this->server->private_message( $id, "$player->acc_name your stats are now being tracked." );
                        }
                        else {
                            $player = $this->new_player( $hwid, $name );
                            $this->server->private_message( $id, "Hello, $name. This name has been registered as a new account.");
                            $this->server->private_message( $id, "Secret code: $player->code" );
                        }

                        $this->T->add( $name, $team, $id, $player );
                        
                        $this->pc++;
                    }
                }

                $this->server->send( "/ppson" );
            }
        }
    }

    public function new_player( $hwid, $name )
    {
        $player = null;
        $this->db->create_new_player_record( $hwid, $name );
        $record = $this->db->get_player( $hwid );
        $player = new base_player( $record, $name );
        return $player;
    }

/* ++++ Ranking functions ++++ */
/* --------------------------------------------------------------------------------------------------------------------------- */    
    private function v($val, $e = 0){ /* 'v' function a simple knock off the one shown in TrueSkill(tm) documentation
/* --------------------------------------------------------------------------------------------------------------------------- */
        if( $val > 0 )
            return (pow(1.2, -($val) * 6));
        else
            return (pow(2, -($val) * 0.25));
    }
/* ---------------------------------------------------------------------------------------------------------------------------- */
    function w($val, $e = 0){ /* 'w' function knock off */
/* ---------------------------------------------------------------------------------------------------------------------------- */
        if( $val == 0 )
            return 0.8;
        if( $val < 0 )
            return -( 1 / pow(1.5, -$val) );    
        
        return ( 1 / pow(1.5, $val * 2) );
    }
	/* ---------------------------------------------------------------------------------------------------------------------------- */
    function c_squared($sigma_w, $sigma_l){ /* Return the value of c squared */
	/* ---------------------------------------------------------------------------------------------------------------------------- */
        return 2 + ($sigma_w * $sigma_w) + ($sigma_l * $sigma_l);
    }
    
	/* ---------------------------------------------------------------------------------------------------------------------------- */
    function newSigma($sigma, $c, $Mu_w, $Mu_l){ /* Get the new sigma for a player */
	/* ---------------------------------------------------------------------------------------------------------------------------- */
        return sqrt(($sigma * $sigma) * (1 - ((($sigma*$sigma)/$c) * $this->w( ($Mu_w - $Mu_l)/sqrt($c) )) ));
    }
    
	/* -------------------------------------------------------------------------------------------------------------------- */
    function newMu_w($sigma, $c, $Mu_w, $Mu_l){ /* Get the new Mu for a player(Winner) */
	/* -------------------------------------------------------------------------------------------------------------------- */
        return $Mu_w + ( (($sigma*$sigma)/sqrt($c)) * $this->v( ($Mu_w - $Mu_l)/sqrt($c) ) );
    }
    
	/* ---------------------------------------------------------------------------------------------------------------------- */
    function newMu_l($sigma, $c, $Mu_w, $Mu_l){ /* Get the new Mu for a player(Looser) */
	/* ----------------------------------------------------------------------------------------------------------------------- */
        return $Mu_l - ( (($sigma*$sigma)/sqrt($c)) * $this->v( ($Mu_w - $Mu_l)/sqrt($c) ) );
    }
       
	/* ---------------------------------------------------------------------------------------------------------------------- */
    public function ch_join( $line ){
	/* ---------------------------------------------------------------------------------------------------------------------- */
        list( $cmd, $hwid, $id, $team, $name ) = explode( " ", $line, 5 );

        $player = new base_player( null, $name );

        if( !$this->db->is_connected() ) {
            $this->db->connect();
        }

        if( !$this->T->is_playing( $name ) ){
            /* Add a new player */
            $record = $this->db->get_player( $hwid );
            
            if( $record ){ 
                $player = new base_player( $record, $name );
                $this->server->private_message( $id, "Welcome back $player->acc_name" );
            } else {
                //Not recognized player
                $player = $this->new_player( $hwid, $name );
                
                $this->server->private_message( $id ,"Welcome $name! You'r HWID has been registered." );
                $this->server->private_message( $id ,"Your secret account code is $player->code" );
                $this->server->private_message( $id ,"Use it to connect your account with your qnet auth!" );
            }
        }
        else {
            $player = $this->T->get_player_with_name( $name );
            if( $player->hwid != $hwid ) {
                $player = $this->new_player( $hwid, $name );
                
                $this->server->private_message( $id ,"Welcome $name! You'r HWID has been registered." );
                $this->server->private_message( $id ,"Your secret account code is $player->code" );
                $this->server->private_message( $id ,"Use it to connect your account with your qnet auth!" );
            }
        }

        $player->hwid = $hwid;
        $this->T->add( $name, $team, $id, $player );
        
        if( count( $this->leavers ) ){
            if( array_key_exists( $name , $this->leavers ) ){
                unset($this->leavers[$name]);
            }elseif( $this->T->pc > 6 ){
                foreach( $this->leavers as $key ){
                    $this->T->remove($key);
                    unset($this->leavers[$key]);
                }
                $this->leavers = array();
            }
        }
    }

	/* ------------------------------------------------------------------------------------------------------------------- */
    public function ch_left( $line )
	/* ------------------------------------------------------------------------------------------------------------------- */
    {
        list( $cmd, $id, $team, $name ) = explode( " ", $line, 4 );
        
        $leave_time = time();
        $p_id = $this->T->ps[$name]->p_id;

        foreach( $this->T->ps as $key => $plr )
            $this->T->ps[$key]->dominated[$p_id] = 0;
        
        /* Change this so that players are no longer automatically removed from 
           the array and their stats updated. Instead, place them into a $leavers
           array, where they remain untill the next map, or they rejoin the server(0% done) */
        if( $this->T->ps[$name]->team->number == 5 ){
            $this->T->remove($name);
        }else{
            if( ($leave_time - $this->T->ps[$name]->map_timer) > 180 ){
                /* Simply dont remove players that played over 3 minutes */
                $this->leavers[$name] = $name;
            }else{
                $this->T->remove($name);
            }
        }

        if( $this->T->player_count() == 0 ) {
            $this->db->disconnect();
        }

    }

	/* ---------------------------------------------------------------------------------------------------------------------- */
    public function ch_kill( $line ){/* Returns false on ahem, false positive.. */
	/* ---------------------------------------------------------------------------------------------------------------------- */
        //list( $cmd, $kn, $vn, $weapon ) = explode( " ", $line, 4 );
        $loc = ( strrpos($line, "with") ) + 5;         
        $weapon = substr( $line, $loc );
        $kn = substr( $line, 4, strpos($line, " killed") - 4 );
        $vn = substr( $line, strpos($line, " killed (") + 12, strrpos($line, " with") - (strpos($line, " killed (") + 12));

        $killer = &$this->T->get_player_with_name( $kn );//&$this->T->ps[$kn];
        $victim = &$this->T->get_player_with_name( $vn );//&$this->T->ps[$vn];

        if( !$killer || !$victim ) {
            //echo "Error killer/victim is not present in system!\n";
            return false;
        }
        
        if( $kn == $vn ) {
            $killer->d_buffer++;
            $killer->w[$weapon]++;
            $killer->kill_timer = time();
            return;
        }

        $killer->update_kills($weapon);

        $victim->d_buffer++;
        
        if( $killer->is_rated ){
            $kill = time();
            $speed = ( $killer->kill_count > 1 ) ? ($killer->kill_count + 1.02) : (2.02);
            if( $kill - $killer->kill_timer < $speed ){
                $killer->kill_count++;
            }else{
                $killer->mlt[$killer->kill_count]++;
                $killer->kill_count = 0;
            }
            
            $killer->kill_timer = $kill;
        }
        
        $killer->dominated[$victim->p_id]++;
        $victim->dominated[$killer->p_id] = 0;
        
        if( $killer->dominated[$victim->p_id] > 4 ){
            $k = ( $killer->acc_name ) ? $killer->acc_name : $killer->name;
            $d = ( $victim->acc_name ) ? $victim->acc_name : $victim->name;
            $domn = null;
            switch( $killer->dominated[$victim->p_id] )
            {
                case 5:
                    $domn = "/say $k is harrassing $d...";
                    $killer->dominations++;
                    break;
                case 7:
                    $domn = "/say $k is destroying $d!";
                    $killer->dominations++;
                    break;
                case 9:
                    $domn = "/say $k is DOMINATING $d!!";
                    $killer->dominations++;
                    break;
                case 11:
                    $domn = "/say $k is beating $d like he owes him money..!\r\n";
                    $killer->dominations++;
                    break;
            }
            if( $domn ) {
                $this->server->send( $domn );
            }
       }
    }
    
	/* ---------------------------------------------------------------------------------------------------------------------- */
    public function ch_grab( $line ){ //Format: PGRAB<name>
	/* ---------------------------------------------------------------------------------------------------------------------- */
        $grab = substr( $line, 5 );
        $this->T->ps[$grab]->g_buffer++;
    }

	/* ---------------------------------------------------------------------------------------------------------------------- */
    public function ch_cap( $line ) { //Format: PCAPF<name>
	/* ---------------------------------------------------------------------------------------------------------------------- */
        $name = substr( $line, 5 );
        $this->T->ps[$name]->team->score++; 
        $cteam = $this->T->ps[$name]->team->number;
        
        foreach( $this->T->ps as $key => $player ){
            if( $this->T->ps[$key]->team->number == $cteam )
                $this->T->ps[$key]->plus_minus++;
            else
                $this->T->ps[$key]->plus_minus--;
        }
                
        $this->T->ps[$name]->c_buffer++;
    }

	/* ---------------------------------------------------------------------------------------------------------------------- */
    public function ch_return( $line ){ //Format: PRETF<name>
	/* ---------------------------------------------------------------------------------------------------------------------- */
        $name = substr( $line, 5 );
        $this->T->ps[$name]->r_buffer++;
    }

	/* ---------------------------------------------------------------------------------------------------------------------- */
    public function ch_nextmap( $line ){ /* NOTE: Fromat this function better */
	/* ---------------------------------------------------------------------------------------------------------------------- */
        $winner = 0;
        $final_time = time();
        $full_map = false;
        if( $final_time - $this->map_timer < 300 ){
            $this->map_timer = $final_time;
        }else{
            $full_map = true;
            $this->map_timer = $final_time;
        }
        
        if( $this->T->alpha->score != $this->T->bravo->score ){
            if( $this->T->alpha->score > $this->T->bravo->score ){
                $winner = 1;
            }else{
                $winner = 2;
            }
        }
        $this->T->alpha->score = 0;
        $this->T->bravo->score = 0;  
        
        foreach( $this->T->ps as $key => $player ){
            $this->T->ps[$key]->dominated = array_fill(0, 33, 0);
        }
        
        /* Everything above this line is necessary after each map */     

        if( false ) { //$this->T->pc < 6 || !$full_map){
            if( count($this->leavers) ){
                foreach( $this->leavers as $key ){
                    $this->T->remove($key);
                    unset($this->leavers[$key]);
                }
                $this->leavers = array();
            }
            
            return;
        }
        
        $this->update_ratings($winner);
                
        /* Update Rated Players */
        foreach( $this->T->ps as $name => $player ){
            $this->T->ps[$name]->merge_buffers();
            if( $winner ){
                $this->T->ps[$name]->maps++;
            }
            
            /* If the player played more than 5 minutes, update their stats with the buffers */
            if( time() - $this->T->ps[$name]->map_timer > 240 ){
                if( $winner ){
                    if( $this->T->ps[$name]->team->number == $winner ){
                        $this->T->ps[$name]->wins++;
                    }
                }
                
                $this->T->ps[$name]->merge_buffers();
                if( $this->T->ps[$name]->is_rated ){
                    $this->update_player_stats($this->T->ps[$name]);
                }
            }

            $this->T->ps[$name]->map_timer = $final_time;
            $this->T->ps[$name]->clear_buffers();
        }   

        //Clear leaver array 
        if( count($this->leavers) ){
            foreach( $this->leavers as $key ){
                $this->T->remove( $key );
                unset( $this->leavers[$key] );
            }
            $this->leavers = array();

            if( $this->T->player_count() == 0 ) {
                $this->db->disconnect();
            }
        }

        return $winner;
    }

	/* ------------------------------------------------------------------------------------------------------------------------------------------------------------------- */
   	private function update_ratings($win_number){
	/* ------------------------------------------------------------------------------------------------------------------------------------------------------------------- */       
        if( $win_number < 3 ){
            if( $win_number == 1 ){
                $win = &$this->T->alpha;
                $loose = &$this->T->bravo;
            }elseif( $win_number == 2){
                $win = &$this->T->bravo;
                $loose = &$this->T->alpha;
            }elseif( !$win_number ){
                return;
            }
        }else{
            return;
        }
        if( (!$win->mu || !$loose->mu) ){
            return null;
        }
        
        $L = array();
        $l_sigma = $loose->sigma;
        $l_mu = $loose->mu;
        
        $W = array();
        $w_sigma = $win->sigma;
        $w_mu = $win->mu;
        
        $l_pc = $loose->count;
        $w_pc = $win->count;

        if( $w_pc === 0 || $l_pc === 0 ) return;

        /* Generate temporary teams */
        foreach( $this->T->ps as $name => $player ){
            if( $this->T->ps[$name]->is_rated ){
                if( $this->T->ps[$name]->team->number == $win->number){
                    $W[$name] = $this->T->ps[$name];
                }elseif( $this->T->ps[$name]->team->number == $loose->number ){
                    $L[$name] = $this->T->ps[$name];
                }
            }
        }

        foreach( $W as $name => $value ){
            
            $c = $this->c_squared( $W[$name]->sigma, ($l_sigma/$l_pc) );
            $sigma = 0;
            
            $sigma = $this->newSigma( $W[$name]->sigma,
                                      $c,
                                      $W[$name]->mu,
                                      ($l_mu/$l_pc) );
                                                          
            $W[$name]->mu = $this->newMu_w( $sigma,
                                            $c,
                                            $W[$name]->mu,
                                            ($l_mu/$l_pc) );
            
            $W[$name]->sigma = $sigma;
            $W[$name]->old_rating = $W[$name]->rating;
            $W[$name]->rating = $W[$name]->mu - ( $W[$name]->sigma * 2.5);
            
            $this->T->ps[$name] = $W[$name];
        }
        foreach( $L as $name => $value ){

            $c = $this->c_squared( ($w_sigma/$w_pc), $L[$name]->sigma );
            $sigma = 0;

            $sigma = $this->newSigma( $L[$name]->sigma, 
                                      $c, 
                                      ($w_mu/$w_pc), 
                                      $L[$name]->mu );
                                      
            $L[$name]->mu = $this->newMu_l( $sigma, 
                                            $c, 
                                            ($w_mu/$w_pc), 
                                            $L[$name]->mu );
                                            
            $L[$name]->sigma = $sigma;
            $L[$name]->old_rating = $L[$name]->rating;
            $L[$name]->rating = $L[$name]->mu - ($L[$name]->sigma * 2.5);
            
            $this->T->ps[$name] = $L[$name];
        }           
    }

	/* ---------------------------------------------------------------------------------------------------------------------- */
    public function update_player_stats( &$player )
	/* ---------------------------------------------------------------------------------------------------------------------- */
    {
        /* First Part Update Player specific stats */
        $player->update_kdr();
        $player->update_cgr();
        $player->update_ckr();
        $player->update_time_played();

        $this->db->write_player_stats( $player ); 

        foreach( $player->w as $key => $kills ) {
            $this->db->update_weapon_stats( $key, $kills );
            $player->w[$key] = 0;
        }
    }
}
