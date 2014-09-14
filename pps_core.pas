{
        pps_core script for the 'Soldat' video game(http://www.soldat.pl)
        
	Code and original concept by Arthur Buldauskas aka ']{ing'(whinemore@gmail.com/arthrurb@yahoo.com)
	for fractured project at : http://fracs.net/pps/ and http://www.fracs.net.
        
        THIS SOFTWARE IS PROVIDED BY THE AUTHOR AND CONTRIBUTORS ``AS IS'' AND
        ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE
        IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR
        PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE AUTHOR OR CONTRIBUTORS
        BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR
        CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF
        SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS
        INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN
        CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE)
        ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE
        POSSIBILITY OF SUCH DAMAGE.
        
	Note: This script is written for the default weapon balance in version 1.5 of 'Soldat'.
}

Type pps_player = Record
	// Total Number of shots 
	pshots : integer;
	// Number of hits
	phits : integer;
	// Number of hits per weapon
	pwhits : array[0..11] of integer;
	// Damage done
	pdamage : integer;
	// Number of shots per weapon
	pwshot : Array[0..11] of integer;
	// Number of Ammo available
	pammo_a : integer;
	// Last known number of ammo available 
	pammo_l : integer;
	// Current main weapon number
	pweapon : integer;
	// Is the players accuracy being rated?
	prated : boolean;
end;

var
	p : array[0..32] of pps_player;
    pc: byte; //Player count
    pps_connected : boolean; //PPS stats status
	
	// # of Bullets available per main 
	wbullets : array[0..11] of integer;
	// min/max damage of current weapon, needed to make sure stuff like nades and knife shots dont count as wep dmg
	wdmg_min : array[0..11] of integer;
	wdmg_max : array[0..11] of integer;

    vote_cmd : string;
    vote_type : string;
    vote_check : array[0..32] of boolean;
    vote_to  : byte;
    vote_req : Double; 
    vote_num : byte;
    vote_active : boolean;

procedure MessageAll( message : string );
begin
    WriteConsole( 0, message, $EE81FAA1 );
end;

procedure StartMapVote( Map : string );
    begin
        vote_active := true;
        vote_cmd := '/map ' + Map;
        vote_type := 'Map';
        MessageAll('Map vote started: ' + Map)
    end;

procedure StartBanKickVote( Name : string );
    begin
        vote_active := true;
        vote_cmd := Name;
        vote_type := 'Ban';
        MessageAll('Kickbacn vote started: ' + Name);
    end;

procedure PassBanKickVote( );
    begin
        BanPlayerReason( NameToId(vote_cmd), 15, 'Ban vote passed' ); 
    end;

procedure StartUnbanlastVote();
    begin
        vote_active := true;
        vote_cmd := '/unbanlast';
        vote_type := 'Unban';
        MessageAll('Unbanlast vote started.');
    end;

procedure CancelVote();
    var
        i : byte;
    begin
        for i := 1 to 32 do vote_check[ i ] := false;

        vote_num := 0;
        vote_cmd := '';
        vote_active := false;
        vote_type := '';
        MessageAll( 'Vote finished.' );
    end;

procedure CastVote( ID: byte );
    begin
        if vote_active = false then exit;
        if vote_check[ ID ] <> true then
            begin
                vote_check[ ID ] := true;
                vote_num := vote_num + 1;

                MessageAll('Vote needed to pass: >= ' + FormatFloat('0.00', vote_req) + '.Left: ' + FormatFloat('0.00', vote_req  - vote_num) );

                if( vote_num*1.0 >= vote_req ) then
                    begin
                        if vote_type = 'Ban' then PassBanKickVote();
                        Command( vote_cmd );
                        CancelVote();
                    end
                    
            end;
    end;

procedure OnGameEnd();
begin
    WriteLn('NXMAP');
end;

