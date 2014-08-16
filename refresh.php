<?php
    function GetSoldatInfo(&$sock) {
        if (!$sock) return false;
        $info = array(
            'gamemode'    => 0,
            'teammode'    => false,
            'pointmode'    => false,
            'players'     => 0,
            'spectators'     => 0,
            'map'        => '',
            'timelimit'    => 0,
            'currenttime'    => 0,
            'timeleft'    => 0,
            'limit'        => 0,
            'player'    => array(),
            'spectator'    => array(),
            'team'        => array()
        );
 
        // Temporary array for players
        $players = array();
        for ($i = 0; $i < 32; $i++) {
            $players[$i] = array(
                'name'         => '',
                'ip'         => '',
                'id'         => 0,
                'kills'        => 0,
                'deaths'    => 0,
                'team'        => 0,
                'ping'        => 0
            );
        }
 
        // Get player names
        for ($i = 0; $i < 32; $i++) {
            $data = fread($sock, 25);
            $len = ord($data[0]);
            $players[$i]['name'] = substr($data, 1, $len);
        }
 
        // Get player teams
        for ($i = 0; $i < 32; $i++) {
            $data = fread($sock, 1);
            $players[$i]['team'] = ord($data);
        }
 
        // Get player kills
        for ($i = 0; $i < 32; $i++) {
            $data = unpack("v", fread($sock, 2));
            $kills = $data[1];
            $players[$i]['kills'] = $kills;
        }
 
        // Get player deaths
        for ($i = 0; $i < 32; $i++) {
            $data = unpack("v", fread($sock, 2));
            $deaths = $data[1];
            $players[$i]['deaths'] = $deaths;
        }
 
        // Get player pings
        for ($i = 0; $i < 32; $i++) {
            $data = fread($sock, 1);
            $players[$i]['ping'] = ord($data);
        }
 
        // Get player IDs
        for ($i = 0; $i < 32; $i++) {
            $data = fread($sock, 1);
            $players[$i]['id'] = ord($data);
        }
 
        // Get player IPs
        for ($i = 0; $i < 32; $i++) {
            $data = unpack("N", fread($sock, 4));
            $players[$i]['ip'] = long2ip($data[1]);
        }
 
        // Get team scores
        for ($i = 1; $i < 5; $i++) {
            $data = unpack("v", fread($sock, 2));
            $score = $data[1];
            $info['team'][$i] = $score;
        }
 
        // Get map name
        $data = fread($sock, 17);
        $len = ord($data[0]);
        $info['map'] = substr($data, 1, $len);
 
        // Get time limit & current time, and form timeleft
        $data = unpack("V", fread($sock, 4));
        $timelimit = $data[1];
        $info['timelimit'] = $timelimit;
        $data = unpack("V", fread($sock, 4));
        $currenttime = $data[1];
        $info['currenttime'] = $timelimit - $currenttime;
 
        $timeleft = $currenttime;
        $temp = (floor($timeleft / 60) % 60);
        $info['timeleft'] = floor($timeleft / 3600) . ':' . ($temp < 10 ? '0' : '') . $temp;
 
        // Get kill limit
        $data = unpack("v", fread($sock, 2));
        $limit = $data[1];
        $info['limit'] = $limit;
 
        // Get gamestyle
        $data = fread($sock, 1);
        $gamestyle = ord($data);
        $info['gamemode'] = $gamestyle;
        if ($gamestyle == 2 || $gamestyle == 3 || $gamestyle == 5 || $gamestyle == 6) {
            $info['teammode'] = true;
            if ($gamestyle != 2) { $info['pointmode'] = true; }
        }
        if ($gamestyle != 2) {
            if ($gamestyle != 3 && $gamestyle != 5) {
                unset($info['team'][1]);
                unset($info['team'][2]);
            }
            unset($info['team'][3]);
            unset($info['team'][4]);
        }
 
        foreach ($players as $player) {
            if ($player['team'] < 5) {
                $info['players']++;
                $info['player'][] = $player;
            }
            else if ($player['team'] == 5) {
                $info['spectators']++;
                $info['spectator'][] = $player;
            }
        }
 
        return $info;
    }

define('REFRESH_PACKET_SIZE',  1188);
 
function RefreshXSize($version = '2.6.5')
{
        if ($version >= '2.7.0') {
                    return 1992;
                        }
            else if ($version >= '2.6.5') {
                        return 1608;
                            }
            else {
                        return 1576;
                            }
}
 
