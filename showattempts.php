<?php  // $Id: showattempts.php,v 1.6 2012/07/25 11:16:04 bdaloukas Exp $
/**
 * This page shows the answers of the current game
 * 
 * @author  bdaloukas
 * @version $Id: showattempts.php,v 1.6 2012/07/25 11:16:04 bdaloukas Exp $
 * @package game
 **/
 
    require_once("../../config.php");
    require_once( "headergame.php");

    if (!has_capability('mod/game:viewreports', $context)){
		print_error( get_string( 'only_teachers', 'game'));
	}

    $PAGE->navbar->add(get_string('showattempts', 'game'));

    $action  = optional_param('action', "", PARAM_ALPHANUM);  // action
    if( $action == 'delete'){
        game_ondeleteattempt( $game);
    }

    echo get_string( 'group').': ';
    game_showgroups( $game);
    echo ' &nbsp; '.get_string('user').': ';
    game_showusers( $game);echo '<br><br>';
     
    game_showattempts( $game);

    echo $OUTPUT->footer();
    
    
    function game_showusers($game)
    {
        global $CFG, $USER, $DB;

        $users = array();

        $context = get_context_instance(CONTEXT_COURSE, $game->course);

        $groupid = optional_param('groupid',0, PARAM_INT);
        $sql =  "SELECT DISTINCT ra.userid,u.lastname,u.firstname FROM {role_assignments} ra, {user} u ".
                " WHERE ra.contextid={$context->id} AND ra.userid=u.id";
        if( $groupid != 0)
            $sql .= " AND ra.userid IN (SELECT gm.userid FROM {groups_members} gm WHERE gm.groupid=$groupid)";
		if( ($recs = $DB->get_records_sql( $sql))){
			foreach( $recs as $rec){
				$users[ $rec->userid] = $rec->lastname.' '.$rec->firstname;
			}
		}
        
        
        if ($guest = guest_user()) {
            $users[$guest->id] = fullname($guest);
        }
        ?>
            <script type="text/javascript">
                function onselectuser()
                {
                    window.location.href = "<?php echo $CFG->wwwroot.'/mod/game/showattempts.php?q='.$game->id.'&userid=';?>" + document.getElementById('menuuser').value + '&groupid=' + document.getElementById('menugroup').value;
                }
            </script>
        <?php

        $attributes = 'onchange="javascript:onselectuser();"';
        $name = 'user';
        $id = 'menu'.$name;
        $class = 'menu'.$name;
        $class = 'select ' . $class; /// Add 'select' selector always
        $nothing = get_string("allparticipants");
        $nothingvalue='0';
        $options = $users;
        $selected = optional_param('userid',0, PARAM_INT);
    
        $output = '<select id="'. $id .'" class="'. $class .'" name="'. $name .'" '. $attributes .'>' . "\n";
        $output .= '   <option value="'. s($nothingvalue) .'"'. "\n";
        if ($nothingvalue === $selected) {
            $output .= ' selected="selected"';
        }
        $output .= '>'. $nothing .'</option>' . "\n";

        if (!empty($options)) {
            foreach ($options as $value => $label) {
                $output .= '   <option value="'. s($value) .'"';
                if ((string)$value == (string)$selected ||
                    (is_array($selected) && in_array($value, $selected))) {
                    $output .= ' selected="selected"';
                }
                if ($label === '') {
                    $output .= '>'. $value .'</option>' . "\n";
                } else {
                    $output .= '>'. $label .'</option>' . "\n";
                }
            }
        }
        echo $output . '</select>' . "\n";
    }

    function game_showgroups($game)
    {
        global $CFG, $USER, $DB;

        $groups = array();
		if( ($recs = $DB->get_records_sql( "SELECT id,name FROM {groups} WHERE courseid=$game->course ORDER BY name"))){
			foreach( $recs as $rec){
				$groups[ $rec->id] = $rec->name;
			}
		}

        ?>
            <script type="text/javascript">
                function onselectgroup()
                {
                    window.location.href = "<?php echo $CFG->wwwroot.'/mod/game/showattempts.php?q='.$game->id.'&groupid=';?>" + document.getElementById('menugroup').value;
                }
            </script>
        <?php

        $attributes = 'onchange="javascript:onselectgroup();"';
        $name = 'group';
        $id = 'menu'.$name;
        $class = 'menu'.$name;
        $class = 'select ' . $class; /// Add 'select' selector always
        $nothing = get_string("allgroups");
        $nothingvalue='0';
        $options = $groups;
        $selected = optional_param('groupid',0, PARAM_INT);
    
        $output = '<select id="'. $id .'" class="'. $class .'" name="'. $name .'" '. $attributes .'>' . "\n";
        $output .= '   <option value="'. s($nothingvalue) .'"'. "\n";
        if ($nothingvalue === $selected) {
            $output .= ' selected="selected"';
        }
        $output .= '>'. $nothing .'</option>' . "\n";

        if (!empty($options)) {
            foreach ($options as $value => $label) {
                $output .= '   <option value="'. s($value) .'"';
                if ((string)$value == (string)$selected ||
                    (is_array($selected) && in_array($value, $selected))) {
                    $output .= ' selected="selected"';
                }
                if ($label === '') {
                    $output .= '>'. $value .'</option>' . "\n";
                } else {
                    $output .= '>'. $label .'</option>' . "\n";
                }
            }
        }
        echo $output . '</select>' . "\n";
    }

    function game_showattempts($game){
        global $CFG, $DB, $OUTPUT;

        $allowdelete = optional_param('allowdelete', 0, PARAM_INT);

        $userid = optional_param('userid',0,PARAM_INT);
        $limitfrom = optional_param('limitfrom',  0, PARAM_INT);
        $gamekind = $game->gamekind;
        $update = get_coursemodule_from_instance( 'game', $game->id, $game->course)->id;

        //Here are user attempts
        $table = "{game_attempts} as ga, {user} u, {game} as g";
        $select = "ga.userid=u.id AND ga.gameid={$game->id} AND g.id={$game->id}";
        $fields = "ga.id, u.lastname, u.firstname, ga.attempts,".
          "timestart, timefinish, timelastattempt, score, ga.lastip, ga.lastremotehost";
        if( $userid != 0)
            $select .= ' AND u.id='.$userid;
        $sql = "SELECT COUNT(*) AS c FROM $table WHERE $select";
        $count = $DB->count_records_sql( $sql);
        $maxlines = 20;
        $recslimitfrom = $recslimitnum = '';
        if( $count > $maxlines){
            $recslimitfrom = ( $limitfrom ? $limitfrom * $maxlines : '');
            $recslimitnum = $maxlines;

            for($i=0; $i*$maxlines < $count; $i++){
                if( $i == $limitfrom){
                    echo ($i+1).' ';
                }else
                {
                    echo "<A HREF=\"{$CFG->wwwroot}/mod/game/preview.php?action=showattempts&amp;update=$update&amp;q={$game->id}&amp;limitfrom=$i&\">".($i+1)."</a>";
                    echo ' &nbsp;';
                }
            }
            echo "<br>";
        }

        $sql = "SELECT $fields FROM $table WHERE $select ORDER BY timelastattempt DESC,timestart DESC";
        if( ($recs = $DB->get_records_sql( $sql, null, $recslimitfrom, $recslimitnum)) != false){
            echo '<table border="1">';
            echo '<tr><td><b>'.get_string( 'delete').'</td><td><b>'.get_string('user').'</td>';
            echo '<td><b>'.get_string('lastip', 'game').'</b></td>';
            echo '<td><b>'.get_string('timestart', 'game').'</b></td>';
            echo '<td><b>'.get_string('timelastattempt', 'game').'</b></td>';
            echo '<td><b>'.get_string('timefinish', 'game').'</b></td>';
            echo '<td><b>'.get_string('score', 'game').'</b></td>';
            echo '<td><b>'.get_string('attempts', 'game').'</b></td>';
            echo '<td><b>'.get_string('preview', 'game').'</b></td>';
            echo '<td><b>'.get_string('showsolution', 'game').'</b></td>';
            echo "</tr>\r\n";

            foreach( $recs as $rec){
                echo '<tr>';
                echo '<td><center>';
                if( ($rec->timefinish == 0) or $allowdelete){
                    echo "\r\n<a href=\"{$CFG->wwwroot}/mod/game/showattempts.php?attemptid={$rec->id}&amp;q={$game->id}&amp;action=delete";
		    if( $allowdelete)
			echo '&allowdelete=1';
		    echo '">';
                    echo '<img src="'.$OUTPUT->pix_url('t/delete').'" alt="'.get_string( 'delete').'" /></a>';
                }
                echo '</center></td>';
                echo '<td><center>'.$rec->firstname. ' '.$rec->lastname.'</center></td>';
                echo '<td><center>'.(strlen( $rec->lastremotehost) > 0 ? $rec->lastremotehost : $rec->lastip).'</center></td>';
                echo '<td><center>'.( $rec->timestart != 0 ? userdate($rec->timestart) : '')."</center></td>\r\n";
                echo '<td><center>'.( $rec->timelastattempt != 0 ? userdate($rec->timelastattempt) : '').'</center></td>';
                echo '<td><center>'.( $rec->timefinish != 0 ? userdate($rec->timefinish) : '').'</center></td>';
                echo '<td><center>'.round($rec->score * 100).'</center></td>';
                echo '<td><center>'.$rec->attempts.'</center></td>';
                echo '<td><center>';
	        	//Preview
	        	if( ($gamekind == 'cross') or ($gamekind == 'sudoku') or ($gamekind == 'hangman') or ($gamekind == 'cryptex')){
	        		echo "\r\n<a href=\"{$CFG->wwwroot}/mod/game/preview.php?action=preview&amp;attemptid={$rec->id}&amp;gamekind=$gamekind";
	        		echo '&amp;update='.$update."&amp;q={$game->id}\">";
                    echo '<img src="'.$OUTPUT->pix_url('t/preview').'" alt="'.get_string( 'preview', 'game').'" /></a>';
	        	}
                echo '</center></td>';

	    	    //Show solution
                echo '<td><center>';
	    	    if( ($gamekind == 'cross') or ($gamekind == 'sudoku') or ($gamekind == 'hangman') or ($gamekind == 'cryptex') ){
	    		    echo "\r\n<a href=\"{$CFG->wwwroot}/mod/game/preview.php?action=solution&amp;attemptid={$rec->id}&amp;gamekind={$gamekind}&amp;update=$update&amp;&amp;q={$game->id}\">";
	    		    echo '<img src="'.$OUTPUT->pix_url('t/preview').'" alt="'.get_string( 'showsolution', 'game').'" /></a>';
    	    	}
                echo '</center></td>';
                echo "</tr>\r\n";
            }
            echo "</table>\r\n";
        }
    }

	function game_ondeleteattempt( $game)
	{
		global $CFG, $DB;

        $attemptid  = required_param('attemptid', PARAM_INT);
		
		$attempt = $DB->get_record( 'game_attempts', array( 'id' => $attemptid));
				
		switch( $game->gamekind)
		{
		case 'bookquiz':
			$DB->delete_records( 'game_bookquiz_chapters', array( 'attemptid' => $attemptid));
			break;
		}
		$DB->delete_records( 'game_queries', array( 'attemptid' => $attemptid));
		$DB->delete_records( 'game_attempts', array( 'id' => $attemptid));
	}