procedure ActivateServer();
begin
// Set the number of bullets for the main weapons 
	wbullets[1] := 7;
	wbullets[2] := 30;
	wbullets[3] := 40;
	wbullets[4] := 25;
	wbullets[5] := 8;
	wbullets[6] := 4;
	wbullets[7] := 1;
	wbullets[8] := 10;
	wbullets[9] := 50;
	wbullets[10] := 200;
	
	wdmg_min[1] := 24; wdmg_max[1] := 40;
	wdmg_min[2] := 14; wdmg_max[2] := 26;
	wdmg_min[3] := 19; wdmg_max[3] := 30;
	wdmg_min[4] := 15; wdmg_max[4] := 23;
	wdmg_min[5] := 1; wdmg_max[5] := 1;
	wdmg_min[6] := 59; wdmg_max[6] := 96;
	wdmg_min[7] := 10000; wdmg_max[7] := 20000;
	wdmg_min[8] := 200; wdmg_max[8] := 330;
	wdmg_min[9] := 16; wdmg_max[9] := 28;
	wdmg_min[10] := 1; wdmg_max[10] := 1;

    //Initialize vote variables
    vote_req := 1;
    vote_num := 0;
    vote_to := 0;
    vote_active := false;
    pps_connected := false;
end;

procedure AppOnIdle(Ticks: integer);
var
	k : integer;
begin
	{ This Procedure does one of 3 things:
		A: Recognizes a reload
		B: Recognizes that a reload has finished
		C: Updates the ammount of ammo and shots taken per player
	}
	for k := 0 to 32 do begin
		// If a player is active....
		if GetPlayerStat(k, 'Active') = true then begin
			if p[k].prated = false then Continue;
			// In soldat, during a reload a weapons ammo is set to zero(except for spas :< )
			if GetPlayerStat(k, 'Ammo') = 0 then begin
				// Only do this if the user has a primary weapon in his/her hand
				if GetPlayerStat(k, 'Primary') > 0 then begin if GetPlayerStat(k, 'Primary') < 11 then begin
					if p[k].pammo_a <> -1 then begin
						// if ammo = -1 that means a reload is in progress...
						//{Debug}WriteConsole(k, 'reload start', $00FF0000 );{Debug}
						p[k].pammo_a := -1;
						// Update shots
						p[k].pshots := p[k].pshots + 1; 
						p[k].pwshot[p[k].pweapon] := p[k].pwshot[p[k].pweapon] + 1;
					end
				end end
			end else begin
			// Player Ammo is not zero check for other two events
				if p[k].pammo_a = -1 then begin
				//B: Reload finished..
					if GetPlayerStat(k, 'Primary' ) > 0 then begin if GetPlayerStat(k, 'Primary') < 11 then begin
						p[k].pammo_a := wbullets[p[k].pweapon];
						p[k].pammo_l := wbullets[p[k].pweapon];
						//{DEBUG}WriteConsole(k, 'reload finish - ammo = ' + inttostr(p[k].pammo_a), $0000FF00 );{DEBUG}
					end end
				end else begin
				//C: last option, just update ammo count/shots fired
					if GetPlayerStat(k, 'Primary' ) > 0 then begin if GetPlayerStat(k, 'Primary') < 11 then begin
						p[k].pammo_a := GetPlayerStat(k, 'Ammo');
						if p[k].pammo_a <> p[k].pammo_l then begin
							p[k].pshots := p[k].pshots + (p[k].pammo_l - p[k].pammo_a);
							p[k].pwshot[p[k].pweapon] := p[k].pwshot[p[k].pweapon] + (p[k].pammo_l - p[k].pammo_a);
							//Desert eagles count as TWO SHOTS so we must adjust for this here
							if p[k].pweapon = 1 then begin
								p[k].pshots := p[k].pshots + (p[k].pammo_l - p[k].pammo_a);
								p[k].pwshot[p[k].pweapon] := p[k].pwshot[p[k].pweapon] + (p[k].pammo_l - p[k].pammo_a);
                            end;
							p[k].pammo_l := p[k].pammo_a;
                            //{DEBUG}WriteConsole(k, 'shots ' + inttostr(p[k].pwshot[p[k].pweapon]), $00FFFF00 );{DEBUG}
						end
					end end
				end // end if Ammo = -1
			end // end if Ammo <> 0
        end; // end if Active
    end; // end for loop

    //Vote timeout stuff
    if(vote_active = true) and (vote_to <> 0) then vote_to := vote_to - Ticks;
    if(vote_active = true) and (vote_to = 0) then CancelVote();
