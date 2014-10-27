<?php
Trait irc_server_test {

    function test_gather( $players ) {
        /*$game_server = $this->pps->request_game_server();
        if( !$game_server ) {
            $this->send( "No available game servers.", $this->chan );
            return;
        }*/

        //$tg = new gather_man( 0, $game_server );
        if( !$this->current_gather ) {

            $game_server = $this->pps->request_game_server();
            if( !$game_server ) {
                $this->send( "No available game servers.", $this->chan );
                return;
            }

            $this->current_gather = new gather_man( $this->pps->get_max_gather_id()+1, $game_server );
        }

        //$tg = new gather_man( 0, $game_server );
        $names = array( "cat", "dog", "mouse", "duck", "sheep", "wolf" );
        $ranks = array( 1, 6, 20, 30, 40, 50 );
        $i = 0;

        foreach( $players as  $rating ) {

            $result = false;
            $name = $names[$i];
            $result = $this->current_gather->add_rated( $name, $rating, array('rank'=>$ranks[$i], 'total'=>100), 20, null );
            $this->send( $result, $this->chan );
            $i++;

            if( $this->current_gather->is_full() ) {

                $this->start_gather( $this->current_gather, 0, 60 );
                $this->current_gather = null;
                return;
            }
        }
        if( $i < 5 ) {
            for( $i ; $i < 6; $i++ ) {
                $result = $this->current_gather->add( $names[$i] );
                $this->send( $result, $this->chan );

                if( $this->current_gather->is_full() ) {

                    $this->start_gather( $this->current_gather, 0, 60 );
                    $this->current_gather = null;
                    return;
                }
            }
        }

        //$this->start_gather( $tg, 0, 60 );
    }

    function fill_gather( ) {
        
        if( !$this->current_gather ) return;
        $names = array( "_fiiler0", "_filler1", "_filler2", "_filler3", "_filler4", "_filler5" );

        $diff = 6 - $this->current_gather->pc;

        for( $i = 0; $i < $diff; $i++ ) {
            
            if( !$this->current_gather ) return; //safety check
            $result = $this->current_gather->add( $names[$i] );
            $this->send( $result, $this->chan );

        }

       $this->start_gather( $this->current_gather, 0, 30 );

        $this->current_gather = null;
    }

}

?>
