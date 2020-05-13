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
require_once(dirname(__FILE__).'/_scan_dir.funcs.php');

if ( !function_exists('file_put_contents') )
{
	define('FILE_APPEND', 0);
	function file_put_contents($n, $d, $flag = false) {
		$mode = ($flag == FILE_APPEND || strtoupper($flag) == 'FILE_APPEND') ? 'a' : 'w';
		$f = @fopen($n, $mode);
		if ($f === false) {
			return 0;
		} else {
			if (is_array($d)) $d = implode($d);
			$bytes_written = fwrite($f, $d);
			fclose($f);
			return $bytes_written;
		}
	}
}

if( function_comp( 'become_file_download', 1 ) ) {
function become_file_download ( $file_path, $content_type = NULL, $buffer_size = 20000 )
{	/*
	 * Credits to pechkin at zeos dot net - {@link http://au3.php.net/manual/en/function.header.php#65667}
	 *
	 * @author Benjamin "balupton" Lupton {@link http://www.balupton.com}
	 * @category resourceLibraryFunction
	 * @package baluptonResourceLibrary
	 * @version 1
	 * @copyright (c) 2007 Benjamin "balupton" Lupton {@link http://www.balupton.com}
	 * @license Attribution-Share Alike 2.5 Australia - {@link http://creativecommons.org/licenses/by-sa/2.5/au/}
	 */
	
	if ( empty($content_type) )
		$content_type = 'application/force-download';
	
	// Define variables
	$fpath = $file_path;
	$fname = basename($file_path);
	$fsize = filesize($fpath);
	$bufsize = $buffer_size;
	
	if ( isset($_SERVER['HTTP_RANGE']) )
	{	// Partial download
		if( preg_match("/^bytes=(\\d+)-(\\d*)$/", $_SERVER['HTTP_RANGE'], $matches) )
		{	// Parsing Range header
			$from = $matches[1];
			$to = $matches[2];
			
			if( empty($to) )
			{
				$to = $fsize - 1;  // -1  because end byte is included
				//(From HTTP protocol:
				// 'The last-byte-pos value gives the byte-offset of the last byte in the range; that is, the byte positions specified are inclusive')
			}
			
			$content_size = $to - $from + 1;
			
			header("HTTP/1.1 206 Partial Content");
			header('Pragma: public');
			header('Cache-control: must-revalidate, post-check=0, pre-check=0');
			header('Expires: ' . gmdate('D, d M Y H:i:s') . ' GMT');
			header("Content-Range: $from-$to/$fsize");
			header("Content-Length: $content_size");
			header("Content-Type: $content_type");
			if ( $content_type == 'application/force-download' )
				header("Content-Disposition: attachment; filename=$fname");
			header("Content-Transfer-Encoding: binary");
	
		   if(file_exists($fpath) && $fh = fopen($fpath, "rb"))
		   {
			   fseek($fh, $from);
			   $cur_pos = ftell($fh);
			   while($cur_pos !== FALSE && ftell($fh) + $bufsize < $to+1)
			   {
				   $buffer = fread($fh, $bufsize);
				   print $buffer;
				   $cur_pos = ftell($fh);
			   }
	
			   $buffer = fread($fh, $to+1 - $cur_pos);
			   print $buffer;
	
			   fclose($fh);
		   }
		   else
		   {
			   header("HTTP/1.1 404 Not Found");
			   exit;
		   }
	   }
	   else
	   {
		   header("HTTP/1.1 500 Internal Server Error");
		   exit;
	   }
	}
	else // Usual download
	{
		// die ( $fpath.':::'.$fsize);
		header("HTTP/1.1 200 OK");
		header('Pragma: public');
		header('Cache-control: must-revalidate, post-check=0, pre-check=0');
		header('Expires: ' . gmdate('D, d M Y H:i:s') . ' GMT');
		header("Content-Length: $fsize");
		header("Content-Type: $content_type");
		if ( $content_type == 'application/force-download' )
		  header("Content-Disposition: attachment; filename=$fname");
		header("Content-Transfer-Encoding: binary");
		if( file_exists($fpath) ) 
		{
			readfile($fpath);
		}
		else
		{
		   header("HTTP/1.1 404 Not Found");
		}
	}
}
}

