<?php
/**
 * Balupton's Resource Library
 *
 * This resource library is a collection of resources that balupton has developer (or collected) over time.
 * It allows him to develop high quality code fast and effeciently.
 *
 * @author Benjamin "balupton" Lupton {@link http://www.balupton.com}
 * @category resourceLibraryGroup
 * @package baluptonResourceLibrary
 * @copyright (c) 2007 Benjamin "balupton" Lupton {@link http://www.balupton.com}
 * @license Attribution-Share Alike 2.5 Australia - {@link http://creativecommons.org/licenses/by-sa/2.5/au/}
 */
if( !defined('EVO_MAIN_INIT') ) die( 'Please, do not access this page directly.' );


if ( ! function_exists('function_comp') ) {
	function function_comp( $f_name, $v_new, $set = true )
	{	/*
		 * Compares the version of a function
		 *
		 * @author Benjamin "balupton" Lupton {@link http://www.balupton.com}
		 * @category resourceLibraryFunction
		 * @package baluptonResourceLibrary
		 * @version 2
		 * @date December 12, 2006
	     * @copyright (c) 2007 Benjamin "balupton" Lupton {@link http://www.balupton.com}
		 * @license Attribution-Share Alike 2.5 Australia - {@link http://creativecommons.org/licenses/by-sa/2.5/au/}
		 */
		
		/*
		 * Changelog
		 *
		 * v2 - 24/12/2006 - Made it so it floors version numbers, if the number is the same then we don't worry.
		 * - Eg. If 2.0 was compared with 2.1 then no problem would happen, if 3 was compared with 2 or vice versa we do have a problem
		 * v1 - 29/07/2006
		 */
		
		/*
		 * Usage
		 *
		 * Returns:
		 * true		;	function has not been set yet
		 * false	;	functions are the same version
		 * NULL		;	functions are not the same version
		 */
		
		$v_name = $f_name.'_version';
		global $$v_name;
		
		if ( isset($$v_name) ) {
			$v_old = & $$v_name;
			if( floor($v_old) == floor($v_new) ) {
				return false;
			} else {
			
				/*if ( $v_old > $v_new ) {
					$start = '<!-- WARNING: Conflicting function versions:';
					$stop = '-->';
					$lb = "\r\n";
				} else {*/
					$start = '<h2>ERROR: Conflicting function versions:';
					$stop = '</h2>';
					$lb = '<br />'."\r\n";
				/*}*/
				
				echo
					$lb.
					$start.$lb.
					'Function ['.$f_name.']'.$lb.
					'Original   Version ['.$v_old.']'.$lb.
					'Attempted  Version ['.$v_new.']'.$lb.
					$stop.$lb;
					
				return NULL;
			}
		} else
		{	// If there is no original version number
			if ( function_exists($f_name) )
			{	// If the function already exists then we have a problem, as we do not know its version
				$start = '<h2>ERROR: Conflicting function versions:';
				$stop = '</h2>';
				$lb = '<br />'."\r\n";
				$v_old = 'Unknown';
				echo
					$lb.
					$start.$lb.
					'Function ['.$f_name.']'.$lb.
					'Original   Version ['.$v_old.']'.$lb.
					'Attempted  Version ['.$v_new.']'.$lb.
					$stop.$lb;
				return NULL;
			} else {
				if ( $set )	$$v_name = $v_new;
				return true;
			}
		}
	}
}

?>