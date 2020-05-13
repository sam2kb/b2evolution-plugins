<?php
/**
 * This file implements the Extra fields plugin for {@link http://b2evolution.net/}.
 *
 * @copyright (c)2010 by Sonorth Corp. - {@link http://b2evo.sonorth.com/}.
 * @license GNU General Public License 2 (GPL) - http://www.opensource.org/licenses/gpl-license.php
 *
 * @author Sonorth Corp.
 */
if( !defined('EVO_MAIN_INIT') ) die( 'Please, do not access this page directly.' );

//$kind = get_class(obj);

/*if( is_a( $obj, 'Item' )
{
// foo
}
elseif( is_a( $obj, 'Blog') )
{
// bar
} */

class extra_fields_plugin extends Plugin
{
	var $name = 'Extra fields';
	var $code = 'xtraFields';
	var $priority = 65;
	var $version = '0.2';
	var $group = 'Sonorth Corp.';
	var $author = 'Sonorth Corp.';
	var $author_url = 'http://b2evo.sonorth.com';
	var $help_url = 'http://forums.b2evolution.net/viewtopic.php?t=';
	
	var $apply_rendering = 'none';
	var $number_of_installs = 1;
	
	// Blog extra fields
	var $blog_fields = array(
			'url' => array(						// Field name ( no spaces or special characters )
				'type' => 'string',				// Field type ( 'string' or 'html' )
				'title' => 'URL',				// Field title ( HTML allowed )
				'notes' => 'Some notes here',	// Field notes ( HTML allowed )
			),
			'notes' => array(
				'type' => 'html',
				'title' => 'Notes',
				'notes' => '',
			),
		);
	