if( function_comp( 'filetype_human', 1 ) ) {
function filetype_human ( $file_path, $format = '%s (.%s)'  )
{	/*
	 * @author Benjamin "balupton" Lupton {@link http://www.balupton.com}
	 * @category resourceLibraryFunction
	 * @package baluptonResourceLibrary
	 * @version 1
	 * @copyright (c) 2007 Benjamin "balupton" Lupton {@link http://www.balupton.com}
	 * @license Attribution-Share Alike 2.5 Australia - {@link http://creativecommons.org/licenses/by-sa/2.5/au/}
	 */
	
	$types = array(
		'exe'	=> 'Application',
		'jpg'	=> 'Image',
		'jpeg'	=> 'Image',
		'png'	=> 'Image',
		'tiff'	=> 'Image',
		'bmp'	=> 'Image',
		'zip'	=> 'ZIP Archive',
		'rar'	=> 'WinRAR Archive',
		'pdf'	=> 'Adobe Reader Document',
		'doc'	=> 'Word Document',
		'txt'	=> 'Document',
		'rtf'	=> 'Rich Text File'
	);
	
	$extension = get_extension($file_path);
	if ( function_exists('mime_content_type') )
	{
		$mime_type = mime_content_type($file_path);
		$type = $mime_type;
	} else
	{
		$mime_type = false;
		$type = 'File';
	}
	
	if ( isset($this->types[$mime_type]) )
	{
		$type = $this->types[$mime_type];
		
	} elseif ( isset($this->types[$extension]) )
	{
		$type = $this->types[$extension];
	}
	
	return sprintf($format, $type, $extension);
}
}


if( function_comp( 'get_estimated_download_time', 1 ) ) {
	function get_estimated_download_time ( $size, $speed, $round_seconds = true, $formats = array() )
	{	/*
		 * @author Benjamin "balupton" Lupton {@link http://www.balupton.com}
		 * @category resourceLibraryFunction
		 * @package baluptonResourceLibrary
		 * @version 1
		 * @copyright (c) 2007 Benjamin "balupton" Lupton {@link http://www.balupton.com}
		 * @license Attribution-Share Alike 2.5 Australia - {@link http://creativecommons.org/licenses/by-sa/2.5/au/}
		 */
		
		if ( !isset($formats['format']) )
			$formats['format'] = '%1$s (Estimate based on %2$s)';
		if ( !isset($formats['hours']) )
			$formats['hours'] = '%s hour%s';
		if ( !isset($formats['minutes']) )
			$formats['minutes'] = '%s minute%s';
		if ( !isset($formats['seconds']) )
			$formats['seconds'] = '%s second%s';
		
		// Convert size into kilobytes
		$size /= 1000;
		
		// Convert speed into Kbps from Mbps
		if ( $speed < 56 )	// 1.5 = 1500
			$speed *= 100;
		
		// Convert speed into KBps
		$speed /= 8;
		
		// Set remaining size
		$remaining_size = $size;
		
		// Figure out the hours
		$max = $speed * 60 /* mins */ * 60 /* hours */;
		$hours = floor($remaining_size / $max);
		$remaining_size = $remaining_size % $max;
		
		// Figure out the minutes
		$max = $speed * 60 /* mins */;
		$minutes = floor($remaining_size / $max);
		$remaining_size = $remaining_size % $max;
		
		// Figure out the seconds
		$max = $speed /* seconds */;
		$seconds = ceil($remaining_size / $max);
		$remaining_size = 0;
		
		// Round it
		if ( $round_seconds )
		{
			if ( $seconds >= 30 )
				++$minutes;
			$seconds = 0;
		}
		
		// Times
		$times = array();
		if ( $hours )	$times['hours']		=  sprintf($formats['hours'], $hours, ($hours > 1 ? 's' : ''));
		if ( $minutes )	$times['minutes']	=  sprintf($formats['minutes'], $minutes, ($minutes > 1 ? 's' : ''));
		if ( $seconds )	$times['seconds']	=  sprintf($formats['seconds'], $seconds, ($seconds > 1 ? 's' : ''));
		$times = implode(', ', $times);
		if ( empty($times) )
			$times = 'Unknown';
		
		// Do display
		$result = 
			sprintf($formats['format'],	$times, ($speed*8).'Kbps');
		
		// Return
		return $result;
	}
}

global $FILESIZE_LEVELS;
if ( !isset($FILESIZE_LEVELS) )
	$FILESIZE_LEVELS = array('B', 'KB', 'MB', 'GB', 'TB', 'PB', 'EB', 'ZB', 'YB');

if( function_comp( 'filesize_human_from', 1 ) ) {
function filesize_human_from( $filesize_human ){
	global $FILESIZE_LEVELS;
	$levels = $FILESIZE_LEVELS;
	
	$filesize_human = strtoupper($filesize_human);
	
	$matches = array();
	$matches_length = preg_match('/([a-zA-Z]*)$/', $filesize_human, $matches);
	if ( $matches_length === 1 && in_array(($match = $matches[0]), $levels))
		$level = $match;
	else
		$level = 'MB';
		
	$filesize = floatval(trim(substr($filesize_human, 0, strlen($filesize_human)-strlen($level))));
	
	$depth = array_search($level, $levels);
	++$depth; // B should be 1
	
	for ( $i = 0, $n = $depth-1; $i < $n; ++$i )
		$filesize *= 1024;
	
	return $filesize;
} }

