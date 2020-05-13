<?php
/**
 * Subscribtions plugin for b2evolution
 *
 * {@internal Below is a list of authors who have contributed to design/coding of this file: }}
 * @author Yabba	- {@link http://www.astonishme.co.uk/}
 * @author Stk		- {@link http://www.astonishme.co.uk/}
 * @author sam2kb	- {@link http://b2evo.sonorth.com/}
 *
 * @license GNU General Public License 2 (GPL) - http://www.opensource.org/licenses/gpl-license.php
 *
 */
if( !defined('EVO_MAIN_INIT') ) die( 'Please, do not access this page directly.' );


class subscriptions_plugin extends Plugin
{
	var $name = 'Subscriptions';
	var $code = 'subscrb';
	var $priority = 50;
	var $version = '1.2.4';
	var $group = 'Sonorth Corp.';
	var $author = 'Russian b2evolution';
	var $author_url = 'http://b2evo.sonorth.com';
	var $help_url = 'http://forums.b2evolution.net/viewtopic.php?t=22680';

	var $apply_rendering = 'never';
	var $number_of_installs = 1;


	/**
	 * @internal
	 */
	var $_scheduled = array( 'item'=>array(), 'comment'=>array() );
	var $_notified = array( 'item'=>array(), 'comment'=>array() );
	var $_cron_log = array();
	var $_hide_tab = false;


	function PluginInit()
	{
		$this->short_desc = $this->T_('This plugin allows visitors to sign up for e-mail notifications of new events in your blogs.');
		$this->long_desc = $this->T_('Although RSS has been around for a fair while many users still don\'t know how it works and are far more comfortable with email notifications. This plugin gives them the ability so subscribe to notifications of new posts on a per blog basis, and new comments on a per post basis. All of the emails and other messages are fully configurable from the plugin\'s settings page in your admin area (see below for a full list of available settings).');
	}


