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
require('rank_math.php');
require('rating_update.php');
require('stats.php');

/*//////////////////////////////////////////////////////////////////////////////////////////////////////////////// */
class base_stats{
/*//////////////////////////////////////////////////////////////////////////////////////////////////////////////// */
    Use stats, rank_math, rating_update;

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

}