end;
					
function OnPlayerDamage(Victim, Shooter: byte; Damage: integer): integer;
// Update hits/shots..
begin		
	result := Damage;
	
	//WriteConsole(Shooter, 'dmg ' + inttostr(Damage), $00FF00FF );
    if( GetPlayerStat(Victim, 'human') <> true ) then begin
        exit;
    end;
    if( GetPlayerStat(Shooter, 'human') <> true ) then begin
        exit;
    end;
	
	// Update damage
	p[Shooter].pdamage := p[Shooter].pdamage + Damage;
	
	// If the player is being rated update hits
	if p[Shooter].prated = true then begin
		p[Shooter].pammo_a := GetPlayerStat( Shooter, 'Ammo' );
		p[Shooter].pshots := p[Shooter].pshots + (p[Shooter].pammo_l - p[Shooter].pammo_a);
		p[Shooter].pwshot[p[Shooter].pweapon] := p[Shooter].pwshot[p[Shooter].pweapon] + (p[Shooter].pammo_l - p[Shooter].pammo_a);
		//Desert eagles count as TWO SHOTS so we must adjust for this here
		if p[Shooter].pweapon = 1 then begin
			p[Shooter].pshots := p[Shooter].pshots + (p[Shooter].pammo_l - p[Shooter].pammo_a);
			p[Shooter].pwshot[p[Shooter].pweapon] := p[Shooter].pwshot[p[Shooter].pweapon] + (p[Shooter].pammo_l - p[Shooter].pammo_a);
        end;
		
		// NOTE: This part of the code makes sure that the dmg done is in the range of the main weapon
		if Damage > wdmg_min[p[Shooter].pweapon] then begin
			if Damage < wdmg_max[p[Shooter].pweapon] then begin
				// Update hits
				
				p[Shooter].phits := p[Shooter].phits + 1;
				p[Shooter].pwhits[p[Shooter].pweapon] := p[Shooter].pwhits[p[Shooter].pweapon] + 1;
				
			end
        end;
		// Few things like nades COULD un-balance shots to hits ratio
		if p[Shooter].pwshot[p[Shooter].pweapon] < p[Shooter].pwhits[p[Shooter].pweapon] then begin 
			p[Shooter].pwshot[p[Shooter].pweapon] := p[Shooter].pwhits[p[Shooter].pweapon]; 
        end;
				
		p[Shooter].pammo_l := p[Shooter].pammo_a;
    end;
	
	if Victim <> Shooter then begin
		if p[Victim].prated = true then begin
			p[Victim].pammo_a := GetPlayerStat( Victim, 'Ammo' );
			if p[Victim].pammo_a <> p[Victim].pammo_l then begin
				p[Victim].pwshot[p[Victim].pweapon] := p[Victim].pwshot[p[Victim].pweapon] + (p[Victim].pammo_l - p[Victim].pammo_a);
				p[Victim].pshots := p[Victim].pshots + (p[Victim].pammo_l - p[Victim].pammo_a);
				//Desert eagles count as TWO SHOTS so we must adjust for this here
				if p[Victim].pweapon = 1 then begin
					p[Victim].pshots := p[Victim].pshots + (p[Victim].pammo_l - p[Victim].pammo_a);
					p[Victim].pwshot[p[Victim].pweapon] := p[Victim].pwshot[p[Victim].pweapon] + (p[Victim].pammo_l - p[Victim].pammo_a);
				end
            end;
			p[Victim].pammo_l := p[Victim].pammo_a;
		end
	end
end;

procedure OnPlayerRespawn( ID: byte );
begin
	// Not much here, just set players ammo to -2
	p[ID].pammo_a := -2;
end;

procedure OnJoinTeam(ID, Team: byte);
var
	k : integer;
