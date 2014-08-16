var
    unpause : integer;
	
procedure ActivateServer();
begin  
    unpause := -1;
end;

procedure AppOnIdle(Ticks: integer);
begin

    if unpause = 0 then begin
        Command('/unpause');
        unpause := -1;
    end
    
    if unpause > 0 then begin
        Command('/say ... ' + inttostr(unpause) + ' ');
        unpause := unpause - 1;
    end

end;
					
function OnPlayerDamage(Victim, Shooter: byte; Damage: integer): integer;
// Update hits/shots..
begin		
	result := Damage;
	
end;

procedure OnJoinTeam(ID, Team: byte);
begin
    WriteLn('PJOIN(' + inttostr(ID) + ')[' + inttostr(Team) + ']' + GetPlayerStat(ID, 'name'));
    if Team = 5 then begin
        WriteConsole(ID, 'Use !auth command to get your secret code.', $0000FF00 );
        WriteConsole(ID, 'The same command in irc will BIND your auth with your soldat HWID', $0000FF00 );
        WriteConsole(ID, 'Do not share your code with anyone, its UNIQUE to your account!', $0000FF00 );
    end
end;

procedure OnMapChange(NewMap: string);
begin
    WriteLn('NXMAP:' + NewMap);
end;

procedure OnLeaveGame(ID, Team: byte; Kicked: boolean);
begin
    WriteLn('PLEFT(' + inttostr(ID) + ')[' + inttostr(Team) + ']' + GetPlayerStat(ID, 'name'));
end;

procedure OnPlayerSpeak(ID: byte; Text: string);
var
    Map: string;
begin
    if( copy(Text,1,6) = '!info' ) then begin
        WriteConsole(ID, 'In-Depth Info available at: #soldat.na', $0000FF00);
        {WriteConsole(ID, 'http://fracs.net/pps/info/', $0000FF00); }
    end
    if( copy(Text,1,6) = '!code' ) then begin
        WriteLn('RCODE'+GetPlayerStat(ID,'name'));
    end
    if( copy(Text,1,8) = '!rating' ) then begin
        WriteLn('PRATE'+GetPlayerStat(ID,'name'));
    end
    if( copy(Text,1,3) = '!p') then begin
        Command('/pause');
        unpause := -1;
    end
    if( copy(Text,1,4) = '!up') then begin
        if unpause = -1 then begin
            unpause := 3;
        end
    end
    if( copy(Text,1,3) = '!r') then begin
        Command('/restart');
    end
    if( copy(Text,1,4) = '!ub') then begin
        Command('/say Unbanned');
        Command('/unbanlast');
    end
    if( copy(Text,1,4) = '!map') then begin
        Map := GetPiece(Text,' ',1);
        Command('/map '+Map );
    end
end;
					