if( function_comp( 'filesize_human', 1 ) ) {
function filesize_human( $size, $decimal_places = 3, $place_holder = 0.100 )
{	/*
	 * Original goes to {@link http://au3.php.net/manual/en/function.filesize.php#64387}
	 *
	 * @author Benjamin "balupton" Lupton {@link http://www.balupton.com}
	 * @category resourceLibraryFunction
	 * @package baluptonResourceLibrary
	 * @version 1.2
	 * @copyright (c) 2007 Benjamin "balupton" Lupton {@link http://www.balupton.com}
	 * @license Attribution-Share Alike 2.5 Australia - {@link http://creativecommons.org/licenses/by-sa/2.5/au/}
	 */
	
	/*
	 * Changelog
	 *
	 * v1.2
	 * - added place_holder, should be 1 by default but client required 0.100
	 */
	
	global $FILESIZE_LEVELS;
	$levels = $FILESIZE_LEVELS;
	
	$level = 0;
	while ( ($new_size = $size / 1024) > $place_holder )
	{
		$size = $new_size;
		++$level;
	}
	$filesize_human = substr($size,0,strpos($size,'.')+$decimal_places).' '.$levels[$level];
	return $filesize_human;
} }

if( function_comp( 'dirsize', 1 ) ) {
function dirsize ( $dir_path )
{	// Get the total filesize of a directory
	$dir_path = create_valid_path($dir_path);	
	$files = scan_dir($dir_path);
	$dirsize = 0;
	//if ( empty($files) )
	//	return 0;
	for ( $i = 0, $n = sizeof($files); $i < $n; ++$i )
	{
		$file_path = $dir_path.$files[$i];
		$filesize = filesize($file_path);
		$dirsize += $filesize;
	}
	return $dirsize;
}
}

if( function_comp( 'get_filename', 2 ) ) {
function get_filename( $file, $with_extension = true ) {
	$file = basename($file);
	if ( !$with_extension )
	{
		$end = strrpos($file,'.');
		if ( $end != false )
			$file = substr($file,0,$end);
	}
	return $file;
} }

if( function_comp( 'get_extension', 1 ) ) {
function get_extension( $file ) {
	$end = strrpos($file,'.');
	if ( $end != false )
		return substr($file,$end+1);
	else
		return '';
} }

if( function_comp( 'create_valid_filename', 1 ) ) {
function create_valid_filename( $name ) {
	return preg_replace( "/[^\w\.-]+/", '_', $name );
} }

if( function_comp( 'create_valid_path', 2 ) ) {
function create_valid_path ( $path )
{	// Changes \\ to /, and adds a / to the end if it's not already not present
	$path = str_replace( '\\', '/', $path );
	
	if( substr( $path, strlen($path)-1 ) != '/' )
	{
		$filename = get_filename($path);
		if( is_dir($path) || !strstr($filename,'.') )
			$path .= '/';
	}
	
	return $path;
} }


if( function_comp( 'unlink_dir', 3 ) ) {
	function unlink_dir($dir)
	{	// Removes a directory and all subfiles, modded by balupton
		$dir = create_valid_path($dir);
		if ($handle = @opendir($dir)) {
			while ( false !== ($item = @readdir($handle)) ) {
				switch( $item )
				{
					case '.':
					case '..':
						break;
					default:
						$c = $dir.$item;
						if ( is_dir($c) )
							unlink_dir($c);
						else
							unlink($c);
				}
			}
			closedir($handle);
			rmdir($dir);
		}
		return true; // if it can't open the directory we assume it's deleted
	}
}


if( function_comp( 'write_file', 1 ) ) {
	function write_file($file,$contents)
	{
		return ($fp = @fopen($file,'w')) && @fwrite($fp, $contents) && fclose($fp);
	}
}
if( function_comp( 'read_file', 1 ) ) {
	function read_file($file,$contents = false)
	{
		if ( ($fp = @fopen($file,'r')) )
		{	// read the file
			$contents = @fread($fp, filesize($file));
			fclose($fp);
		}
		return $contents;
	}
}


