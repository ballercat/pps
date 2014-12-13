<?php
/*
This file is part of the PPS project <https://github.com/ballercat/pps>

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

Trait actions {

    public function get_account_users( $auth, $code )
    {
        if( $this->gather_mode ) $this->database->connect( false );
        $result = $this->database->get_account_users( $auth, $code );
        if( $this->gather_mode ) $this->database->disconnect();
        return $result;
    }

    public function bind_user_auth( $name, $auth, $code ) 
    {
        if( $this->gather_mode ) $this->database->connect( false );
        echo "Bind user auth: $name $auth $code\n";
        $result = $this->database->bind_user_auth( $name, $auth, $code );
        if( $this->gather_mode ) $this->database->disconnect();
        return $result; 
    }

    public function get_auth_stats_string( $auth )
    {
        if( $this->gather_mode ) $this->database->connect( false );
        $record = $this->database->get_auth_stats( $auth );
        if( $this->gather_mode ) $this->database->disconnect();

        if( !$record ) return null;

        $name = $record['name'];
        $rating = $record['rating'];
        $KD = $record['kd'];
        $kills = $record['kills'];
        $deaths = $record['deaths'];
        $caps = $record['caps'];
        $CG = $record['cg'];
        $grabs = $record['grabs'];
        $played = $record['time_played'];
        $pm = $record['plusminus'];


        //IRC message string
        return "$name rating: $rating KD: $KD CG: $CG +/-:$pm played(minutes):$played";
    }

    public function get_auth_stats( $auth ) {
        if( $this->gather_mode ) $this->database->connect();
        $result = $this->database->get_auth_stats( $auth );
        if( $this->gather_mode ) $this->database->disconnect();
        return $result;
    }

    public function get_player_rank( $name, $user_id = null, $code = null, $auth = null, $hwid = null )
    {
        if( $this->gather_mode ) $this->database->connect();
        $result = $this->database->get_player_rank( $name, $user_id, $code, $auth, $hwid );
        if( $this->gather_mode ) $this->database->disconnect();
        return $result;
    }

    public function merge_account_users( $code, $auth ) {
        if( $this->gather_mode ) $this->database->connect();
        $result = $this->database->merge_account_users( $code, $auth );
        if( $this->gather_mode ) $this->database->disconnect();
        return $result;
    }       

    public function get_player_points( $user_id  ) 
    {
        if( $this->gather_mode ) $this->database->connect();
        $result = $this->database->get_points( $user_id );
        if( $this->gather_mode ) $this->database->disconnect();
        return $result;
    } 

    public function give_player_points( $user_id, $points, $type, $reason = null, $issuer = null )
    {
        if( $this->gather_mode ) $this->database->connect();
        $result = $this->database->give_points( $user_id, $points, $type, $reason, $issuer );
        if( $this->gather_mode ) $this->database->disconnect();   
        return $result;
    }

    public function erase_player_points( $uer_id, $type )
    {
        if( $this->gather_mode ) $this->database->connect();
        $this->database->erase_points( $user_id, $type );
        if( $this->gather_mode ) $this->database->disconnect();
    }

    public function get_max_gather_id()
    {
        if( $this->gather_mode ) $this->database->connect( false );
        $id = $this->database->get_max_gather_id();
        if( $this->gather_mode ) $this->database->disconnect();
        return $id;
    }

    public function create_gather() 
    {
        if( $this->gather_mode ) $this->database->connect( false );
        $id = $this->database->create_gather();
        if( $this->gather_mode ) $this->database->disconnect();
        return $id;
    }

    public function get_last_gather( $limit = 1, $id = null )
    {
        if( $this->gather_mode ) $this->database->connect( false );
        $result = $this->database->get_last_gather( $limit, $id );
        if( $this->gather_mode ) $this->database->disconnect();

        if( $result ) {
            $gathers = array();
            $gather = $result->fetch_array( MYSQLI_ASSOC );
            while( $gather ) {

                $gathers[$gather['id']] = $gather;
                $gather = $result->fetch_array( MYSQLI_ASSOC );
            }

            return $gathers;
        }

        return false;
    }

    public function find_user_regex( $regex ) 
    {
        if( $this->gather_mode ) $this->database->connect();
        $result = $this->database->get_player_regex( $regex );
        if( $this->gather_mode ) $this->database->disconnect();
        return $result;
    }

    function write_refresh($gather_id, $region, $refresh) 
    {
        if( $this->gather_mode ) $this->database->connect();
        $this->database->write_refresh( $gather_id, $region, $refresh );
        if( $this->gather_mode ) $this->database->disconnect();
    }
}

?>
