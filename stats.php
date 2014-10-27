<?php
/*
This file 'stats.php' is part of the PPS project 

Copyright: (C) 2014 Arthur, B. aka ]{ing <whinemore@gmail.com>

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

Trait stats {
	/* ---------------------------------------------------------------------------------------------------------------------- */
    public function ch_join( $line ){
	/* ---------------------------------------------------------------------------------------------------------------------- */
        echo $line . "\n";

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

        $this->server->private_message( $id, "Stats are currently being tested" );

        $player->hwid = $hwid;
        $this->T->add( $name, $team, $id, $player );

        echo "PC: " . $this->T->player_count() . "\n";
    }

	/* ------------------------------------------------------------------------------------------------------------------- */
    public function ch_left( $line )
	/* ------------------------------------------------------------------------------------------------------------------- */
    {
        echo $line . "\n";
        list( $cmd, $id, $team, $name ) = explode( " ", $line, 4 );
        
        $p_id = $this->T->left( $name );

        foreach( $this->T->ps as $key => $plr ) {

            $this->T->ps[$key]->dominated[$p_id] = 0;
        }

        echo "PC: " . $this->T->player_count() . " \n";

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

        if( $final_time - $this->map_timer < 300 ) {

            $this->map_timer = $final_time;
        }else {

            $full_map = true;
            $this->map_timer = $final_time;
        }
        
        if( $this->T->alpha->score != $this->T->bravo->score ) {

            if( $this->T->alpha->score > $this->T->bravo->score ) {

                $winner = 1;
            }else{

                $winner = 2;
            }
        }

        $this->T->alpha->score = 0;
        $this->T->bravo->score = 0;  
        
        foreach( $this->T->ps as $key => $player ) {

            $this->T->ps[$key]->dominated = array_fill(0, 33, 0);
        }
        
        $this->update_ratings($winner);
                
        /* Update Rated Players */
        foreach( $this->T->ps as $name => $player ) {

            $this->T->ps[$name]->merge_buffers();

            if( $winner ) {

                $this->T->ps[$name]->maps++;
            }
            
            /* If the player played more than 5 minutes, update their stats with the buffers */
            if( $this->T->ps[$name]->full_map( $final_time ) ) {

                if( $winner ){

                    if( $this->T->ps[$name]->team->number == $winner ) {
                        $this->T->ps[$name]->wins++;
                    }
                }
                
                $this->T->ps[$name]->merge_buffers();
                if( $this->T->ps[$name]->is_rated ) {

                    $this->update_player_stats($this->T->ps[$name]);
                }
            }

            $this->T->ps[$name]->map_timer = $final_time;
            $this->T->ps[$name]->clear_buffers();
        }   

        //Clear leavers
        $this->T->clear_leavers(); 

        if( $this->T->player_count() == 0 ) {
            $this->db->disconnect();
        }

        return $winner;
    }
}

?>
