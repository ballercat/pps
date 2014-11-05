{
        pps_core script for the 'Soldat' video game(http://www.soldat.pl)
        
	Code and original concept by Arthur Buldauskas aka ']{ing'(whinemore@gmail.com/arthrurb@yahoo.com)
	for fractured project at : 
        
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
}

var
    gather_mode : boolean;
    unpause_timer : byte;
    unpause : boolean;
    tiebreaker : string;

procedure MessageAll( message : string );
begin
    WriteConsole( 0, message, $EE81FAA1 );
end;

procedure PrivateMessage( player_id : byte; message : string );
    begin
        if player_id = 0 then
            begin
                MessageAll( message );
            end
        else begin
            WriteConsole( player_id, message, $FF34DBB7 );
        end;
    end;


procedure ChangeMap( Map : string );
    begin
        Command('/map ' + Map);
    end;
    

procedure ActivateServer();
begin
    gather_mode := true;
    unpause_timer := 0;
    unpause := false;
    tiebreaker := 'ctf_Laos';
end;

procedure AppOnIdle(Ticks: integer);
begin
	
    if unpause = true then
        begin
            if unpause_timer = 0 then
                begin
                    MessageAll('GO!');
                    Command('/unpause');
                    unpause := false;
                end
            else begin
                MessageAll(IntToStr(unpause_timer) + '...');
                unpause_timer := unpause_timer - 1;
            end;
        end;

end;

procedure OnPlayerSpeak(ID: byte; Text: string);
begin
    if( GetPlayerStat(ID, 'human') <> true ) then begin exit; end;

    if(Text = '!cmd') or (Text = '!commands') then
        begin
            MessageAll( 'Stats commands: !rating, !auth.' );
            MessageAll( 'Gather commands: !info, !a, !b, !s, !map, !tb, !ub, !p, !up, !irc' );
        end
    else
    if Text = '!info'  then   WriteConsole(ID, 'Progressive Play System. Idle on irc: #gather/#soldat.na(lounge)', $0000FF00) else
    if Text = '!rating'  then WriteLn( 'PRATE'+GetPlayerStat(ID,'name') ) else
    if(Text = '!auth') or (Text = '!code') or (Text = '!secret')  then   WriteLn( 'RCODE'+GetPlayerStat(ID,'name') ) else
    if(Text = '!1') or (Text ='!a') or (Text = '!alpha') then Command('/setteam1 ' + IntToStr(ID)) else
    if(Text = '!2') or (Text ='!b') or (Text = '!bravo') then Command('/setteam2 ' + IntToStr(ID)) else
    if(Text = '!5') or (Text ='!s') or (Text = '!spec') then Command('/setteam5 ' + IntToStr(ID)) else
    if(GetPiece(Text,' ',0) = '!irc') then
        begin
            WriteLn( 'MIRC ' + GetPlayerStat(ID,'name') + ' ' + Copy(Text, 6, 120) );
        end else
    if(GetPiece(Text,' ',0) = '!map') then
        begin
            ChangeMap( Copy( Text, 6, 120 ) );
        end else
    if Text = '!ub' then 
        begin
            Command('/unbanlast');
        end else
    if Text = '!p' then
        begin
            Command('/pause');
            unpause := false;
        end else
    if Text = '!up' then
        begin
            if Paused then 
                begin
                    MessageAll('Unpausing in:');
                    unpause := true;
                    unpause_timer := 3;
                end;
        end else
    if Text = '!r' then
        begin
            Command('/restart');
        end else
    if Text = '!tb' then
        begin
            Command('/map ' + tiebreaker);
        end;
end;

function OnCommand( ID : Byte; Text : string ): boolean;
begin
    if( GetPiece(Text, ' ', 0) = '/pvm' ) then
        begin
            PrivateMessage( StrToInt(GetPiece(Text, ' ', 1)), Copy(Text,9,120) );
        end
    else if(GetPiece(Text,' ',0) = '/tiebreaker') then
        begin
            tiebreaker := GetPiece(Text, ' ', 1);
        end;

    Result := false;
end;