	// Post extra fields
	var $item_fields = array(
			'images' => array(					// Field name ( no spaces or special characters )
				'type' => 'integer',			// Field type ( 'string' or 'html' )
				'title' => 'Images',			// Field title ( HTML allowed )
				'notes' => '',					// Field notes ( HTML allowed )
			),
			'videos' => array(
				'type' => 'integer',
				'title' => 'Videos',
				'notes' => '',
			),
			/*'notes1' => array(
				'type' => 'html',
				'title' => 'Notes',
				'notes' => '',
			),
			'notes2' => array(
				'type' => 'html',
				'title' => 'Notes 2',
				'notes' => '',
			),*/
		);
	
	
	/**
	* Init
	*
	* This gets called after a plugin has been registered/instantiated.
	*/
	function PluginInit( & $params )
	{
		$this->short_desc = $this->T_('Allows you to add custom fields to blogs and posts');
		$this->long_desc = $this->short_desc;
	}
	
	
	function GetDbLayout()
	{
		return array(
			"CREATE TABLE IF NOT EXISTS ".$this->get_sql_table('blog')." (
					field_ID int(11) NOT NULL auto_increment,
					field_blog_ID int(11) NOT NULL,
					field_name varchar(255) NOT NULL,
					field_value_string varchar(255) NOT NULL,
					field_value_html text NOT NULL,
					PRIMARY KEY field_ID ( field_ID ),
					INDEX field_blog_ID ( field_blog_ID )
			  )",
			
			"CREATE TABLE IF NOT EXISTS ".$this->get_sql_table('item')." (
					field_ID int(11) NOT NULL auto_increment,
					field_item_ID int(11) NOT NULL,
					field_name varchar(255) NOT NULL,
					field_value_string varchar(255) NOT NULL,
					field_value_html text NOT NULL,
					PRIMARY KEY field_ID ( field_ID ),
					INDEX field_item_ID ( field_item_ID )
			  )",
		);
	}
	
	
	/**
	 * Define settings that the plugin uses/provides.
	 */
	function GetDefaultSettings()
	{
		return array(
				'blog_fields' => array(
					'label' => $this->T_('Blog extra fields'),
					'type' => 'checkbox',
					'defaultvalue' => 0,
					'note' => 'This will add extra fields to the blog general settings tab.',
				),
				'item_fields' => array(
					'label' => $this->T_('Item extra fields'),
					'type' => 'checkbox',
					'defaultvalue' => 1,
					'note' => 'This will add extra fields to the post edit form.',
				),
			);
	}


	//function AfterBlogUpdate( $params = array() )
	function AdminAfterMenuInit()
	{
		if( $this->Settings->get('blog_fields') && $blog = param( 'extra_fields_plugin', 'integer' ) )
		{
			foreach( $this->blog_fields as $field_name => $field )
			{
				$value = param( 'extra_fields_plugin_'.$field_name, $field['type'] );
				
				if( $this->update_field( $field_name, $value, $field['type'], $blog ) )
				{
					$this->msg( sprintf( 'The field &laquo;%s&raquo; has been saved', $field['title'] ), 'success' );
				}
			}
		}
	}
	
	
	function AfterItemInsert( $params = array() )
	{
		$this->AfterItemUpdate( $params ); // exactly the same action
	}


	function AfterItemUpdate( $params = array() )
	{
		global $DB;
		
		if( !$this->Settings->get('item_fields') ) return;
		
		foreach( $this->item_fields as $field_name => $field )
		{
			$value = param( 'extra_fields_plugin_'.$field_name, $field['type'] );
			
			if( $this->update_field( $field_name, $value, $field['type'], $params['Item']->ID, 'item' ) )
			{
				$this->msg( sprintf( 'The field &laquo;%s&raquo; has been saved', $field['title'] ), 'success' );
			}
		}
	}


	function AfterItemDelete( $params = array() )
	{
		return $this->delete_fields( $params['Item']->ID, 'item' );
	}
	
	
	function AfterBlogDelete( $params = array() )
	{
		return $this->delete_fields( $params['Blog']->ID, 'blog' );
	}
	
	
	/**
	 * Event handler: Called when ending the admin html head section.
	 *
	 * @param array Associative array of parameters
	 * @return boolean did we do something?
	 */
	function AdminAfterPageFooter( & $params )
	{
		if( !$this->Settings->get('blog_fields') ) return false;
		
		global $ctrl, $tab, $action, $blog;
		
		if( $ctrl == 'coll_settings' && $tab == 'general' && !empty($blog) )
		{
			echo '<div id="extra_fields_plugin_fields">';
			
			$Form = new Form();
			$Form->output = 0;
			
			echo $Form->begin_fieldset( $this->T_('Extra fields') );
			echo '<input name="extra_fields_plugin" value="'.$blog.'" type="hidden" />';
			
			foreach( $this->blog_fields as $field_name => $field )
			{
				switch($field['type'])
				{
					case 'string':
						echo $Form->text( 'extra_fields_plugin_'.$field_name,
										 $this->get_field($field_name, 'blog'), 50, $field['title'], $field['notes'], 255 );
						break;
					
					case 'html':
						echo $Form->textarea( 'extra_fields_plugin_'.$field_name,
										 $this->get_field($field_name), 3, $field['title'], $field['notes'], 50, 'large' );
						break;
				}
			}
			
			echo $Form->end_fieldset();
			$Form->end_form();
			echo '</div>';
			?>
            
            <script type="text/javascript">
			//<![CDATA[
				jQuery(document).ready(function()
				{	// Let's do our stuff
					jQuery('div#extra_fields_plugin_fields').insertBefore('form.fform > fieldset:has(input.SaveButton)');
				});
			//]]>
			</script>
            
            <?php
		}
		return true;
	}
	
	
	function AdminDisplayItemFormFieldset( & $params )
	{
		if( !$this->Settings->get('item_fields') ) return;
		if( $params['edit_layout'] == 'simple' ) return;
		
		echo '<div id="extra_fields_plugin_fields">';
		
		$params['Form']->begin_fieldset( $this->T_('Extra fields') );
		
		echo '<table cellspacing="0" class="compose_layout">';
		foreach( $this->item_fields as $field_name => $field )
		{
			switch($field['type'])
			{
				case 'string':
					echo '<tr><td width="97%"><label for="extra_fields_plugin_'.$field_name.'"><strong>'.$field['title'].':</strong> <span class="notes">'.$field['notes'].'</span></label><br />';
					$params['Form']->text_input( 'extra_fields_plugin_'.$field_name, $this->get_field($field_name, 'item'), 20, '', '', array('maxlength'=>255, 'style'=>'width: 100%;') );
					echo '</td><td width="1"><!-- for IE7 --></td></tr>';
					
					break;
				
				case 'integer':
					echo '<tr><td class="label"><label for="extra_fields_plugin_'.$field_name.'"><strong>'.$field['title'].':</strong></label></td><td class="input">';
					$params['Form']->text_input( 'extra_fields_plugin_'.$field_name, $this->get_field($field_name, 'item'), 15, '', $field['notes'], array('maxlength'=>11) );
					echo '</td></tr>';
					break;
				
				case 'html':					
					echo '<tr><td class="label" colspan="2"><label for="extra_fields_plugin_'.$field_name.'"><strong>'.$field['title'].':</strong> <span class="notes">'.$field['notes'].'</span></label><br />
							 <textarea name="extra_fields_plugin_'.$field_name.'" rows="2" cols="25" class="large" id="extra_fields_plugin_'.$field_name.'">'.$this->get_field($field_name, 'item').'</textarea>
						  </td></tr>';
					
					break;
			}
		}
		echo '</table>';
		
		$params['Form']->end_fieldset();
		echo '</div>';
		
		return true;
	}
	
	
	function get_field( $field_name, $kind = 'blog', $ID = NULL )
	{
		global $DB;
		
		switch( $kind )
		{
			case 'blog':
				global $blog;
				
				if( empty($this->blog_fields[$field_name]) ) return 'N/A';
				if( empty($ID) ) $ID = $blog;
				
				$type = $this->blog_fields[$field_name]['type'];
				if( $type == 'integer' )
				{
					$type = 'string';
				}
		
				$SQL = 'SELECT field_value_'.$type.'
						FROM '.$this->get_sql_table($kind).'
						WHERE field_'.$kind.'_ID = '.$DB->quote($ID).'
						AND field_name = '.$DB->quote($field_name);
				break;
			
			case 'item':
				global $Item, $edited_Item;;
				
				if( empty($this->item_fields[$field_name]) ) return 'N/A';
				if( empty($ID) )
				{
					if( !empty($Item) )
					{
						$ID = $Item->ID;
					}
					elseif( !empty($edited_Item) )
					{
						$ID = $edited_Item->ID;
					}
				}
				if( empty($ID) ) return ''; // New post
				
				$type = $this->item_fields[$field_name]['type'];
				if( $type == 'integer' )
				{
					$type = 'string';
				}
				
				$SQL = 'SELECT field_value_'.$type.'
						FROM '.$this->get_sql_table($kind).'
						WHERE field_'.$kind.'_ID = '.$DB->quote($ID).'
						AND field_name = '.$DB->quote($field_name);
				break;
			
			default:
				return '';
		}
		return $DB->get_var($SQL);
	}
	
	
	function update_field( $name, $value = '', $type, $ID, $kind = 'blog' )
	{
		global $DB;
		
		$name = $DB->quote($name);
		$ID = $DB->quote($ID);
		
		if( $type == 'integer' )
		{
			$type = 'string';
		}
		
		$SQL = 'SELECT field_ID FROM '.$this->get_sql_table($kind).'
				WHERE field_'.$kind.'_ID = '.$ID.'
				AND field_name = '.$name;
		
		if( $field_ID = $DB->get_var($SQL) )
		{
			$SQL = 'UPDATE '.$this->get_sql_table($kind).'
					SET field_value_'.$type.' = "'.$DB->escape($value).'"
					WHERE field_ID = '.$DB->quote($field_ID);
		}
		else
		{
			if( $value == '' ) return false; // Don't create empty fields
			
			$SQL = 'INSERT INTO '.$this->get_sql_table($kind).'
						( field_name, field_'.$kind.'_ID, field_value_'.$type.' )
					VALUES (
						'.$name.',
						'.$ID.',
						"'.$DB->escape($value).'" )';
		}
		
		return $DB->query($SQL);
	}
	
	
	function delete_fields( $ID, $kind )
	{
		global $DB;
		
		return $DB->query( 'DELETE FROM '.$this->get_sql_table($kind).'
							WHERE field_'.$kind.'_ID = '.$DB->quote($ID) );
	}
}

?>