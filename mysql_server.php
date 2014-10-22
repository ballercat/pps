<?php
/*
This file 'mysql_server.php' is part of the PPS project <http://code.google.com/p/fracsnetpps/>

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

define( 'SERVER_TYPE_MYSQL', 3 );

class mysql_server extends ppsserver {
    public $type = SERVER_TYPE_MYSQL;

    public $mysqli;
    public $prep;

    private $user;
    private $db_name;

    public function __construct( $ip, $user, $pass, $db_name  ) {
        $this->ip       = $ip;
        $this->user     = $user;
        $this->pass     = $pass;
        $this->db_name  = $db_name;
    }

    public function __destruct() 
    {
        if( $this->mysqli ) {
            $this->mysqli->query( "UPDATE status SET stats_on=0 WHERE stats_version='v0.4b'" );
            $this->mysqli->close();
            $this->mysqli = null;
        }
    }

    public function disconnect() 
    {
        if( $this->mysqli ) {

            if( $this->prep ) 
                $this->prep->reset();

            $this->mysqli->close();
            $this->mysqli = null;
            $this->connected = false;
        } 
    }

    public function connect( $prep = true ) 
    {
        if( $this->mysqli ) {

            $this->mysqli->close();
            $this->mysqli = null;
        }

        $this->mysqli = new mysqli( $this->ip, $this->user, $this->pass, $this->db_name );
        if( $this->mysqli->connect_errno ) {

            echo '--> Connect error ' . $this->mysqli->connect_error . "\n";
        }

        if( $prep ) {

            $this->prep();
        }

        $this->connected = true;
    }

    public function prep()
    {
        $this->prep = $this->mysqli->stmt_init();
        
        $prepare_str  = "UPDATE players SET kills=?,";
        $prepare_str .= "deaths=?,";
        $prepare_str .= "doubles=?,";
        $prepare_str .= "triples=?,";
        $prepare_str .= "multi=?,";
        $prepare_str .= "caps=?,";
        $prepare_str .= "grabs=?,";
        $prepare_str .= "returns=?,";
        $prepare_str .= "kd=?,";
        $prepare_str .= "cg=?,";
        $prepare_str .= "ck=?,";
        $prepare_str .= "rating=?,";
        $prepare_str .= "wins=?,";
        $prepare_str .= "maps=?,";
        $prepare_str .= "time_played=?,";
        $prepare_str .= "lastplayed=NOW(),";
        $prepare_str .= "`Desert Eagles`=`Desert Eagles`+?,";
        $prepare_str .= "`HK MP5`=`HK MP5`+?,";
        $prepare_str .= "`Ak-74`=`Ak-74`+?,";
        $prepare_str .= "`Steyr AUG`=`Steyr AUG`+?,";
        $prepare_str .= "`Spas-12`=`Spas-12`+?,";
        $prepare_str .= "`Ruger 77`=`Ruger 77`+?,";
        $prepare_str .= "`M79`=`M79`+?,";
        $prepare_str .= "`Barrett M82A1`=`Barrett M82A1`+?,";
        $prepare_str .= "`FN Minimi`=`FN Minimi`+?,";
        $prepare_str .= "`Selfkill`=`Selfkill`+?,";
        $prepare_str .= "`Combat Knife`=`Combat Knife`+?,";
        $prepare_str .= "`Chainsaw`=`Chainsaw`+?,";
        $prepare_str .= "`LAW`=`LAW`+?,";
        $prepare_str .= "`Grenade`=`Grenade`+?,";
        $prepare_str .= "`Hands`=`Hands`+?,";
        $prepare_str .= "streak=?,";
        $prepare_str .= "plusminus=?,";                
        $prepare_str .= "mu=?,";
        $prepare_str .= "sigma=?,";
        $prepare_str .= "dominations=?,";
        $prepare_str .= "old_rating=?";
        $prepare_str .= " WHERE hwid=?";
        
        $this->prep->prepare($prepare_str) or die("COULD NOT PREP");
    }

    public function set_auth( $code, $auth )
    {
        return $this->mysqli->query( "UPDATE players SET auth=\"$auth\" WHERE code=\"$code\"" );
    }

    public function get_account_users( $auth, $code ) 
    {

        $accounts = null;

        if( !$code ) return null;
        //The user must have some real data to be able to access this info
        $result = $this->mysqli->query( "SELECT * FROM players WHERE code=\"$code\"" );
        if( $result->num_rows < 1 ) return null;

        $accounts = array( $result->fetch_array(MYSQLI_ASSOC) );

        $result = $this->mysqli->query( "SELECT * FROM players WHERE auth=\"$auth\"" );
        $record = $result->fetch_array( MYSQLI_ASSOC );

        while( $record ) {

            $accounts[] = $record;
            $record = $result->fetch_array( MYSQLI_ASSOC );
        }

        return $accounts;
    }

    public function merge_account_users( $saved_code , $auth )
    {
        $accounts = $this->get_account_users( $auth, $saved_code );

        if( !$accounts ) {
            return "No accounts found for code: $code";
        }

        $highlander = null; 
        foreach( $accounts as $player ) {

            if( $player['code'] == $saved_code ) {

                $highlander = new base_player( $player, $player['acc_name'] );
                break;
            }
        }

        if( !$highlander ) return "Merge failed";

        $text = "Merged accounts: ";
        foreach( $accounts as $user_data ) {
            
            $text .= $user_data['name'] . "(" . $user_data['user_id'] . ") ";
            if( $user_data['code'] == $saved_code ) continue;
            $player = new base_player( $user_data );

            $highlander->k += $player->k;
            $highlander->d += $player->d;
            $highlander->mlt[1] += $player->mlt[1];
            $highlander->mlt[2] += $player->mlt[2];
            $highlander->mlt[3] += $player->mlt[3];

            $highlander->c += $player->c;
            $highlander->g += $player->g;
            $highlander->r += $player->r;

            $highlander->w['Desert Eagles'] += $user_data['Desert Eagles']; 
            $highlander->w['HK MP5'] += $user_data['HK MP5']; 
            $highlander->w['Ak-74'] += $user_data['Ak-74'];
            $highlander->w['Steyr AUG'] += $user_data['Steyr AUG'];
            $highlander->w['Spas-12'] += $user_data['Spas-12'];
            $highlander->w['Ruger 77'] += $user_data['Ruger 77'];
            $highlander->w['M79'] += $user_data['M79'];
            $highlander->w['Barrett M82A1'] += $user_data['Barrett M82A1'];
            $highlander->w['FN Minimi'] += $user_data['FN Minimi'];
            $highlander->w['Selfkill'] += $user_data['Selfkill'];
            $highlander->w['Combat Knife'] += $user_data['Combat Knife'];
            $highlander->w['Chainsaw'] += $user_data['Chainsaw'];
            $highlander->w['LAW'] += $user_data['LAW'];
            $highlander->w['Grenade'] += $user_data['Grenade'];
            $highlander->w['Hands'] += $user_data['Hands'];

            $highlander->plus_minus += $player->plus_minus;
            $highlander->mu += $player->mu;
            $highlander->sigma += $player->sigma;
            $highlander->dominations += $player->dominations;
            $highlander->tp += $player->tp;

            $this->mysqli->query( "DELETE from players WHERE code=\"$player->code\"" );
        } 

        $text .= "into " .  $highlander->name;

        $highlander->update_kdr();
        $highlander->update_cgr();
        $highlander->update_ckr();

        $highlander->sigma = $highlander->sigma/count($accounts);
        $highlander->mu = $highlander->mu/count($accounts);
        $highlander->rating = $highlander->mu - ( $highlander->sigma * 2.5 ); 

        $this->write_player_stats( $highlander );

        return $text;
    }

    public function bind_user_auth( $name, $auth, $code ) 
    {
        
        $result = $this->mysqli->query( "SELECT name FROM players WHERE code=\"$code\"" );
        if( $result ) {

            $record = $result->fetch_array( MYSQLI_ASSOC );
            if( count($record ) ) {

                //Run a check for wheter or not the user exists already with a different HWID
                $result = $this->mysqli->query( "SELECT name FROM players WHERE auth=\"$auth\"" );
                if( $result->num_rows > 1 ) {
                   return "More than one user bound to account $auth";
                }

                if( $this->set_auth( $code, $auth ) )
                    return "Sucess! User: $name, has been updated with new auth: $auth";
            }
        }
        return "Failed to update auth. user:$name  auth:$auth";
    }

    public function get_auth_stats( $auth )
    {
        $result = $this->mysqli->query( "SELECT * FROM players WHERE auth=\"$auth\"" );
        if( $result ) {
            $record = $result->fetch_array( MYSQLI_ASSOC );
            return $record; 
        }

        return null;
    }

    public function create_new_player_record( $hwid, $name )
    {
        //Generate unique auth code for a player NOTE: should be possible to do with MYSQL
        do {
            $code = strtoupper( bin2hex(openssl_random_pseudo_bytes(3)) );
            $result = $this->mysqli->query( "SELECT user_id FROM players WHERE code=\"$code\"" );
        }while( $result && $result->num_rows );

        //Insert a record into players table
        $this->mysqli->query( "INSERT INTO players(name, hwid, code) VALUES(\"$name\", \"$hwid\", \"$code\")" );

        //Insert a record into accuracy table
        $this->mysqli->query( "INSERT INTO accuracy(user_id) VALUES(".$this->mysqli->insert_id.")" );

        return $code;
    }

    public function get_player( $hwid )
    {
        $result = $this->mysqli->query( "SELECT * FROM players WHERE hwid=\"$hwid\"" );
        if( $result ) {
            return $result->fetch_array( MYSQLI_ASSOC );
        }
        
        return false;
    }

    public function write_player_stats( &$player )
    {
       
        if( !$this->connected ) $this->connect();

        $this->prep->bind_param('iiiiiiiiddddiiiiiiiiiiiiiiiiiiiiddids', 
                                        $player->k,
                                        $player->d,
                                        $player->mlt[1],
                                        $player->mlt[2],
                                        $player->mlt[3],
                                        $player->c,
                                        $player->g,
                                        $player->r,
                                        $player->kdr,
                                        $player->cgr,
                                        $player->ckr,
                                        $player->rating,
                                        $player->wins,
                                        $player->maps,
                                        $player->tp,
                                        $player->w['Desert Eagles'], 
                                        $player->w['HK MP5'], 
                                        $player->w['Ak-74'],
                                        $player->w['Steyr AUG'],
                                        $player->w['Spas-12'],
                                        $player->w['Ruger 77'],
                                        $player->w['M79'],
                                        $player->w['Barrett M82A1'],
                                        $player->w['FN Minimi'],
                                        $player->w['Selfkill'],
                                        $player->w['Combat Knife'],
                                        $player->w['Chainsaw'],
                                        $player->w['LAW'],
                                        $player->w['Grenade'],
                                        $player->w['Hands'],
                                        $player->wstreak,
                                        $player->plus_minus,
                                        $player->mu,
                                        $player->sigma,
                                        $player->dominations,
                                        $player->old_rating,
                                        $player->hwid);
                                                                
        $this->prep->execute() or die("Could Not Execute Prepared Statement\n");
    }

    public function update_weapon_stats( $weapon, $kills )
    {
        $this->mysqli->query( "UPDATE weapons SET kills=kills+$kills WHERE weapon=\"$weapon\"" );
    }

    public function get_player_rank( $name, $user_id = null, $code = null, $auth = null, $hwid = null )
    {
        echo "USERID: $user_id\n";
        $token = 'name';
        $value = $name;
        if( $user_id != null ) {
            $token = 'user_id';
            $value = $user_id;
        } 
        else if( $code != null ) {
            $token = 'code';
            $value = $code;
        }
        else if( $auth != null ) {
            $token = 'auth';
            $value = $auth;
        }
        else if( $hwid != null ) {
            $token = 'hwid';
            $value = $hwid;
        }

        //query
        $this->mysqli->query( "SET @rownum = 0;" );
        $this->mysqli->query( "SET @total = 0;" );
        $query = "SELECT $token, rating,rank, @total as total FROM(";
        $query .= "SELECT players.$token, players.rating,@rownum := @rownum + 1 as rank,@total := @total + 1 ";
        $query .= "FROM players ORDER BY rating DESC)`selection` ";
        $query .= "WHERE $token = '$value';";

        $result = $this->mysqli->query( $query );
        
        $record = false;
        if( $result ) {
            //array( 'token' => 'value', 'rank' => %i, 'rating' => %d, 'total' => %d );
            $record = $result->fetch_array( MYSQLI_ASSOC );
        } 

        return $record;
    }

    public function give_points( $user_id, $points, $type ) 
    {
        $result = $this->mysqli->query( "SELECT * FROM points WHERE user_id=$user_id AND type=\"$type\"" );
        if( $result->num_rows > 0 ) {

            $this->mysqli->query( "UPDATE points SET points=points+$points WHERE user_id=$user_id AND type=\"$type\"" );
            return true;
        }
        else {

            $this->mysqli->query( "INSERT INTO points(user_id,points,type,issued) VALUES($user_id,$points,\"$type\",NOW())" ); 
            return true;
        }

        return false;
    }

    public function get_points( $user_id ) //gets all the points
    {

        $result = $this->mysqli->query( "SELECT * FROM points WHERE user_id=$user_id" );
        if( $result->num_rows < 1 ) return null;

        $record = $result->fetch_array( MYSQLI_ASSOC );
        $points = array();
        while( $record ) {

            $points[] = $record;
            $record = $result->fetch_array( MYSQLI_ASSOC );
        }

        return $points;
    }

    public function erase_points( $user_id, $type ) 
    {
        return $this->mysqli->query( "DELETE FROM points WHERE user_id=$user_id AND type=\"$type\"" );
    }
}

?>

