<?php
/**
 * This file implements the Mass Delete plugin for {@link http://b2evolution.net/}.
 *
 * @copyright (c)2010 by Sonorth Corp. - {@link http://b2evo.sonorth.com/}.
 * @license GNU General Public License 2 (GPL) - http://www.opensource.org/licenses/gpl-license.php
 *
 * @author Sonorth Corp.
 */
if( !defined('EVO_MAIN_INIT') ) die( 'Please, do not access this page directly.' );


class mass_delete_plugin extends Plugin
{
	var $name = 'Mass delete';
	var $code = 'massdelete';
	var $priority = 30;
	var $version = '0.0.1-dev';
	var $group = 'Sonorth Corp.';
	var $author = 'Sonorth Corp.';
	var $author_url = 'http://b2evo.sonorth.com';

	var $apply_rendering = 'stealth';
	var $number_of_installs = 1;


	/**
	 * Init
	 *
	 * This gets called after a plugin has been registered/instantiated.
	 */
	function PluginInit( & $params )
	{
		$this->short_desc = $this->T_('Delete multiple objects in one click');
		$this->long_desc = $this->short_desc;
	}

	function AdminAfterMenuInit()
	{
		$this->register_menu_entry( $this->name );
	}


	function AdminTabPayload()
	{
		global $Messages, $UserSettings, $current_User, $app_version;

		if( $this->hide_tab == 1 ) return;
		$Messages->clear('all'); // Reset all messages

		$BlogCache = & get_Cache( 'BlogCache' );

		$action = param( 'action', 'string' );
		$action_url = 'admin.php?ctrl=tools&amp;tab=plug_ID_'.$this->ID;

		$target_blog_ID = param( 'target_blog_ID', 'string' );

		if( !empty($target_blog_ID) )
		{	// Display fieldset

			memorize_param( 'target_blog_ID', 'string', 0, $target_blog_ID );
			set_param('target_blog_ID',$target_blog_ID);

			$checkall = param( 'checkall', 'integer', 0 );  // Non-Javascript-CheckAll

			if( $target_blog_ID == 'all' )
			{
				$SQL = 'SELECT * FROM T_comments';
				$Results = new Results( $SQL, 'cm_', '----D', 30 );
				$Results->title = $this->T_('Latest comments');
			}
			elseif( is_numeric($target_blog_ID) )
			{
				$lBlog = & $BlogCache->get_by_ID( $target_blog_ID );

				if( version_compare( $app_version, '4', '<' ) )
				{
					load_class('comments/model/_commentlist.class.php');

					$show_statuses = param( 'show_statuses', 'array', array(), true );
					$Results = new CommentList( $lBlog, "'comment','trackback','pingback'",
								$show_statuses, '',	'',	'DESC',	'',	30 );
				}
				else
				{
					load_class('comments/model/_commentlist.class.php', 'CommentList2');

					$Results = new CommentList2( $lBlog );

					// Filter list:
					$Results->set_default_filters( array(
							'statuses' => array( 'published', 'draft', 'deprecated' ),
							'comments' => 30,
						) );

					$Results->load_from_Request();
					$Results->query();
				}
				$Results->title = $this->T_('Latest comments').': '.$lBlog->get('shortname');
			}
			else
			{
				return;
			}


			$Form = new Form( 'admin.php', '', 'post' );
			$Form->begin_form( 'fform' );
			$Form->hidden_ctrl();
			$Form->hiddens_by_key( get_memorized() );
			$Form->hidden( 'target_blog_ID', $target_blog_ID );
			$Form->hidden( 'action', 'delete' );


			if( $Results->total_rows < 1 )
			{
				echo '<p><a href="'.$action_url.'">&laquo; Go back</a></p><p>Nothing found</p>';
				$Form->end_form();
				return;
			}


			function commresults_results_td_box( $Obj )
			{
				global $checkall;

				// Checkbox
				$r = '<span name="surround_check" class="checkbox_surround_init">';
				$r .= '<input title="'.T_('Select this comment').'" type="checkbox" class="checkbox"
							name="target_ids[]" value="'.$Obj->comment_ID .'" id="target_id_'.$Obj->comment_ID .'"';

				if( $checkall )
				{
					$r .= ' checked="checked"';
				}
				$r .= ' />';
				$r .= '</span>';

				return $r;
			}
			$Results->cols[] = array(
					'th' => '+',
					'td' => '% commresults_results_td_box( {row} ) %',
					'td_class' => 'checkbox firstcol shrinkwrap',
				);

			// Issue date:
			$Results->cols[] = array(
					'th' => T_('Content'),
					'td' => '%strmaxlen( preg_replace("~[(<br />)\s+]~", " ", #comment_content#), 90 )%',
				);

			$Results->cols[] = array(
					'th' => T_('IP'),
					'order' => ($target_blog_ID == 'all') ? 'comment_author_IP' : 'author_IP',
					'th_class' => 'shrinkwrap',
					'td_class' => 'left',
					'td' => '$comment_author_IP$',
				);

			$Results->cols[] = array(
					'th' => T_('Status'),
					'order' => ($target_blog_ID == 'all') ? 'comment_status' : 'status',
					'th_class' => 'shrinkwrap',
					'td' => '%ucfirst( #comment_status# )%',
				);

			// Issue date:
			$Results->cols[] = array(
					'th' => T_('Date'),
					'order' => ($target_blog_ID == 'all') ? 'comment_date' : 'date',
					'default_dir' => 'D',
					'th_class' => 'shrinkwrap',
					'td_class' => 'center',
					'td' => '%mysql2localedatetime_spans( #comment_date# )%',
				);


			function commresults_td_actions( $commID )
			{
				$target_blog_ID = get_param('target_blog_ID');

				$r = action_icon( T_('Edit'), 'edit', '?ctrl=comments&action=edit&comment_ID='.$commID, '', 5, 1 );
				$r .= action_icon( T_('Delete'), 'delete', regenerate_url( '', 'comm_ID='.$commID.'&amp;target_blog_ID='.$target_blog_ID.'&amp;action=delete' ), '', 5, 1, array( 'onclick' => 'return confirm(\''.T_('Do you really want to delete this comment?').'\')' ) );

				return $r;
			}
			$Results->cols[] = array(
					'th' => $this->T_('Actions'),
					'td' => '% commresults_td_actions( #comment_ID# ) %',
					'th_class' => 'shrinkwrap',
					'td_class' => 'shrinkwrap',
				);

			$Results->display();

			echo '<div class="notes" style="margin:1px auto 10px 4px">'.$Form->check_all().' '.$this->T_('Check/Uncheck all').'</div>';

			$Form->end_form( array( array( 'value' => $this->T_('Delete selected comments !'), 'onclick' => 'return confirm(\''.$this->T_('Do you really want to continue?').'\')' ) ) );
		}
		else
		{
			echo '<h2>Select a blog</h2>';

			$Form = new Form( 'admin.php', '', 'post' );
			$Form->begin_form( 'fform' );
			$Form->hidden_ctrl();
			$Form->hiddens_by_key( get_memorized() );

			$blog_options = '<option selected="selected" value="all">== All ==</option>';
			$blog_options .= $BlogCache->get_option_list();


			if( !empty($blog_options) )
			{
				$Form->select_input_options( 'target_blog_ID', $blog_options, $this->T_('Select a blog'), '' );

				echo '<fieldset><div class="label"><label></label></div>';
				echo '<div class="input">
						<input type="submit" value="'.format_to_output( $this->T_('Continue...'), 'formvalue' ).'">
					  </div></fieldset>';
			}
			$Form->end_form();

			?>

			<script type="text/javascript">
            <!--
            var allchecked = Array();
            var idprefix;

			function toggleCheckboxes(the_form, the_elements, set_name )
			{
				if( typeof set_name == 'undefined' )
				{
					set_name = 0;
				}
				if( allchecked[set_name] ) allchecked[set_name] = false;
				else allchecked[set_name] = true;

				var elems = document.forms[the_form].elements[the_elements];
				if( !elems )
				{
					return;
				}
				var elems_cnt = (typeof(elems.length) != 'undefined') ? elems.length : 0;
				if (elems_cnt)
				{
					for (var i = 0; i < elems_cnt; i++)
					{
						elems[i].checked = allchecked[nr];
					} // end for
				}
				else
				{
					elems.checked = allchecked[nr];
				}
				setcheckallspan( set_name );
			}


			function setcheckallspan( set_name, set )
			{
				if( typeof(allchecked[set_name]) == 'undefined' || typeof(set) != 'undefined' )
				{ // init
					allchecked[set_name] = set;
				}

				if( allchecked[set_name] )
				{
					var replace = document.createTextNode('uncheck all');
				}
				else
				{
					var replace = document.createTextNode('check all');
				}

				if( document.getElementById( idprefix+'_'+String(set_name) ) )
				{
					document.getElementById( idprefix+'_'+String(set_name) ).replaceChild(replace, document.getElementById( idprefix+'_'+String(set_name) ).firstChild);
				}
				//else alert('no element with id '+idprefix+'_'+String(set_name));
			}

				function initcheckall( htmlid, init )
			{
				// initialize array
				allchecked = Array();
				idprefix = typeof(htmlid) == 'undefined' ? 'checkallspan' : htmlid;

				for( var lform = 0; lform < document.forms.length; lform++ )
				{
					for( var lelem = 0; lelem < document.forms[lform].elements.length; lelem++ )
					{
						if( document.forms[lform].elements[lelem].id.indexOf( idprefix ) == 0 )
						{
							var index = document.forms[lform].elements[lelem].name.substring( idprefix.length+2, document.forms[lform].elements[lelem].name.length );
							if( document.getElementById( idprefix+'_state_'+String(index)) )
							{
								setcheckallspan( index, document.getElementById( idprefix+'_state_'+String(index)).checked );
							}
							else
							{
								setcheckallspan( index, init );
							}
						}
					}
				}
			}

			/**
			 * Check if files are selected.
			 *
			 * This should be used as "onclick" handler for "With selected" actions (onclick="return check_if_selected_files();").
			 * @return boolean true, if something is selected, false if not.
			 */
			function check_if_selected_files()
			{
				elems = document.getElementsByName( 'target_ids[]' );
				var checked = 0;
				for( i = 0; i < elems.length; i++ )
				{
					if( elems[i].checked )
					{
						checked++;
					}
				}
				if( !checked )
				{
					alert( '<?php echo TS_('Nothing selected.') ?>' );
					return false;
				}
				else
				{
					return true;
				}
			}
				//-->
			</script>

        <?php
		}
	}