begin
    if( GetPlayerStat(ID, 'human') <> true ) then begin
        exit;
    end;

    WriteLn('PJOIN '+GetPlayerStat(ID,'hwid')+' ' + inttostr(ID) + ' ' + inttostr(Team) + ' ' + GetPlayerStat(ID, 'name'));

    if(Team = 1) or (Team = 2) then pc := pc + 1;
    vote_req := pc;
    if pc > 2 then 
        begin
            vote_req := pc * 0.6;
        end;

    //Player initialize
	p[ID].pammo_a := -2;
	p[ID].pammo_l := 0;
	p[ID].pshots := 0;
	for k := 0 to 11 do begin
		p[ID].pwshot[k] := 0;
		p[ID].pwhits[k] := 0;
    end;
	p[ID].pweapon := -1;
	p[ID].prated := true;
	p[ID].phits := 0;
	p[ID].pdamage := 0;
    if Team = 5 then begin
        WriteConsole(ID, 'Visit #soldat.na on quakenet.org to bind your account with your auth!', $0000FF00 );
    end
end;

procedure OnLeaveGame(ID, Team: byte; Kicked: boolean);
begin
    if( GetPlayerStat(ID, 'human') <> true ) then begin
        exit;
    end;

    if (Team = 1) or (Team = 2) then pc := pc - 1;
    vote_req := pc;
    if pc > 2 then 
        begin
            vote_req := pc * 0.6;
        end;

    WriteLn('PLEFT ' + inttostr(ID) + ' ' + inttostr(Team) + ' ' + GetPlayerStat(ID, 'name'));
	WriteLn('[acc]'+ GetPlayerStat(ID,'ip') +'[/acc]d' + inttostr(p[ID].pdamage) + 's' + inttostr(p[ID].pshots) + 'h' + inttostr(p[ID].phits) 
				+ 'w[1]' + inttostr(p[ID].pwshot[1]) + ':' + inttostr(p[ID].pwhits[1])
				+ '[2]' + inttostr(p[ID].pwshot[2]) + ':' + inttostr(p[ID].pwhits[2])
				+ '[3]' + inttostr(p[ID].pwshot[3]) + ':' + inttostr(p[ID].pwhits[3])
				+ '[4]' + inttostr(p[ID].pwshot[4]) + ':' + inttostr(p[ID].pwhits[4])
				+ '[5]' + inttostr(p[ID].pwshot[5]) + ':' + inttostr(p[ID].pwhits[5])
				+ '[6]' + inttostr(p[ID].pwshot[6]) + ':' + inttostr(p[ID].pwhits[6])
				+ '[7]' + inttostr(p[ID].pwshot[7]) + ':' + inttostr(p[ID].pwhits[7])
				+ '[8]' + inttostr(p[ID].pwshot[8]) + ':' + inttostr(p[ID].pwhits[8])
				+ '[9]' + inttostr(p[ID].pwshot[9]) + ':' + inttostr(p[ID].pwhits[9]) 
				+ '[10]');
end;

procedure OnPlayerKill(Killer, Victim: Byte; Weapon: String);
begin
    WriteLn( 'PKILL ' + inttostr(Killer) + ' ' +  inttostr(Victim) + ' ' + Weapon ); 
end;

procedure OnWeaponChange(ID, PrimaryNum, SecondaryNum: Byte);
begin
    if( GetPlayerStat(ID, 'human') <> true ) then begin
        exit;
    end;

	if p[ID].pammo_a = -2 then begin
		if PrimaryNum <> 0 then begin if PrimaryNum < 11 then begin 
			p[ID].pweapon := PrimaryNum;
			p[ID].pammo_a := integer(wbullets[p[ID].pweapon]);
			p[ID].pammo_l := p[ID].pammo_a;
			p[ID].prated := true;
            if p[ID].pweapon = 5 then begin p[ID].prated := false; end;
			// When you choose an m79, it starts out on a reload(+1 shot) so we compensate for that here
			if p[ID].pweapon = 7 then begin
				p[ID].pshots := p[ID].pshots - 1;
				p[ID].pwshot[7] := p[ID].pwshot[7] - 1;
			end
		end end
	end else begin
		if PrimaryNum <> 0 then begin if PrimaryNum < 11 then begin
			if PrimaryNum <> p[ID].pweapon then begin
				p[ID].pweapon := PrimaryNum;
				p[ID].pammo_a := integer(wbullets[p[ID].pweapon]);
				p[ID].pammo_l := p[ID].pammo_a;
				p[ID].prated := true
                if p[ID].pweapon = 5 then begin p[ID].prated := false; end;
				// When you choose an m79, it starts out on a reload(+1 shot) so we compensate for that here
				if p[ID].pweapon = 7 then begin
					p[ID].pshots := p[ID].pshots - 1;
					p[ID].pwshot[7] := p[ID].pwshot[7] - 1;
				end
			end
		end end
	end 
