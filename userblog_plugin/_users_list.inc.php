<?php
// ===============================================================================
// User blog column
global $Settings, $current_User, $Plugins;

$userblog_Plugin = & $Plugins->get_by_code( 'evo_userblog' );

if ( ! empty($userblog_Plugin) && $userblog_Plugin->status == 'enabled' )
{
	function get_userblog_id( & $row )
	{
		global $UserSettings, $BlogCache, $Plugins, $current_User, $admin_url;
		
		$userblog_Plugin = & $Plugins->get_by_code( 'evo_userblog' );
							
		$user_ID = $row->user_ID;
		$user_blog_ID = $UserSettings->get('userblog_created', $user_ID);
		
		if( isset($user_blog_ID) )
		{
			switch ( $user_blog_ID )
			{
				case 'wanted':
					$r = '<a href="'.$admin_url.'?ctrl=users&amp;userblog=new&amp;userID='.$user_ID.'">'.$userblog_Plugin->T_('Create blog').'</a>';
					break;
				
				case is_numeric($user_blog_ID):
					$user_blog = & $BlogCache->get_by_ID( $user_blog_ID, false );
					
					if ( $user_blog )
					{
						$r = $user_blog->get('name').'<br><br>'.
						action_icon( $userblog_Plugin->T_('Edit this blog'), 'edit', $admin_url.'?ctrl=coll_settings&amp;blog='.$user_blog_ID ).
						action_icon( $userblog_Plugin->T_('Delete this blog'), 'delete', $admin_url.'?ctrl=collections&amp;action=delete&amp;blog='.$user_blog_ID );
					}
					else
					{
						$r = '<a href="'.$admin_url.'?ctrl=users&amp;userblog=wanted&amp;userID='.$user_ID.'">'.
								$userblog_Plugin->T_('Fix user settings').'</a><br>('.$userblog_Plugin->T_('Set user blog to &quot;wanted&quot;').')';
					}
					break;
			}
			return $r;
		}
		else
		{	
			$r = '<a href="'.$admin_url.'?ctrl=users&amp;userblog=wanted&amp;userID='.$user_ID.'">'.
					$userblog_Plugin->T_('Fix user settings').'</a><br>('.$userblog_Plugin->T_('Set user blog to &quot;wanted&quot;').')';
			return $r;
		}
	}
	
	if ( $current_User->group_ID == 1 )
	{
		$Results->cols[] = array(
								'th' => $userblog_Plugin->T_('User Blog'),
								'td_class' => 'shrinkwrap',
								'td' => '¤conditional( (#grp_ID# == '.$Settings->get('userblog_grp_ID').'), \'%get_userblog_id( {row} )%\' )¤',
							);						
	}
}
// ===============================================================================
?>