	function AdminTabAction()
	{
		global $DB;

		if( !$this->check_perms() ) $this->hide_tab = 1;
		if( $this->hide_tab == 1 ) return;

		$action = param( 'action', 'string', 'list' );

		switch( $action )
		{
			case 'delete':
				$target_ids = param( 'target_ids', 'array', array(), true );
				if( count($target_ids) > 0 )
				{
					foreach( $target_ids as $cID )
					{
						$IDs[] = (is_numeric($cID)) ? $DB->quote(trim($cID)) : '';
					}
					$string = implode( ', ', $IDs );

					$SQL = 'DELETE FROM T_comments
							WHERE comment_ID IN ('.$string.')';

					if( $num = $DB->query($SQL) )
					{
						$this->msg( sprintf( 'Deleted comments [%d]', $num ), 'success' );
					}
					else
					{
						$this->msg( 'Nothing deleted', 'notes' );
					}
				}
				elseif( $comm_ID = param( 'comm_ID', 'integer' ) )
				{
					$SQL = 'DELETE FROM T_comments
							WHERE comment_ID = '.$DB->quote($comm_ID);

					if( $DB->query($SQL) )
					{
						$this->msg( 'Comment deleted', 'success' );
					}
				}
				else
				{
					$this->msg( 'Nice try! Now select some comments.', 'notes' );
				}
				break;
		}
	}


	function check_perms()
	{
		global $current_User, $Settings, $UserSettings;

		$msg = $this->T_('You\'re not allowed to view this page!');

		if( !is_logged_in() )
		{	// Not logged in
			$this->msg( $msg, 'error' );
			$this->hide_tab = 1;
			return false;
		}
		if( !$current_User->check_perm( 'options', 'edit', true ) )
		{
			$this->msg( $msg, 'error' );
			$this->hide_tab = 1;
			return false;
		}
		return true;
	}


	// Trim array
	function array_trim( $array )
	{
		return array_map( 'trim', $array );
	}
}

?>