end;

procedure OnPlayerSpeak(ID: byte; Text: string);
begin
    if( GetPlayerStat(ID, 'human') <> true ) then begin exit; end;

    if Text = '!stats' then 
        begin
            if pps_connected = true then MessageAll( 'Stats are: ON.' ) else
            if pps_connected = false then MessageAll( 'Stats are: OFF.' );
            MessageAll( '!cmd, !commands for list of commands' );
        end
    else if(Text = '!cmd') or (Text = '!commands') then
        begin
            if pps_connected = true then MessageAll( 'Stats commands: !rating, !auth.' );
            MessageAll( 'Game commands: !info, !a, !b, !s, !map, !bankick, !ub, !v' );
        end
    else
    if Text = '!info'  then   WriteConsole(ID, 'Progressive Play System. Idle on irc: #soldat.na', $0000FF00) else
    if Text = '!rating'  then WriteLn( 'PRATE'+GetPlayerStat(ID,'name') ) else
    if(Text = '!auth') or (Text = '!code') or (Text = '!secret')  then   WriteLn( 'RCODE'+GetPlayerStat(ID,'name') ) else
    if(Text = '!1') or (Text ='!a') or (Text = '!alpha') then Command('/setteam1 ' + IntToStr(ID)) else
    if(Text = '!2') or (Text ='!b') or (Text = '!bravo') then Command('/setteam2 ' + IntToStr(ID)) else
    if(Text = '!5') or (Text ='!s') or (Text = '!spec') then Command('/setteam5 ' + IntToStr(ID)) else
    if(GetPiece(Text,' ',0) = '!map') and (vote_active = false) then 
        begin
            StartMapVote( Copy( Text, 6, 120 ) );
            CastVote( ID );
        end else
    if(GetPiece(Text,' ',0) = '!bankick') and (vote_active = false) then
        begin
            StartBanKickVote( Copy(Text, 10, 120 ) );
            CastVote( ID );
        end else
    if Text = '!ub' then 
        begin
            StartUnbanlastVote();
            CastVote( ID );
        end else
    if(Text = '!v') or (Text = '!vote') or (Text = '!yes') or (Text = '!y') or (Text = '!m') then CastVote( ID );
end;

procedure OnFlagGrab(ID, TeamFlag: byte; GrabbedInBase: boolean );
begin
    if( GetPlayerStat(ID, 'human') <> true ) then begin
        exit;
    end;
    if( GrabbedInBase ) then begin
        WriteLn('PGRAB'+GetPlayerStat(ID,'name'));
    end;
end;

procedure OnFlagScore( ID, TeamFlag: byte );				
begin
    if( GetPlayerStat(ID, 'human') <> true ) then begin
        exit;
    end;
    WriteLn('PCAPF'+GetPlayerStat(ID,'name'));
end;

procedure OnFlagReturn( ID, TeamFlag: byte );
begin
    if( GetPlayerStat(ID, 'human') <> true ) then begin
        exit;
    end;
    WriteLn('PRETF'+GetPlayerStat(ID,'name'));
end;

function OnCommand( ID : Byte; Text : string ): boolean;
begin
    if Text = '/ppson' then
        begin
            MessageAll( 'PPS Stats Script has connected.' );
            pps_connected := true;
        end;

    Result := false;
end;

procedure OnAdminDisconnect( IP: string);
begin
    if IP = '127.0.0.1' then
        begin
            MessageAll( 'PPS Stats Script has disconnected.' );
            pps_connected := false;
        end;
end;