	function GetDbLayout()
	{
	  return array(
			"CREATE TABLE IF NOT EXISTS ".$this->get_sql_table('subs')." (
				sub_id INT(11) unsigned NOT NULL auto_increment,
				sub_item_id INT(11) NOT NULL default '0',
				sub_blog_id INT(11) NOT NULL default '0',
				sub_user_name VARCHAR(255) NOT NULL default '',
				sub_user_email VARCHAR(255) NOT NULL default '',
				sub_date datetime NOT NULL default '2000-01-01 00:00:00',
				PRIMARY KEY (sub_id),
				UNIQUE KEY sub_item_id_email (sub_item_id, sub_user_email),
				INDEX (sub_item_id),
				INDEX (sub_blog_id)
			   )",

			"CREATE TABLE IF NOT EXISTS ".$this->get_sql_table('pings')." (
				ping_id INT(11) NOT NULL default '0',
				ping_type VARCHAR(7) NOT NULL default '',
				UNIQUE KEY ping_id_type (ping_id, ping_type)
			   )",
	    );
	}


	function BeforeEnable()
	{
		global $app_version;

		if( version_compare( $app_version, '3', '<' ) )
		{
			return sprintf( $this->T_('The "%s plugin" requires b2evolution v3.0.0, or higher.'), $this->name );
		}
		return true;
	}


	/**
	 * Default notifications and other messages
	 *
	 * @return array defaults
	 */
	function GetDefaultSettings()
	{
		global $app_name;

		return array(
			'add_to_forms' => array(
				'label' => $this->T_('Add to comment forms'),
				'defaultvalue' => 1,
				'type' => 'checkbox',
				'note' => $this->T_('Check this to display subscribtions checkboxes in feedback forms'),
			),
			'email_error' => array(
				'label' => $this->T_('No email message'),
				'defaultvalue' => $this->T_('You need to provide your email address to subscribe'),
				'type' => 'text',
				'size' => 60,
				'note' => $this->T_('This is the error message displayed if visitor wants to subscribe but doesn\'t leave an email address'),
			),
			'settings_end' => array(
				'layout' => 'end_fieldset',
			),
			'comment_settings_begin' => array(
				'layout' => 'begin_fieldset',
				'label' => $this->T_('Comments settings'),
			),
			'comment_subject' => array(
				'label' => $this->T_('Subject'),
				'defaultvalue' => $this->T_('New reply to').': $post_title$',
				'type' => 'text',
				'size' => 60,
				'note' => $this->T_('This is the subject of the email message sent out for comment subscriptions'),
			),
			'comment_message' => array(
				'label' => $this->T_('Message'),
				'defaultvalue' => sprintf( $this->T_('Hi %s,

You\'re receiving this email because you wished to be notified of new comments on "%s"
You can read the new comment here:
%s

--
If you wish to unsubscribe from future notifications, please click here:
%s'),
					'$name$', '$post_title$', '$link$', '$unsubscribe$'),
				'type' => 'html_textarea',
				'note' => $this->T_('This is the email message sent out for comment subscriptions.<br />Replacement values: <b>$name$</b> - visitor\'s name, <b>$post_title$</b> - post\'s title, <b>$link$</b> - URL of the comment made, <b>$unsubscribe$</b> - link to unsubscribe from notifications'),
				'rows' => 10,
			),
			'comment_settings_end' => array(
				'layout' => 'end_fieldset',
			),
			'post_settings_begin' => array(
				'layout' => 'begin_fieldset',
				'label' => $this->T_('Posts settings'),
			),
			'post_subject' => array(
				'label' => $this->T_('Subject'),
				'defaultvalue' => $this->T_('New post on').': $blog_name$',
				'type' => 'text',
				'size' => 60,
				'note' => $this->T_('This is the subject of the email message sent out for blog subscriptions'),
				),
			'post_message' => array(
				'label' => $this->T_('Message'),
				'defaultvalue' => sprintf( $this->T_('Hi %s,

You\'re receiving this email because you wished to be notified of new posts in the blog "%s"
You can read the new post [%s] here:
%s

--
If you wish to unsubscribe from future notifications, please click here:
%s'),
						'$name$', '$blog_name$', '$post_title$', '$link$', '$unsubscribe$'),
				'type' => 'html_textarea',
				'note' => $this->T_('This is the email message sent out for blog subscriptions, see the help file for replacement values'),
				'rows' => 10,
			),
			'post_settings_end' => array(
				'layout' => 'end_fieldset',
			),
			'unsubscribe_settings_begin' => array(
				'layout' => 'begin_fieldset',
				'label' => $this->T_('Unsubscribe settings'),
			),
			'unsubscribed' => array(
				'label' => $this->T_('"Success" message'),
				'defaultvalue' => $this->T_('Selected subscriptions have been removed'),
				'type' => 'html_textarea',
				'note' => $this->T_('This is the message displayed when visitor has successfully unsubscribed'),
			),
			'unsubscribe_error' => array(
				'label' => $this->T_('"Error" message'),
				'defaultvalue' => $this->T_('Sorry, there was a problem removing your subscription. Please try again later.'),
				'type' => 'html_textarea',
				'note' => $this->T_('This is the message displayed when unsubscription throws an unknown error'),
			),
			'unsubscribed_invalid' => array(
				'label' => $this->T_('Invalid subscription message'),
				'defaultvalue' => $this->T_('Sorry, that subscription was not found' ),
				'type' => 'html_textarea',
				'note' => $this->T_('This is the message displayed when an invalid unsubscription attempt is made'),
			),
			'multiple_unsubscribe' => array(
				'label' => $this->T_('Multiple unsubscribe message'),
				'defaultvalue' => $this->T_('You also have the following subscriptions that you may wish to cancel.'),
				'type' => 'html_textarea',
				'note' => $this->T_('This is the message displayed when the visitor has more subscriptions which they may wish to cancel.'),
			),
			'unsubscribe_page' => array(
				'label' => $this->T_('Unsubscribe page'),
				'defaultvalue' => '
<h3>'.$app_name.' Subscriptions - Unsubscribe</h3>
<p>$message$</p>
$form$',
				'type' => 'html_textarea',
				'note' => $this->T_('This is the page displayed after unsubscribing, see the help file for replacement values'),
				'rows' => 14,
			),
		);
	}


	function get_widget_param_definitions( $params )
	{
		$r = array(
			'blog_ID' => array(
				'label' => $this->T_('Target blog'),
				'note' => $this->T_('ID of the blog to use, leave empty for the current blog.'),
				'size' => 4
			),
			'title' => array(
				'label' => $this->T_('Title'),
				'defaultvalue' => $this->T_('Watch this blog'),
				'note' => $this->T_('Widget title displayed in skin'),
				'type' => 'html_input',
				'size' => 40
			),
			'notes_visitor' => array(
				'label' => $this->T_('Text for visitors'),
				'defaultvalue' => $this->T_('Enter your name and email address to receive notifications about new posts in this blog.'),
				'note' => $this->T_('Notes displayed to visitors below the email input field.'),
				'type' => 'html_textarea',
				'rows' => 3,
				'cols' => 40,
			),
			'notes_user' => array(
				'label' => $this->T_('Text for users'),
				'defaultvalue' => $this->T_('Click &quot;Subscribe&quot; to receive notifications about new posts in this blog.'),
				'note' => $this->T_('Notes displayed to users. Logged in users don\'t need to enter their name and email address.'),
				'type' => 'html_textarea',
				'rows' => 3,
				'cols' => 40,
			),
		);

		return $r;
	}


	function AfterInstall()
	{
		$this->msg( sprintf( $this->T_('Please note: email notifications will be queued in order to be executed asynchronously.<br />You will need to set up <a %s>b2evoltuion Scheduler</a> for this plugin to work.'),
						'href="http://manual.b2evolution.net/Scheduler"'), 'note' );
	}


	function ExecCronJob( & $params )
	{	// We provide only one cron job, so no need to check $params['ctrl'] here
		$status = $this->send_notifications( $params['params']['type'], $params['params']['target_ID'], $params['params']['parent_ID'] );

		$msg = 'Finished ';
		if( $status === true )
		{
			$code = 1;
			$msg .= '(OK)';
		}
		elseif( $status === NULL )
		{
			$code = 1;
			$msg .= '(not needed)';
		}
		elseif( $status === false )
		{
			$code = 2;
			$msg .= '(error)';
		}

		if( !empty($this->_cron_log) )
		{
			$msg .= "\n<br />".implode( "\n<br />", $this->_cron_log );
		}

		return array( 'code' => 1, 'message' => $msg );
	}


	function AdminAfterMenuInit()
	{
		$this->register_menu_entry( $this->name );
	}


	function AdminTabPayload()
	{
		global $Messages, $UserSettings, $current_User, $app_version;

		if( $this->_hide_tab == 1 ) return;
		$Messages->clear('all'); // Reset all messages

		//memorize_param( 'target_blog_ID', 'string', 0, $target_blog_ID );
		//set_param('target_blog_ID',$target_blog_ID);

		$this->display_results();
	}


	function AdminTabAction()
	{
		global $DB;

		if( !$this->check_perms() ) $this->_hide_tab = 1;
		if( $this->_hide_tab == 1 ) return;

		if( param( 'action_delete', 'string' ) )
		{	// Delete selected items
			$target_ids = param( 'target_ids', 'array', array(), true );
			if( count($target_ids) > 0 )
			{
				foreach( $target_ids as $tID )
				{
					$IDs[] = (is_numeric($tID)) ? $DB->quote(trim($tID)) : '';
				}
				$string = implode( ', ', $IDs );

				$SQL = 'DELETE FROM '.$this->get_sql_table('subs').'
						WHERE sub_id IN ('.$string.')';

				if( $num = $DB->query($SQL) )
				{
					$this->msg( sprintf( 'Deleted [%d] rows', $num ), 'success' );
				}
				else
				{
					$this->msg( 'Nothing deleted', 'notes' );
				}
			}
			elseif( $row_ID = param( 'row_ID', 'integer' ) )
			{
				$SQL = 'DELETE FROM '.$this->get_sql_table('subs').'
						WHERE sub_id = '.$DB->quote($row_ID);

				if( $DB->query($SQL) )
				{
					$this->msg( 'Row deleted', 'success' );
				}
			}
			else
			{
				$this->msg( 'Nice try! Now select something and try again.', 'notes' );
			}
		}

		/*
		$action = param( 'action', 'string', 'list' );

		switch( $action )
		{
			case 'delete':
				break;
		}
		*/
	}


	function check_perms()
	{
		global $current_User;

		$msg = $this->T_('You\'re not allowed to view this page!');

		if( !is_logged_in() )
		{	// Not logged in
			$this->msg( $msg, 'error' );
			$this->_hide_tab = 1;
			return false;
		}
		if( !$current_User->check_perm( 'options', 'edit', true ) )
		{
			$this->msg( $msg, 'error' );
			$this->_hide_tab = 1;
			return false;
		}
		return true;
	}


	/**
	 * Sets all the display parameters
	 * these will either be the default display params
	 * or the widget display params if it's in a container
	 *
	 * @param array $params
	 */
 	function init_display( $params = array() )
 	{
		$temp = $this->get_widget_param_definitions( array() );

		$full_params = array();
		foreach( $temp as $setting => $values )
		{
			$full_params[ $setting ] = ( isset( $params[ $setting ] ) ? $params[ $setting ] : $this->Settings->get( $setting ) );
		}

		foreach( $params as $param => $value )
		{
			if( !isset( $full_params[ $param ] ) ) $full_params[ $param ] = $value;
		}
		//return $this->disp_params;
		return $full_params;
	}


	function SkinBeginHtmlHead()
	{
		add_css_headline('
			.widget_plugin_'.$this->code.' .form_text_input { margin-top: 1px }
			.widget_plugin_'.$this->code.' fieldset { border: none; padding: 3px }');
	}


	function SkinTag( & $params )
	{
		// If the widget params aren't set up, using the plugin defaults
		$params = $this->init_display($params);
		$this->show_widget( $params );
		return true;
	}


	function show_widget( & $params )
	{
		global $Session, $Blog, $current_User;

		$params = array_merge( array(
				'block_start'		=> '<div class="$wi_class$">',
				'block_end'			=> '</div>',
				'block_title_start'	=> '<h3>',
				'block_title_end'	=> '</h3>',
			), $params );

		$r  = $params['block_start']."\n";
		$r .= $params['block_title_start'].$params['title'].$params['block_title_end']."\n";

		$Form = new Form( regenerate_url('action,redirect_to') );
		$Form->output = false;

		$blog_ID = empty($params['blog_ID']) ? $Blog->ID : $params['blog_ID'];

		$r .= $Form->begin_form();

		if( is_logged_in() )
		{
			$r .= $Form->hidden( $this->get_class_id('user_id'), $current_User->ID );
			$r .= $Form->hidden( $this->get_class_id('user_key'), sha1($this->code.$Session->ID.$current_User->ID) );
			$r .= $Form->info('', $params['notes_user']);
		}
		else
		{
			$r .= $Form->text( $this->get_class_id('name'), '', 25, $this->T_('Name'), '', 255 );
			$r .= $Form->text( $this->get_class_id('email'), '', 25, $this->T_('Email'), '', 255 );
			$r .= $Form->info('', $params['notes_visitor']);
		}
		$r .= $Form->hidden( $this->get_class_id('blog_id'), $blog_ID );
	//	$r .= $Form->add_crumb('subscribe');
		$r .= $Form->end_form( array(array('name' => 'subscribe', 'class' => 'submit', 'value' => $this->T_('Subscribe'))) );

		$r  .= $params['block_end']."\n";

		echo $r;
	}


	function BeforeBlogDisplay()
	{
		global $Session, $DB, $localtimenow;

		if( $blog_ID = (int) param( $this->get_class_id('blog_id'), 'integer' ) )
		{
			//$Session->assert_received_crumb('subscribe');

			if( $key = param( $this->get_class_id('user_key'), 'string' ) ) // sha1( plugin_code.session_id.user_id )
			{
				$user_ID = (int) param( $this->get_class_id('user_id'), 'integer' );

				// Invalid key
				if( $key != sha1($this->code.$Session->ID.$user_ID) ) return;

				$name = '';
				$email = $user_ID;
			}
			else
			{
				$name = param( $this->get_class_id('name'), 'string' );
				$email = param( $this->get_class_id('email'), 'string' );

				if( empty($name) || empty($email) || !is_email($email) )
				{
					$this->msg( $this->T_('Invalid name or email address.'), 'error' );
					return;
				}
			}

			// Let's subscribe this user
			$SQL = 'INSERT INTO '.$this->get_sql_table('subs').' (sub_user_name, sub_user_email, sub_blog_id, sub_date)
					VALUES( "'.$DB->escape($name).'",
							"'.$DB->escape($email).'",
							'.$DB->quote($blog_ID).',
							'.$DB->quote( date('Y-m-d H:i:s', $localtimenow) ).' )
					ON DUPLICATE KEY UPDATE sub_user_name = VALUES(sub_user_name), sub_blog_id = VALUES(sub_blog_id), sub_date = VALUES(sub_date)';

			if( $DB->query($SQL) )
			{
				$this->msg( $this->T_('You are now subscribed'), 'success' );
			}
			else
			{
				$this->msg( $this->T_('Unable to subscribe'), 'notes' );
			}
		}
	}


	function CommentFormSent( & $params )
	{
		global $Session;

		if( $params['action'] == 'preview' )
		{	// Comment preview: save checkbox state for use in form field
			$Session->set( $this->get_class_id('item_id'), param($this->get_class_id('item_id'), 'integer') );
			$Session->set( $this->get_class_id('blog_id'), param($this->get_class_id('blog_id'), 'integer') );
			$Session->dbsave();
		}
	}


	// Adds a checkbox for "subscribe to comments"
	function DisplayCommentFormFieldset( & $params )
	{
		global $Session, $blog;

		if( ! $this->Settings->get('add_to_forms') ) return; // disabled

		$item_checked = $Session->get( $this->get_class_id('item_id') );
		$blog_checked = $Session->get( $this->get_class_id('blog_id') );

		$Session->delete( $this->get_class_id('item_id') );
		$Session->delete( $this->get_class_id('blog_id') );
		$Session->dbsave();

		$params['Form']->checkbox( $this->get_class_id('item_id'), $item_checked, $this->T_('Watch post'),
					$this->T_('Watch this post (receive email notifications about new comments to this post).'), '', $params['Item']->ID );

		$params['Form']->checkbox( $this->get_class_id('blog_id'), $blog_checked, $this->T_('Watch blog'),
					$this->T_('Watch this blog (receive email notifications about new posts in this blog).'), '', $blog );
	}


	// Check if a user has subscribed
	function BeforeCommentFormInsert( & $params )
	{
		$this->item_id = (int) param( $this->get_class_id('item_id'), 'integer' );
		$this->blog_id = (int) param( $this->get_class_id('blog_id'), 'integer' );

		if( ($this->blog_id || $this->item_id) && empty($params['Comment']->author_ID) && empty($params['Comment']->author_email) )
		{	// No email provided by visitor
			$this->msg( $this->Settings->get('email_error'), 'error' );
		}
		return;
	}


	// Save subscription into the database
	function AfterCommentFormInsert( & $params )
	{
		global $DB, $localtimenow;

		if( $this->item_id != $params['Comment']->item_ID && $this->blog_id != $params['Comment']->Item->blog_ID )
		{	// Nothing to do, wrong values
			return;
		}

		if( empty($params['Comment']->author_ID) )
		{
			$sub_user_email = $params['Comment']->author_email;
			$sub_user_name = $params['Comment']->author;
		}
		else
		{
			$sub_user_email = $params['Comment']->author_ID;
			$sub_user_name = ''; // we'll get it later
		}

		$SQL1 = 'SELECT * FROM '.$this->get_sql_table('subs').'
				WHERE sub_user_email = "'.$DB->escape($sub_user_email).'"
				AND sub_item_id = '.$DB->quote($this->item_id);

		if( $sub = $DB->get_row($SQL1) )
		{	// Update name and settings

			$blog_sub = $sub->sub_blog_id;
			if( $this->blog_id > 0 )
			{	// Do not NULL current subscription, only add new
				$blog_sub = $this->blog_id;
			}

			$SQL = 'UPDATE '.$this->get_sql_table('subs').' SET
						sub_user_name = "'.$DB->escape($sub_user_name).'",
						sub_blog_id = '.$DB->quote($blog_sub).'
					WHERE sub_id = '.$DB->quote($sub->sub_id);

			if( $DB->query($SQL) )
			{
				$this->msg( $this->T_('Your subscribtion updated'), 'success' );
				return;
			}
		}
		else
		{
			$SQL = 'INSERT INTO '.$this->get_sql_table('subs').' (sub_user_name, sub_user_email, sub_item_id, sub_blog_id, sub_date)
					VALUES( "'.$DB->escape($sub_user_name).'",
							"'.$DB->escape($sub_user_email).'",
							'.$DB->quote($this->item_id).',
							'.$DB->quote($this->blog_id).',
							'.$DB->quote( date('Y-m-d H:i:s', $localtimenow) ).' )
					ON DUPLICATE KEY UPDATE sub_user_name = VALUES(sub_user_name), sub_blog_id = VALUES(sub_blog_id), sub_date = VALUES(sub_date)';

			if( $DB->query($SQL) )
			{
				$this->msg( $this->T_('You are now subscribed'), 'success' );
				return;
			}
		}

		$this->msg( $this->T_('Unable to subscribe'), 'notes' );
	}


	function AfterItemUpdate( & $params )
	{
		$this->AfterItemInsert( $params );
	}


	// Send out notifications
	function AfterItemInsert( & $params )
	{
		if( $params['Item']->get('status') != 'published' ) return; // nothing to do
		$this->schedule_notifications( 'item', $params['Item']->ID, $params['Item']->get_blog_ID(), $params['Item']->issue_date );
	}


	function AfterItemDelete( & $params )
	{
		$this->delete_subscriptions( 'item', $params['Item']->ID );
	}


	function AfterCollectionDelete( & $params )
	{	// b2evo 4
		$this->delete_subscriptions( 'blog', $params['Blog']->ID );
	}


	function AfterCommentUpdate( & $params )
	{
		$this->AfterCommentInsert( $params );
	}


	// Send out notifications
	function AfterCommentInsert( & $params )
	{
		if( $params['Comment']->get('status') != 'published' ) return; // nothing to do
		$this->schedule_notifications( 'comment', $params['Comment']->ID, $params['Comment']->item_ID );
	}


	function schedule_notifications( $type, $target_ID, $parent_ID, $date = 'now' )
	{
		if( in_array($target_ID, $this->_scheduled[$type]) ) return; // already scheduled

		$this->_scheduled[$type][] = $target_ID;

		if( $date == 'now' )
		{
			$date = date2mysql( $GLOBALS['servertimenow'] );
		}

		// CREATE OBJECT:
		load_class( '/cron/model/_cronjob.class.php', 'Cronjob' );
		$edited_Cronjob = new Cronjob();

		// start datetime. We do not want to ping before the post is effectively published:
		$edited_Cronjob->set( 'start_datetime', $date );

		// name:
		$edited_Cronjob->set( 'name', sprintf( $this->T_('Email notifications (%s)'), $type ) );

		// controller:
		$edited_Cronjob->set( 'controller', 'plugin_'.$this->ID.'_notify' );

		// params: specify which post this job is supposed to send notifications for:
		$edited_Cronjob->set( 'params', array( 'type'=>$type, 'target_ID'=>$target_ID, 'parent_ID'=>$parent_ID ) );

		// Save cronjob to DB:
		$edited_Cronjob->dbinsert();
	}


	/**
	 * Sends out notifications if required
	 *
	 * @param string 'item' or 'comment'
	 * @param integer item/comment id to send notifications for
	 * @param integer blog/item id of the item/comment parent
	 */
	function send_notifications( $type, $target_ID, $parent_ID )
	{
		global $DB;

		if( ! in_array($type, array('comment', 'item')) )
		{
			$this->_cron_log[] = sprintf( 'Unknown type [%s]', $type );
			return false;
		}
		if( in_array($target_ID, $this->_notified[$type]) )
		{
			$this->_cron_log[] = sprintf( 'Already notified [%s]', $type.' #'.$target_ID );
			return;
		}

		load_funcs( '_core/_url.funcs.php' );

		// Let's check if we've already sent notifications
		$SQL = 'SELECT ping_id FROM '.$this->get_sql_table('pings').'
				WHERE ping_id = '.$DB->quote($target_ID).'
				AND ping_type = "'.$DB->quote($type).'"';

		if( $DB->get_var($SQL) )
		{	// Notifications already sent for this item/comment
			$this->_cron_log[] = sprintf( 'Notifications already sent [%s]', $type.' #'.$target_ID );
			return NULL;
		}

		$notified = false;
		$status = false;

		switch( $type )
		{
			case 'comment':
				if( ! in_array($target_ID, $this->_notified[$type]) )
				{	// Has not been processed yet
					$notified = true;
					$status = $this->comment_notifications( $target_ID, $parent_ID );
				}
				break;

			case 'item':
				if( ! in_array($target_ID, $this->_notified[$type]) )
				{	// Has not been processed yet
					$notified = true;
					$status = $this->item_notifications( $target_ID, $parent_ID );
				}
				break;
		}

		$this->_notified[$type][] = $target_ID;

		if( $notified )
		{
			$this->_cron_log[] = 'Notifications processed';

			$DB->query('INSERT IGNORE INTO '.$this->get_sql_table('pings').' (ping_id, ping_type)
						VALUES ('.$DB->quote($target_ID).', "'.$type.'")');
		}
		else
		{
			$this->_cron_log[] = 'Notifications failed';
		}

		return $status;
	}


	/**
	 * Send out item notifications
	 *
	 * @param integer which comment should we send notifications for
	 * @param integer which post is the comment on
	 */
	function item_notifications( $target_ID, $parent_ID )
	{
		global $DB;

		if( function_exists('get_ItemCache') )
		{	// b2evo 4
			$ItemCache = & get_ItemCache();
			$BlogCache = & get_BlogCache();
		}
		else
		{
			$ItemCache = & get_Cache('ItemCache');
			$BlogCache = & get_Cache('BlogCache');
		}

		if( ($Item = $ItemCache->get_by_ID($target_ID, false, false)) === false )
		{
			$this->_cron_log[] = sprintf('Item #%s not found', $target_ID);
			return false;
		}

		if( ($Blog = $BlogCache->get_by_ID($parent_ID, false, false)) === false )
		{
			$this->_cron_log[] = sprintf('Blog #%s not found', $parent_ID);
			return false;
		}

		load_funcs( '_core/_url.funcs.php' );

		// Get author ID and email
		$User = $Item->get_creator_user();
		$author_user_email = $User->email;
		$author_user_ID = $Item->creator_user_ID;

		// Make sure we don't select same user twice
		$SQL = 'SELECT sub_id, sub_user_name, sub_user_email
				FROM '.$this->get_sql_table('subs').'
				WHERE sub_blog_id = '.$DB->quote($parent_ID).'
				GROUP BY sub_user_email';

		if( $rows = $DB->get_results($SQL) )
		{	// We have some subscribed users, let's notify them

			$subject = str_replace( '$blog_name$', $Blog->shortname, $this->Settings->get('post_subject') );
			$url = $Item->get_permanent_url();

			foreach( $rows as $row )
			{
				// Check if this is the author of the post
				if( $row->sub_user_email == $author_user_email || $row->sub_user_email == $author_user_ID )
				{	// Pointless telling them that they've posted ;)
					continue;
				}
				if( is_numeric($row->sub_user_email) )
				{	// This is a member, get name and email from their profile
					list($user_name, $user_email) = $this->get_user_name_email($row->sub_user_email);
				}
				else
				{
					$user_name = $row->sub_user_name;
					$user_email = $row->sub_user_email;
				}

				$unsub_url = $this->get_unsubscribe_url($row);

				// Let's build the message
				$message = str_replace(
						array('$name$', '$link$', '$unsubscribe$', '$blog_name$', '$post_title$'),
						array($user_name, $url, $unsub_url, $Blog->shortname, $Item->title ),
						$this->Settings->get('post_message')
					);

				// Normalize line endings
				$message = preg_replace( "~(\r\n|\r)~", "\n", $message );

				$this->_cron_log[] = sprintf('- sending message to %s', $user_email);

				// Now let's delight them with our shiny personalised message
				send_mail( $user_email, $user_name, $subject, $message );
			}
		}

		return true;
	}



	/**
	 * Send out comment notifications
	 *
	 * @param integer which comment should we send notifications for
	 * @param integer which post is the comment on
	 */
	function comment_notifications( $target_ID, $parent_ID )
	{
		global $DB;

		if( function_exists('get_ItemCache') )
		{	// b2evo 4
			$CommentCache = & get_CommentCache();
			$ItemCache = & get_ItemCache();
		}
		else
		{
			$CommentCache = & get_Cache('CommentCache');
			$ItemCache = & get_Cache('ItemCache');
		}

		if( ($Comment = $CommentCache->get_by_ID($target_ID, false, false)) === false )
		{
			$this->_cron_log[] = sprintf('Comment #%s not found', $parent_ID);
			return false;
		}
		if( ($Item = $ItemCache->get_by_ID($parent_ID, false, false)) === false )
		{
			$this->_cron_log[] = sprintf('Item #%s not found', $parent_ID);
			return false;
		}

		// Check if comment is by a member or visitor
		$sub_user_email = ( empty($Comment->author_user_ID) ? $Comment->author_email : $Comment->author_user_ID );

		// Make sure we don't select same user twice
		$SQL = 'SELECT sub_id, sub_user_name, sub_user_email
				FROM '.$this->get_sql_table('subs').'
				WHERE sub_item_id = '.$DB->quote($parent_ID).'
				GROUP BY sub_user_email';

		if( $rows = $DB->get_results($SQL) )
		{	// We have some subscribed users, let's notify them
			$subject = str_replace( '$post_title$', $Item->title, $this->Settings->get('comment_subject') );
			$url = $Comment->get_permanent_url();

			foreach( $rows as $row )
			{
				// Check if this is the author of the comment
				if( $row->sub_user_email == $sub_user_email )
				{	// Pointless telling them that they've commented ;)
					continue;
				}

				if( is_numeric($row->sub_user_email) )
				{	// This is a member, get name and email from their profile
					list($user_name, $user_email) = $this->get_user_name_email($row->sub_user_email);
				}
				else
				{
					$user_name = $row->sub_user_name;
					$user_email = $row->sub_user_email;
				}

				$unsub_url = $this->get_unsubscribe_url($row);

				// Let's build the message
				$message = str_replace(
						array('$name$', '$link$', '$unsubscribe$', '$post_title$'),
						array($user_name, $url, $unsub_url, $Item->title ),
						$this->Settings->get('comment_message')
					);

				$this->_cron_log[] = sprintf('- sending message to %s', $user_email);

				// Now let's delight them with our shiny personalised message
				send_mail( $user_email, $user_name, $subject, $message );
			}
		}

		return true;
	}


	function delete_subscriptions( $type, $parent_ID )
	{
		global $DB;

		// Null the values
		$DB->query('UPDATE '.$this->get_sql_table('subs').' SET sub_'.$type.'_id = 0
					WHERE sub_'.$type.'_id = '.$DB->quote($parent_ID));

		// Delete subs with all nulls
		$DB->query('DELETE FROM '.$this->get_sql_table('subs').'
					WHERE sub_blog_id = 0
					AND sub_item_id = 0');
	}


	function get_unsubscribe_url( $row )
	{
		if( empty($row) ) return '';

		return $this->get_htsrv_url('unsubscribe', array(), '&', true).
								'&'.$this->get_class_id('sub_id').'='.$row->sub_id.
								'&'.$this->get_class_id('user_key').'='.sha1( $this->code.$row->sub_user_name.$row->sub_user_email.$row->sub_id );
	}


	// Declares our htsrv actions
	function GetHtsrvMethods()
	{
	 	return array('unsubscribe', 'multiple_unsubscribe');
	}


	// Handles unsubscribes
	// If more than one subscription, offers the ability to unsubscribe from them all
	function htsrv_unsubscribe( & $params )
	{
		global $DB, $Session, $app_name;

		$sub_id = param( $this->get_class_id('sub_id'), 'integer' );
		$user_key = param( $this->get_class_id('user_key'), 'string' );

		add_js_headline('// Check if files are selected
function check_if_selected_ids()
{
	elems = document.getElementsByName( "'.$this->get_class_id('sub_id[]').'" );
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
		alert( "'.TS_('Nothing selected.').'" );
		return false;
	}
	else
	{
		return true;
	}
}');

		echo $this->get_chicago_page( 'header', array(
						'checkboxes'	=> true,
						'title'			=> $app_name.' subscriptions - Unsubscribe',
					) );

		if( empty($sub_id) || empty($user_key) )
		{
			$message = $this->Settings->get('unsubscribed_invalid');
		}
		else
		{
			if( function_exists('get_UserCache') )
			{	// b2evo 4
				$BlogCache = & get_BlogCache();
				$ItemCache = & get_ItemCache();
			}
			else
			{
				$BlogCache = & get_Cache('BlogCache');
				$ItemCache = & get_Cache('ItemCache');
			}

			$out = '';

			// Let's see if we have a subscription
			$SQL = 'SELECT sub_id, sub_user_name, sub_user_email
					FROM '.$this->get_sql_table('subs').'
					WHERE sub_id = '.$DB->quote($sub_id);

			if( $row = $DB->get_row($SQL) )
			{	// We have a result, does it match?
				if( $user_key == sha1($this->code.$row->sub_user_name.$row->sub_user_email.$row->sub_id) )
				{
					if( $DB->query('DELETE FROM '.$this->get_sql_table('subs').' WHERE sub_id = '.$row->sub_id) )
					{
						$message = $this->Settings->get('unsubscribed');
					}
					else
					{	// Something went wrong
						$message = $this->Settings->get('unsubscribe_error');
					}

					// Now we need to check if there are other subscriptions
					$SQL = 'SELECT sub_id, sub_blog_id, sub_item_id
							FROM '.$this->get_sql_table('subs').'
							WHERE sub_user_email = "'.$DB->escape($row->sub_user_email).'"
							ORDER BY sub_blog_id DESC';

					if( $subs = $DB->get_results($SQL) )
					{	// Produce list ( all selected ) of other subscriptions
						$Form = new Form( $this->get_htsrv_url('multiple_unsubscribe', array(), '&', true), $this->code );
						$Form->output = false;
						//$Form->switch_layout('table');
						$out .= $Form->begin_form( 'fform', '', array( 'target' => '_self', 'onsubmit' => 'return check_if_selected_ids(this);' ) );

						$out .= '<tr><td colspan="2"><div style="margin:10px 0">'.
									$this->Settings->get('multiple_unsubscribe').'</div></td></tr>';

						foreach( $subs as $sub )
						{
							$title = 'unknown';

							if( $sub->sub_blog_id > 0 )
							{	// Blog subscription
								if( $Blog = $BlogCache->get_by_ID($sub->sub_blog_id, false) )
								{
									$title = '['.T_('Blog').'] <a href="'.$Blog->gen_blogurl().'" target="_blank">'.$Blog->shortname.'</a>';
								}
							}
							if( $sub->sub_item_id > 0 )
							{	// Post subscription
								if( $Item = $ItemCache->get_by_ID($sub->sub_item_id, false) )
								{
									$title = '['.T_('Post').'] <a href="'.$Item->get_permanent_url().'" target="_blank">'.$Item->title.'</a>';
								}
							}

							$key2_arr[] = $sub->sub_id;

							if( $title == 'unknown' )
							{	// Delete subscription to missing target
								$out .= $Form->hidden( $this->get_class_id('sub_id[]'), $sub->sub_id );
							}
							else
							{
								//$out .= '<span name="surround_check" class="checkbox_surround_init">';
								//$out .= $Form->checkbox( $this->get_class_id('sub_id[]'), true, '>', $title, '', $sub->sub_id );
								//$out .= '</span>';

								$out .= '<fieldset id="'.$this->get_class_id('sub_id').$sub->sub_id.'">
									  <div><span name="surround_check" class="checkbox_surround_init"><input value="'.$sub->sub_id.'" name="'.$this->get_class_id('sub_id[]').'" checked="checked" class="checkbox" id="'.$this->get_class_id('sub_id').$sub->sub_id.'" type="checkbox" /></span>
									  <span class="notes">'.$title.'</span></div>
									</fieldset>';

								/*
								$out .= '<tr>
								  <td class="input"><input value="'.$sub->sub_id.'" name="'.$this->get_class_id('sub_id[]').'" checked="checked" id="'.$this->get_class_id('sub_id').$sub->sub_id.'" type="checkbox" /></td>
								  <td class="label"><label for="'.$this->get_class_id('sub_id').$sub->sub_id.'">'.$title.'</label></td>
								</tr>';
								*/
							}
						}
						$out .= $Form->hidden( $this->get_class_id('key1'), md5($row->sub_user_email) );
						$out .= $Form->hidden( $this->get_class_id('key2'), sha1($this->code.$Session->ID.md5($row->sub_user_email)) );

						$out .= '<tr><td colspan="2"><hr /><div class="notes" style="margin:10px">'.$Form->check_all().' '.$this->T_('Check/Uncheck all').'</div></td></tr>';

						$out .= $Form->end_form( array(array('name' => 'unsubscribe', 'class' => 'submit', 'value' => $this->T_('Unsubscribe'))) );
					}
				}
				else
				{
					$message = $this->Settings->get('unsubscribed_invalid');
				}
			}
			else
			{
				$message = $this->Settings->get('unsubscribed_invalid');
			}
		}
		echo str_replace( array('$message$', '$form$'), array($message, $out), $this->Settings->get('unsubscribe_page') );

		echo $this->get_chicago_page('footer');
	}


	// Handles multiple unsubscriptions
	function htsrv_multiple_unsubscribe( & $params )
	{
		global $DB, $Session;

		$key1 = param( $this->get_class_id('key1'), 'string' ); // md5( email )
		$key2 = param( $this->get_class_id('key2'), 'string' ); // sha1( plugin_code.session_id.key1 )
		$sub_ids = param( $this->get_class_id('sub_id'), 'array' );

		echo $this->get_chicago_page('header');

		if( $key1 && $key2 == sha1($this->code.$Session->ID.$key1) && ($sub_ids = $this->get_sanitized_ids($sub_ids)) !== false )
		{	// We have some that they want to remove
			$SQL = 'DELETE FROM '.$this->get_sql_table('subs').'
					WHERE md5(sub_user_email) = "'.$DB->escape($key1).'"
					AND sub_id IN ('.implode(',', $sub_ids).')';

			if( $DB->query($SQL) )
			{
				$message = $this->Settings->get('unsubscribed');
			}
			else
			{
				$message = $this->Settings->get('unsubscribe_error');
			}
		}
		else
		{
			$message = $this->T_('Bad request params');
		}
		echo str_replace( array( '$message$', '$form$' ), array( $message, '' ), $this->Settings->get('unsubscribe_page') );
		echo $this->get_chicago_page('footer');
	}


	function get_sanitized_ids( $var, $glue = "\n" )
	{
		if( !is_array($var) )
		{
			$var = explode("\n", $var);
		}

		$array = array_filter( array_map('trim', $var) );
		$array = array_map('intval', $array);
		$array = array_unique($array);

		if( !empty($array) ) return $array;
		return false;
	}


	function get_chicago_page( $what = 'header', $params = array() )
	{
		$params = array_merge( array(
				'title'			=> '',
				'jquery'		=> false,
				'checkboxes'	=> false,
			), $params );

		if( $what == 'header' )
		{
			global $evo_charset, $adminskins_url;

			ob_start();

			echo '<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
				<html xmlns="http://www.w3.org/1999/xhtml">
				<head>
				<meta http-equiv="Content-Type" content="text/html; charset='.$evo_charset.'" />
				<meta http-equiv="Expires" content="Sun, 15 Jan 1998 17:38:00 GMT" />
				<meta name="ROBOTS" content="NOINDEX, NOFOLLOW" />
				<title>'.$params['title'].'</title>
				<link rel="stylesheet" type="text/css" href="'.$adminskins_url.'chicago/rsc/css/chicago.css" />

				<style type="text/css">
				fieldset { margin:0 60px; padding:0; border:none !important }
				.fieldset { border:none !important }
				.embed { text-align:center }
				table.grouped tr.group td { color:#000 }
				.content { width: 70%; margin: 0 auto }
				.whitebox_center { margin:10px; padding:15px; background-color:#fff; border:2px #555 solid }
				</style>';

			require_js( 'functions.js');
			require_js( 'form_extensions.js');
			require_js( 'rollovers.js' );
			require_js( 'dynamic_select.js' );
			require_js( 'admin.js' );

			if( $params['jquery'] ) require_js( '#jquery#' );

			if( $params['checkboxes'] )
			{
				add_js_headline('
				var allchecked = Array();
				var idprefix;

				function toggleCheckboxes(the_form, the_elements, set_name )
				{
					if( typeof set_name == "undefined" )
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
					var elems_cnt = (typeof(elems.length) != "undefined") ? elems.length : 0;
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
					if( typeof(allchecked[set_name]) == "undefined" || typeof(set) != "undefined" )
					{ // init
						allchecked[set_name] = set;
					}

					if( allchecked[set_name] )
					{
						var replace = document.createTextNode("uncheck all");
					}
					else
					{
						var replace = document.createTextNode("check all");
					}

					if( document.getElementById( idprefix+"_"+String(set_name) ) )
					{
						document.getElementById( idprefix+"_"+String(set_name) ).replaceChild(replace, document.getElementById( idprefix+"_"+String(set_name) ).firstChild);
					}
					//else alert("no element with id "+idprefix+"_"+String(set_name));
				}

				function initcheckall( htmlid, init )
				{
					// initialize array
					allchecked = Array();
					idprefix = typeof(htmlid) == "undefined" ? "checkallspan" : htmlid;

					for( var lform = 0; lform < document.forms.length; lform++ )
					{
						for( var lelem = 0; lelem < document.forms[lform].elements.length; lelem++ )
						{
							if( document.forms[lform].elements[lelem].id.indexOf( idprefix ) == 0 )
							{
								var index = document.forms[lform].elements[lelem].name.substring( idprefix.length+2, document.forms[lform].elements[lelem].name.length );
								if( document.getElementById( idprefix+"_state_"+String(index)) )
								{
									setcheckallspan( index, document.getElementById( idprefix+"_state_"+String(index)).checked );
								}
								else
								{
									setcheckallspan( index, init );
								}
							}
						}
					}
				}');
			}

			include_headlines();
			echo '</head><body><div class="content"><div class="whitebox_center">';

			$out = ob_get_clean();
		}
		elseif( $what == 'footer' )
		{
			$out = '</div></div></body></html>';
		}

		return $out;
	}


	// Trim array
	function array_trim( $array )
	{
		return array_map( 'trim', $array );
	}


	function get_user_name_email( $user_ID, $style = 'text' )
	{
		if( function_exists('get_UserCache') )
		{	// b2evo 4
			$UserCache = & get_UserCache();
		}
		else
		{
			$UserCache = & get_Cache('UserCache');
		}

		$name = '[deleted]';
		$email = '';
		if( ($User = $UserCache->get_by_ID( $user_ID, false, false )) !== false )
		{
			if( $style == 'html' && method_exists($User, 'get_identity_link') )
			{
				$name = $User->get_identity_link();
			}
			else
			{
				$name = $User->get_preferred_name();
			}
			$email = $User->get('email');
		}
		return array( $name, $email );
	}


	function display_results()
	{
		global $DB, $BlogCache, $UserCache, $ItemCache;

		$BlogCache = & get_BlogCache();
		$ItemCache = & get_ItemCache();
		$UserCache = & get_UserCache();

		$Form = new Form( 'admin.php', '', 'post' );
		$Form->begin_form( 'fform' );
		$Form->hidden_ctrl();
		$Form->hiddens_by_key( get_memorized() );

		$checkall = param( 'checkall', 'integer', 0 );  // Non-Javascript-CheckAll

		$SQL = new SQL();

		$SQL->SELECT( '*' );
		$SQL->FROM( $this->get_sql_table('subs') );

		/*
		 * Query filter keywords:
		 */
		$keywords = param( 'keywords', 'string', '', true );
		$filter = param( 'filter', 'string', '', true );

		switch( $filter )
		{
			case 'blog-sub':
				$SQL->WHERE('sub_blog_id > 0');
				break;

			case 'item-sub':
				$SQL->WHERE('sub_item_id > 0');
				break;

			case 'blog-sub-u':
				$SQL->WHERE('sub_blog_id > 0 AND sub_item_id = 0');
				break;

			case 'item-sub-u':
				$SQL->WHERE('sub_item_id > 0 AND sub_blog_id = 0');
				break;

			case 'visitor-sub':
				$SQL->WHERE('sub_user_name != "" AND sub_user_email NOT REGEXP "^[0-9]+$"');
				break;

			case 'user-sub':
				$SQL->WHERE('sub_user_email REGEXP "^[0-9]+$"');
				break;
		}

		if( !empty( $keywords ) )
		{
			$SQL->add_search_field( 'sub_user_name' );
			$SQL->add_search_field( 'sub_user_email' );
			$SQL->WHERE_keywords( $keywords, 'AND' );
		}

		$action = param( 'action', 'string' );
		$action_url = 'admin.php?ctrl=tools&amp;tab=plug_ID_'.$this->ID;

		$total = $DB->get_var( 'SELECT COUNT(DISTINCT sub_user_email) FROM '.$this->get_sql_table('subs') );

		$Results = new Results( $SQL->get(), 'row_', 'D', 20 );
		$Results->title = '<div style="float:right">'.$this->T_('Unique users').': '.$total.'</div><div style="float:left">'.$this->T_('Subscribtions').' ('.$Results->total_rows.')</div>';

		function filter_keywords( & $Form )
		{
			$Form->text( 'keywords', get_param('keywords'), 20, T_('Keywords'), T_('Separate with space'), 50 );
		}
		$Results->filter_area = array(
			'callback' => 'filter_keywords',
			'url_ignore' => 'row_page,keywords',
			'presets' => array(
				'all' => array($this->T_('All'), '?ctrl=tools&amp;tab=plug_ID_'.$this->ID ),
				'user-sub' => array($this->T_('Users'), '?ctrl=tools&amp;tab=plug_ID_'.$this->ID.'&amp;filter=user-sub' ),
				'visitor-sub' => array($this->T_('Visitors'), '?ctrl=tools&amp;tab=plug_ID_'.$this->ID.'&amp;filter=visitor-sub' ),
				'blog-sub' => array($this->T_('Blog subs'), '?ctrl=tools&amp;tab=plug_ID_'.$this->ID.'&amp;filter=blog-sub' ),
				'blog-sub-u' => array($this->T_('Blog subs only'), '?ctrl=tools&amp;tab=plug_ID_'.$this->ID.'&amp;filter=blog-sub-u' ),
				'item-sub' => array($this->T_('Post subs'), '?ctrl=tools&amp;tab=plug_ID_'.$this->ID.'&amp;filter=item-sub' ),
				'item-sub-u' => array($this->T_('Post subs only'), '?ctrl=tools&amp;tab=plug_ID_'.$this->ID.'&amp;filter=item-sub-u' ),
				)
			);



		// Save plugin into global
		$GLOBALS['SubscriptionsPlugin'] = & $this;

		function rows_results_td_box( $row_ID )
		{
			global $checkall;

			// Checkbox
			$r = '<span name="surround_check" class="checkbox_surround_init">';
			$r .= '<input title="'.$GLOBALS['SubscriptionsPlugin']->T_('Select this row').'" type="checkbox" class="checkbox"
						name="target_ids[]" value="'.$row_ID.'" id="target_id_'.$row_ID.'"';

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
				'td' => '% rows_results_td_box( #sub_id# ) %',
				'td_class' => 'checkbox firstcol shrinkwrap',
				'order' => 'sub_id',
			);

		function rows_results_td_user( $row )
		{
			if( is_numeric($row->sub_user_email) )
			{	// This is a member, get name and email from their profile
				list($user_name, $user_email) = $GLOBALS['SubscriptionsPlugin']->get_user_name_email($row->sub_user_email, 'html');
				$title = 'user';
			}
			else
			{
				$user_email = $row->sub_user_email;
				$user_name = $row->sub_user_name;
				$title = 'visitor';
			}
			return '<span class="note">[<a href="mailto:'.urlencode($user_email).'">'.$title.'</a>]</span> '.$user_name;
		}
		$Results->cols[] = array(
				'th' => T_('User'),
				'td' => '% rows_results_td_user( {row} ) %',
			);

		function rows_results_td_blog( $row )
		{
			global $BlogCache;

			if( empty($row->sub_blog_id) ) return '<span class="note">[no]</span>';

			$Blog = $BlogCache->get_by_ID($row->sub_blog_id, false, false);
			if( !empty($Blog) )
			{
				$r = '<a href="'.$Blog->get('url').'" target="_blank">'.$Blog->dget('shortname').'</a>';
			}
			else
			{
				$r = '<span class="note">[deleted blog #'.$row->sub_blog_id.']</span>';
			}
			return $r;
		}
		$Results->cols[] = array(
				'th' => T_('Blog'),
				'td' => '% rows_results_td_blog( {row} ) %',
				'order' => 'sub_blog_id',
			);

		function rows_results_td_item( $row )
		{
			global $ItemCache;

			if( empty($row->sub_item_id) ) return '<span class="note">[no]</span>';

			$Item = $ItemCache->get_by_ID($row->sub_item_id, false, false);
			if( !empty($Item) )
			{
				$r = '<a href="'.$Item->get_permanent_url().'">'.$Item->dget('title').'</a>';
			}
			else
			{
				$r = '<span class="note">[deleted item #'.$row->sub_item_id.']</span>';
			}
			return $r;
		}
		$Results->cols[] = array(
				'th' => T_('Post'),
				'td' => '% rows_results_td_item( {row} ) %',
				'order' => 'sub_item_id',
			);

		$Results->cols[] = array(
				'th' => $this->T_('Date Time'),
				'td' => '%mysql2localedatetime_spans( #sub_date# )%',
				'th_class' => 'shrinkwrap',
				'td_class' => 'timestamp',
				'order' => 'sub_date',
			);

		function row_td_actions( $row )
		{
			$r = action_icon( $GLOBALS['SubscriptionsPlugin']->T_('Unsubscribe'), 'delete', $GLOBALS['SubscriptionsPlugin']->get_unsubscribe_url($row), '', 5, 1, array( 'target'=>'_blank', 'onclick'=>'return confirm(\''.$GLOBALS['SubscriptionsPlugin']->T_('Do you really want to unsubscribe?').'\')' ) );
			//$r .= action_icon( T_('Delete'), 'delete', regenerate_url( 'action,row_ID', 'row_ID='.$row->sub_ID.'&amp;action=delete' ), '', 5, 1, array( 'onclick' => 'return confirm(\''.T_('Do you really want to delete it?').'\')' ) );

			return $r;
		}
		$Results->cols[] = array(
				'th' => $this->T_('Actions'),
				'td' => '% row_td_actions({row}) %',
				'th_class' => 'shrinkwrap',
				'td_class' => 'shrinkwrap',
			);

		$Results->display();

		if( $Results->total_rows < 1 )
		{
			$Form->end_form();
			return;
		}

		echo '<div class="notes" style="margin:1px auto 10px 4px">'.$Form->check_all().' '.$this->T_('Check/Uncheck all').'</div>';

		$Form->end_form( array( array(
				'value' => $this->T_('Delete selected items !'),
				'onclick' => 'return confirm(\''.$this->T_('Do you really want to continue?').'\')',
				'name' => 'action_delete',
			) ) );

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

?>