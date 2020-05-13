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


require_once(dirname(__FILE__).'/_general.funcs.php');

if( function_comp( 'scan_dir', 6 ) ) {

	function scan_dir ($dir, $pattern = NULL, $action = NULL, $prepend = '', $return_format = NULL)
	{	/*
		 * This function scans the directory then for files that match the given details, perform a action on them
		 * EXPORT: An array containing the location of the files which the action was performed on.
		 *
 		 * @author Benjamin "balupton" Lupton {@link http://www.balupton.com}
		 * @category resourceLibraryFunction
		 * @package baluptonResourceLibrary
		 * @version 6
	     * @copyright (c) 2007 Benjamin "balupton" Lupton {@link http://www.balupton.com}
		 * @license Attribution-Share Alike 2.5 Australia - {@link http://creativecommons.org/licenses/by-sa/2.5/au/}
		 */
		
		// If we want to include the [ files || dirs ] in the output we make
		//		[ $file_pattern = true or $file_pattern = string || $dir_pattern = true or $dir_pattern = string ]
		// If we do not want to include a [ dir || file ] we do 
		//		[ $file_pattern  = false || $dir_pattern = false ]
		// If we do not want to recurse in the action set
		//		$continue = true
		
		// Create Valid Directory
		$dir = realpath($dir);
		if ( !$dir )
			return array();
		$dir = str_replace('\\', '/', $dir);
		if ( is_dir($dir) )
			$dir .= '/';
		
		// Get on with the script
		$files = array(); // Define our array to return
		if ( $return_format === 'seperate' )
		{	// Add extra
			$files['dirs'] = $files['files'] = array();
		}
		
		// Set defaults
		$file_pattern = $dir_pattern = $both_pattern = true;
		$both_action = $file_action = $dir_action = NULL;
		
		// Handle pattern
		if ( !empty($pattern) )
		// We have a pattern
		switch ( $pattern )
		{	// Replace the pattern if it is predefined
			case 'php':
				$file_pattern = '/^(.+)\.php$/';
				$dir_pattern = NULL;
				break;
			case 'inc_php':
				$file_pattern = '/^(_.+)\.php$/';
				$dir_pattern = NULL;
				break;
			case 'image':
				$file_pattern = '/^(.+)\.(jpg|jpeg|gif|png|tiff|bmp|xbmp)$/i';
				$dir_pattern = NULL;
				break;
			case 'file':
			case 'files':
				$dir_pattern = NULL;
				break;
			case 'directory':
			case 'directories':
				$file_pattern = NULL;
				break;
			default:
				if ( is_array($pattern) )
				{
					$file_pattern_exists = array_key_exists('file', $pattern);
					if ( $file_pattern_exists )	$dir_pattern = NULL;
					
					$dir_pattern_exists = array_key_exists('dir',  $pattern);
					if ( $dir_pattern_exists )	$file_pattern = NULL;
					
					$both_pattern_exists = array_key_exists('both', $pattern);
					
					if ( $file_pattern_exists )	$file_pattern = $pattern['file'];
					if ( $dir_pattern_exists  )	$dir_pattern  = $pattern['dir'];
					if ( $both_pattern_exists )	$both_pattern = $pattern['both'];
				}
				else
				{
					$file_pattern = $pattern;
					$dir_pattern = NULL;
				}
				break;
		}
		$pattern = array('both'=>$both_pattern,'file'=>$file_pattern,'dir'=>$dir_pattern);
		
		// Handle action
		if ( !empty($action) )
		// We have a pattern
		switch ( $action )
		{	// Replace the pattern if it is predefined
			case 'no_recurse':
				$dir_action = 'if ( $dir !== \''.$dir.'\' ) { $skip = true; }';
				break;
			default:
				if ( is_array($action) )
				{
					if ( isset($action['file']) )	$file_action = $action['file'];
					if ( isset($action['dir']) )	$dir_action  = $action['dir'];
					if ( isset($action['both']) )	$both_action = $action['both'];
				}
				else
				{
					$file_action = $action;
				}
				break;
		}
		$action = array('both'=>$both_action,'file'=>$file_action,'dir'=>$dir_action);
		
		// Get down to business
		$both_matches = $file_matches = $dir_matches = array();
		if ( $dh = opendir($dir) )
		{	// Open the directory
			// Go through the directory and include the files that match the given regular expression
			while (($file = readdir($dh)) !== false)
			{	// Cycle through files
				$skip = false;
				$path = $dir.$file;
				if ( !empty($file) && substr($file,0,1) != '.' )
				{	// We have a file or directory
				
					// Check
					if ( $both_pattern === true || $both_pattern === NULL || ($both_pattern !== false && preg_match($both_pattern, $file, $both_matches)) )
					{	// passed check
					} else
					{	// failed check
						continue; // continue to next file
					}
					
					// Perform custom action
					eval($both_action); // Custom action
					if ( $skip )	continue;
					
					// Continue with specifics
					if ( is_file($path) )
					{	// We have a file
						
						// Check
						if ( $file_pattern === true || $file_pattern === NULL || ($file_pattern !== false && preg_match($file_pattern, $file, $file_matches)) )
						{	// passed check
						} else
						{	// failed check
							continue; // continue to next file
						}
						
						// Perform custom action
						eval( $file_action ); // Custom action
						if ( $skip )	continue;
						
						// Return
						if ( $file_pattern !== NULL )
						// We want to return, so it is either TRUE or STRING as if it was FALSE we would of continued
						switch ( $return_format )
						{	// Work with the return
						
							case 'seperate':
								$files['files'][] = $prepend.$file; // Append the file location to the array to be returned
								break;
							
							case 'tree2':
								$filename = $file;
								$end = strrpos($filename, '.');
								if ( $end !== -1 )
									$filename = substr($filename, 0, $end);
								$files[$filename] = $prepend.$file; // Append the file location to the array to be returned
								break;
						
							case 'tree':
								$files[] = $file; // Append the file name to the array to be returned
								/*
								$filename = $file;
								$end = strrpos($filename, '.');
								if ( $end !== -1 )
									$filename = substr($filename, 0, $end);
								$files[$filename] = $prepend.$file; // Append the file location to the array to be returned
								*/
								break;
							
							default:
								$files[] = $prepend.$file; // Append the file location to the array to be returned
								break;
						}
					}
					elseif ( is_dir($path) )
					{	// We have a dir
						
						// Check
						if ( $dir_pattern === true || $dir_pattern === NULL || ($dir_pattern !== false && preg_match($dir_pattern, $file, $dir_matches)) )
						{	// passed check
						} else
						{	// failed check
							continue; // continue to next file
						}
						
						// Perform custom action
						eval( $dir_action ); // Custom action
						if ( $skip )	continue;
						
						// Return
						switch ( $return_format )
						{	// Work with the return		
										
							case 'seperate':
								if ( $dir_pattern !== NULL )
								{	// We want to return, so it is either TRUE or STRING as if it was FALSE we would of continued
									$files['dirs'][] = $prepend.$file; // Append the file location to the array to be returned
								}
								$scan_dir = scan_dir($path, $pattern, $action, $prepend.$file.'/', $return_format);
								$files['files'] = array_merge($files['files'], $scan_dir['files']);
								$files['dirs'] = array_merge($files['dirs'], $scan_dir['dirs']);
								unset($scan_dir);
								break;
							
							case 'tree2':
							case 'tree':
								$files[$file] =
									scan_dir($path, $pattern, $action, $prepend.$file.'/', $return_format);
								break;
							
							default:
								if ( $dir_pattern !== NULL )
								{	// We want to return, so it is either TRUE or STRING as if it was FALSE we would of continued
									$files[] = $prepend.$file; // Append the file location to the array to be returned
								}
								$files = array_merge(
									$files,
									scan_dir($path, $pattern, $action, $prepend.$file.'/', $return_format)
								);
								break;
						}
					} // end file or dir compare
					
				} // end is file or dor
				
			} // end while
			
			closedir($dh); // Close the directory
			
		} // end open dir
		
		return $files;
		
	} // END: scan_dir
	
} // END: function_exists

?>