if( function_comp( 'get_relative_location', 1 ) ) {
function get_relative_path( $wanted_loc, $base_loc )
{	/*
	 * Gets the relative location of $wanted_file based on the $base_file's location
	 *
	 * @author Benjamin "balupton" Lupton {@link http://www.balupton.com}
	 * @category resourceLibraryFunction
	 * @package baluptonResourceLibrary
	 * @version 1
	 * @copyright (c) 2007 Benjamin "balupton" Lupton {@link http://www.balupton.com}
	 * @license Attribution-Share Alike 2.5 Australia - {@link http://creativecommons.org/licenses/by-sa/2.5/au/}
	 */
	
	$wanted_file = create_valid_path($wanted_loc);
	$base_loc = create_valid_path($base_loc, true);
	
	// remove the file from $base_file if it exists
	if( substr($base_loc,strlen($base_loc)-1,1) != '/' )
		$base_loc = substr($base_loc,0,strrpos($base_loc,'/'));

	$start_on = 0;
	$change_on = 0;
	
	$a = explode('/',$wanted_loc);
	$aa = explode('/',$base_loc);
	$s = sizeof($a);
	$ss = sizeof($aa);
	
	// Remove the empty parts
	for( $i = 0; $i < $s; $i++ )
	{	// remove empty parts
		if( empty($a[$i]) ) {
			array_splice($a,$i,1);
			$i--;
			$s--;
		}
	}
	
	for( $i = 0; $i < $ss; $i++ )
	{	// remove empty parts
		if( empty($aa[$i]) ) {
			array_splice($aa,$i,1);
			$i--;
			$ss--;
		}
	}
	
	for( $i = 0; $i < $s && $i < $ss; $i++ )
	{	// gets the first similarity between the two locations
		$c = & $a[$i];
		$cc = & $aa[$i];
		if( $c === $cc ) {
			$start_on = $i;
			break;
		}
	}
	
	for( $i = $start_on; $i < $s && $i < $ss; $i++ )
	{	// gets the first difference between the two locations
		$c = & $a[$i];
		$cc = & $aa[$i];
		if( $c !== $cc ) {
			$change_on = $i;
			break;
		}
	}
	
	array_splice($a,0,$change_on);
	array_splice($aa,0,$change_on);
	$new_file = implode('/',$a);
	$ss = sizeof($aa);
	for ( $i = 0; $i < $ss; $i++ )
		$new_file = '../'.$new_file;
		
	return $new_file;
}}


if( function_comp( 'copy_dir', 1 ) ) {
function copy_dir($source, $dest, $overwrite = false){

	$source = create_valid_path($source);
	$dest = create_valid_path($dest);

	if( !(is_dir($dest) || @mkdir($dest)) )
	{	// if the destination directory does not exist then create it
		return false;
	}
		
	if( $handle = @opendir($source) )
	{	// open'd the directory for reading
		while( false !== ($file = readdir($handle)) )
		{	// cycle through the contents of the directory
			if( substr($file,0,1) != '.' )
			{	// if the file is not a dummy file
				$source_file = $source.$file;
				$dest_file = $dest.$file;
				
				if( is_file($dest_file) && is_file($source_file) )
				{	// if both things are files then remove the destination file
					if( $overwrite )
						if ( !@unlink($dest_file) )
							return false;
				}
					
				if( is_file($source_file) )
				{	// if the source file is a file then copy it over
					if( !@copy($source_file, $dest_file) )
						return false;
				}
				elseif( is_dir($source_file) )
				{	// if the source file is a directory then recurse
					if( !copy_dir($source_file, $dest_file, $overwrite) )
						return false;
				}
				
			}
		}
		closedir($handle);
	} else
	{
		return false;
	}
	
	return true;
}
}

if( function_comp( 'rename_dir', 1 ) ) {
function rename_dir($source, $dest, $overwrite = false){

	$source = create_valid_path($source);
	$dest = create_valid_path($dest);

	if( !(is_dir($dest) || @mkdir($dest)) )
	{	// if the destination directory does not exist then create it
		return false;
	}
		
	if( $handle = @opendir($source) )
	{	// open'd the directory for reading
		while( false !== ($file = readdir($handle)) )
		{	// cycle through the contents of the directory
			if( substr($file,0,1) != '.' )
			{	// if the file is not a dummy file
				$source_file = $source.$file;
				$dest_file = $dest.$file;
				
				if( is_file($dest_file) && is_file($source_file) )
				{	// if both things are files then remove the destination file
					if( $overwrite )
						if ( !@unlink($dest_file) )
							return false;
				}
					
				if( is_file($source_file) )
				{	// if the source file is a file then copy it over
					if( !@rename($source_file, $dest_file) )
						return false;
				}
				elseif( is_dir($source_file) )
				{	// if the source file is a directory then recurse
					if( !rename_dir($source_file, $dest_file, $overwrite) )
						return false;
				}
				
			}
		}
		closedir($handle);
	} else
	{
		return false;
	}
	
	return unlink_dir($source);
}
}

?>