function ParseRefresh(&$packet, $version = '2.6.5') 
{
    if (strlen($packet) == REFRESH_PACKET_SIZE) {
        $refreshx = false;
    } else if (strlen($packet) == RefreshXSize($version)) {
        $refreshx = true;
    }
    else {
        return false;
    }
     
    $info = array(
            'gamemode'    => 0,
            'players'     => 0,
            'spectators'  => 0,
            'map'         => '',
            'timelimit'   => 0,
            'currenttime' => 0,
            'timeleft'    => 0,
            'limit'       => 0,
            'player'      => array(),
            'spectator'   => array(),
            'team'        => array()
    );
     
    if ($refreshx) {
        $info = array_merge($info, array(
            'maxplayers' => 0,
            'maxspecs'   => 0,
            'nextmap'    => '',
            'passworded' => false,
            'redflag'    => array(),
            'blueflag'   => array()
        ));
    }
     
    $players = array();
    for ($i = 0; $i < 32; $i++) {
        $players[$i] = array(
            'name'   => '',
            'ip'     => '',
            'id'     => 0,
            'kills'  => 0,
            'caps'   => 0,
            'deaths' => 0,
            'team'   => 0, 
            'ping'   => 0
        );
                 
        if ($refreshx) {
            $players[$i] = array_merge($players[$i], array(
                'x' => 0,
                'y' => 0
            ));
            if ($version >= '2.7.0') {
                $players[$i] = array_merge($players[$i], array('hwid' => ''));
            }
        }
    }
         
    if ($refreshx && $version >= '2.7.0') {
            /* 
             *         384 = (11 + 1 byte per hwid) * 32 players
             *                 1184 = 800 + 384
             *                         */
        $pos = 1184;
    }
    else {
             /*
              *         800 = (24 + 1 byte per name) * 32 players
              *                 */
        $pos = 800;
    }

    $teams                            = unpack('C*', substr($packet, $pos, 32));  $pos += 32;
    $kills                            = unpack('v*', substr($packet, $pos, 64));  $pos += 64;
    if ($version >= '2.6.5') {  $caps = unpack('C*', substr($packet, $pos, 32));  $pos += 32;  }
    $deaths                           = unpack('v*', substr($packet, $pos, 64));  $pos += 64;
    if (!$refreshx)          { $pings = unpack('C*', substr($packet, $pos, 32));  $pos += 32;  }
    else                     { $pings = unpack('l*', substr($packet, $pos, 128)); $pos += 128; }
    $ids                              = unpack('C*', substr($packet, $pos, 32));  $pos += 32;
    $ips                              = unpack('N*', substr($packet, $pos, 128)); $pos += 128;
    if ($refreshx)           { $locs  = unpack('f*', substr($packet, $pos, 256)); $pos += 256; }
                 
    for ($i = 0; $i < 32; $i++) {
        $players[$i]['name']   = substr($packet, $i*25+1, ord($packet[$i*25]));
        $players[$i]['team']   = $teams[$i+1];
        $players[$i]['kills']  = $kills[$i+1];
        $players[$i]['caps']   = $caps[$i+1];
        $players[$i]['deaths'] = $deaths[$i+1];
        $players[$i]['ping']   = $pings[$i+1];
        $players[$i]['id']     = $ids[$i+1];
        $players[$i]['ip']     = long2ip($ips[$i+1]);

        if ($refreshx) {
            $players[$i]['x'] = $locs[$i+1];
            $players[$i]['y'] = $locs[$i+33];
            if ($version >= '2.7.0') {
                $players[$i]['hwid'] = substr($packet, $i*12+801, 11);
            }
        }
    }

    if ($refreshx) {
        $data = unpack('f*', substr($packet, $pos, 16)); $pos += 16;
        $info['redflag']  = array('x' => $data[1], 'y' => $data[2]);
        $info['blueflag'] = array('x' => $data[3], 'y' => $data[4]);
    }
                 
    $teams = unpack('v*', substr($packet, $pos, 8));            $pos += 8;
    $map   = unpack('Clen/A16name', substr($packet, $pos, 17)); $pos += 17;
    $time  = unpack('V*', substr($packet, $pos, 8));            $pos += 8;
    $limit = unpack('v', substr($packet, $pos, 2));                 $pos += 2;
                         
    $timelimit = $time[1];
    $currenttime = $time[2];
    $timeleft = $currenttime;
    $temp = (floor($timeleft / 60) % 60);
    $info['timeleft'] = floor($timeleft / 3600) . ':' . ($temp < 10 ? '0' : '') . $temp;
                                 
    $info['team'] = $teams;
    $info['map'] = substr($map['name'], 0, $map['len']);
    $info['timelimit'] = $timelimit;
    $info['currenttime'] = $timelimit - $currenttime;
    $info['limit'] = $limit[1];
    $info['gamemode'] = ord($packet[$pos++]);
 
    if ($refreshx) {
        $data = unpack('C*', substr($packet, $pos, 4)); $pos += 4;
        $info['maxplayers'] = $data[1];
        $info['maxspecs']   = $data[2];
        $info['passworded'] = ($data[3] != 0 ? true : false);
        $info['nextmap']    = substr($packet, $pos, $data[4]);
    }
                                                 
    if ($info['gamemode'] != 2) {
        if ($info['gamemode'] != 3 && $info['gamemode'] != 5) {
            unset($info['team'][1]);
            unset($info['team'][2]);
        }
        unset($info['team'][3]);
        unset($info['team'][4]);
    }
                                                 
    foreach ($players as $player) {
        if ($player['team'] < 5) {
            $info['players']++;
            $info['player'][] = $player;
        }
        else if ($player['team'] == 5) {
            $info['spectators']++;
            $info['spectator'][] = $player;
        }
    }
 
    return $info;
}

?>
