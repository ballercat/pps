<?php
Trait irc_server_test {

    function test_gather( $players ) {
        $game_server = $this->pps->request_game_server();
        if( !$game_server ) {
            $this->send( "No available game servers.", $this->chan );
            return;
        }

        $tg = new gather_man( 0, $game_server );

        $names = array( "cat", "dog", "mouse", "duck", "sheep", "wolf" );
        $i = 0;
        foreach( $players as  $rating ) {
            $result = false;
            $name = $names[$i];
            if( $rating ) {
                
                $result = $tg->add_rated( $name, $rating, array('rank'=>$i, 'total'=>100) );
            }
            else {
                $result = $tg->add( $name );
            }

            $this->send( $result, $this->chan );
            $i++;
        }
        if( $i < 5 ) {
            for( $i ; $i < 6; $i++ ) {
                $result = $tg->add( $names[$i] );
                $this->send( $result, $this->chan );
            }
        }

        $this->start_gather( $tg, 0, 60 );
    }
}

?>
