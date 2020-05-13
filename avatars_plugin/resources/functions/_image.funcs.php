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

if( function_comp( 'resize', 8 ) ) {
	function resize( $args )
	{	/*
		 * @author Benjamin "balupton" Lupton {@link http://www.balupton.com}
		 * @category resourceLibraryFunction
		 * @package baluptonResourceLibrary
		 * @version 8
		 * @date December 02, 2007
	     * @copyright (c) 2007 Benjamin "balupton" Lupton {@link http://www.balupton.com}
		 * @license Attribution-Share Alike 2.5 Australia - {@link http://creativecommons.org/licenses/by-sa/2.5/au/}
		 */

		/*
		 * Changelog
		 *
		 * v8, 02/12/2007
		 * - Cleaned by using math instead of logic
		 * - Restructured the code
		 * - Re-organised variable names
		 * v7, 20/07/2007
		 * - Cleaned
		 * v6,
		 * - Added cropping
		 * v5, 12/08/2006
		 * - Changed to use args
		 */

		/*
		The 'exact' resize mode, will resize things to the exact limit.
		If a width or height is 0, the appropriate value will be calculated by ratio
		Results with a 800x600 limit:
			*x*			->	800x600
		Results with a 0x600 limit:
			1280x1024	->	750x600
			1900x1200	->	950x600
			96x48		->	1200x600
			1000x500	->	1200x600
		Results with a 800x0 limit:
			1280x1024	->	800x640
			1900x1200	->	800x505
			96x48		->	800x400
			1000x500	->	800x400
		*/

		/*
		The 'area' resize mode, will resize things to fit within the area.
		If a width or height is 0, the appropriate value will be calculated by ratio
		Results with a 800x600 limit:
			1280x1024	->	750x600
			1900x1200	->	950x600	->  800x505
			96x48		->	96x48		no change
			1000x500	->	800x400 = '800x'.(800/100)*500
		Results with a 0x600 limit:
			1280x1024	->	750x600
			1900x1200	->	950x600
			96x48		->	96x48		no change
			1000x500	->	1000x500	no change
		Results with a 800x0 limit:
			1280x1024	->	800x640
			1900x1200	->	950x600	->	800x505
			96x48		->	96x48		no change
			1000x500	->	800x400	= '800x'.(800/1000)*500
		*/

		// ---------
		extract($args);

		// ---------
		if ( !isset($width_original) && isset($width_old) )
		{	$width_original = $width_old;	unset($width_old);	}
		if ( !isset($height_original) && isset($height_old) )
		{	$height_original = $height_old;	unset($height_old);	}

		//
		if ( !isset($width_original) && !isset($height_original) && isset($image) )
		{	// Get from image
			$image = read_image($image);
			$width_original = imagesx($image);
			$height_original = imagesy($image);
		}

		//
		if ( empty($width_original) || empty($height_original) )
		{	//
			echo '<!-- ERROR: '.__FUNCTION__.': '.__LINE__.': no original dimensions specified -->';
			return false;
		}

		// ---------
		if ( !isset($width_desired) && isset($width_new) )
		{	$width_desired = $width_new;	unset($width_new);	}
		if ( !isset($height_desired) && isset($height_new) )
		{	$height_desired = $height_new;	unset($height_new);	}

		//
		if ( empty($width_desired) || empty($height_desired) )
		{	// Don't do any resizing
			echo '<!-- WARNING: '.__FUNCTION__.': '.__LINE__.': no desired dimensions specified -->';
			// return array( 'width' => $width_original, 'height' => $height_original );
		}

		// ---------
		if ( !isset($resize_mode) )
			$resize_mode = 'area';
		elseif ( $resize_mode === 'none' )
		{	// Don't do any resizing
			//echo '<!-- WARNING: '.__FUNCTION__.': '.__LINE__.': $resize_mode === \'none\' -->';
			//return array( 'width' => $width_original, 'height' => $height_original );
		}
		elseif ( !in_array($resize_mode, array('area', 'crop-top', 'crop-bottom', 'crop-center', 'exact', true) ) )
		{	//
			echo('<!-- ERROR:  '.__FUNCTION__.': '.__LINE__.': Passed $resize_mode is not valid: '."[$resize_mode]".' -->');
			return false;
		}

		// ---------
		if ( !isset($x_original) && isset($x_old) )
		{	$x_original = $x_old;	unset($x_old);	}
		if ( !isset($y_original) && isset($y_old) )
		{	$y_original = $y_old;	unset($y_old);	}
		if ( !isset($x_original) )	$x_original = 0;
		if ( !isset($y_original) )	$y_original = 0;

		// ---------
		// Let's force integer values
		$width_original = intval($width_original);
		$height_original = intval($height_original);
		$width_desired = intval($width_desired);
		$height_desired = intval($height_desired);

		// ---------
		// Set proportions
		if ( $height_original !== 0 )
			$proportion_wh = $width_original / $height_original;
		if ( $width_original !== 0 )
			$proportion_hw = $height_original / $width_original;

		if ( $height_desired !== 0 )
			$proportion_wh_desired = $width_desired / $height_desired;
		if ( $width_desired !== 0 )
			$proportion_hw_desired = $height_desired / $width_desired;

		// ---------
		// Set cutoms
		$x_new = $x_original;
		$y_new = $y_original;
		$canvas_width = $canvas_height = NULL;

		// ---------
		$width_new = $width_original;
		$height_new = $height_original;

		// ---------
		// Do resize
		if ( $height_desired === 0 && $width_desired === 0 )
		{	// Nothing to do
		}
		elseif ( $height_desired === 0 && $width_desired !== 0 )
		{	// We don't care about the height
			$width_new = $width_desired;
			if ( $resize_mode !== 'exact' ) $height_new = $width_desired * $proportion_hw; // h = w*(h/w)
		}
		elseif ( $height_desired !== 0 && $height_desired === 0 )
		{	// We don't care about the width
			if ( $resize_mode !== 'exact' ) $width_new = $height_desired * $proportion_wh; // w = h*(w/h)
			$height_new = $height_desired;
		}
		else
		{	// We care about both

			if ( $resize_mode === 'exact' || /* no upscaling */ ($width_original <= $width_desired && $height_original <= $height_desired) )
			{	// Nothing to do
			}
			elseif ( $resize_mode === 'area' )
			{	// Proportion to fit inside

				// Pick which option
				if ( $proportion_wh <= $proportion_wh_desired )
				{	// Option 1: wh
					// Height would of overflowed
					$width_new = $height_desired * $proportion_wh; // w = h*(w/h)
					$height_new = $height_desired;
				}
				else
				// if ( $proportion_hw <= $proportion_hw_desired )
				{	// Option 2: hw
					// Width would of overflowed
					$width_new = $width_desired;
					$height_new = $width_desired * $proportion_hw; // h = w*(h/w)
				}

			}
			elseif ( preg_match( '~^crop-~', $resize_mode ) )
			{	// Proportion to occupy

				// Pick which option
				if ( $proportion_wh <= $proportion_wh_desired )
				{	// Option 2: hw
					// Height will overflow
					$width_new = $width_desired;
					$height_new = $width_desired * $proportion_hw; // h = w*(h/w)

					switch( $resize_mode )
					{
						case 'crop-top':
							// No change
							break;

						case 'crop-center':
							$y_new = -($height_new-$height_desired)/2;
							break;

						case 'crop-bottom':
							$y_new = -($height_new-$height_desired);
							break;
					}
				}
				else
				// if ( $proportion_hw <= $proportion_hw_desired )
				{	// Option 1: hw
					// Width will overflow
					$width_new = $height_desired * $proportion_wh; // w = h*(w/h)
					$height_new = $height_desired;
					// Set custom
					$x_new = -($width_new-$width_desired)/2;
				}

				// Set canvas
				$canvas_width = $width_desired;
				$canvas_height = $height_desired;

			}

		}

		// ---------
		// Set custom if they have not been set already
		if ( $canvas_width === NULL )
			$canvas_width = $width_new;
		if ( $canvas_height === NULL )
			$canvas_height = $height_new;

		// ---------
		// Compat
		$width_old = $width_original;
		$height_old = $height_original;
		$x_old = $x_original;
		$y_old = $y_original;

		// ---------
		// Return
		$return = compact(
			'width_original', 'height_original',
				'width_old', 'height_old',
			'width_desired', 'height_desired',
			'width_new', 'height_new',
			'canvas_width', 'canvas_height',
			'x_original', 'y_original',
				'x_old', 'y_old',
			'x_new', 'y_new'
		);
		// echo '<--'; var_dump($return); echo '-->';
		return $return;
	}
}

if ( !function_exists('exif_imagetype') )
{
	function exif_imagetype ( $image_location )
	{
		$image_size = getimagesize($image_location);
		if ( !$image_size )
			return $image_size;
		$image_type = $image_size[2];
		return $image_type;
	}
}

if( !function_exists('image_type_to_extension') ) {
	function image_type_to_extension ( $image_type, $include_dot = true )
	{	/*
		 * @author Benjamin "balupton" Lupton {@link http://www.balupton.com}
		 * @category resourceLibraryFunction
		 * @package baluptonResourceLibrary
		 * @version 1
		 * @date July 20, 2007
	     * @copyright (c) 2007 Benjamin "balupton" Lupton {@link http://www.balupton.com}
		 * @license Attribution-Share Alike 2.5 Australia - {@link http://creativecommons.org/licenses/by-sa/2.5/au/}
		 */

		/*
		 * Changelog
		 *
		 * Version 1, 20/07/2007
		 * - created
		 */

		switch ( $image_type )
		{
		/*	case IMAGETYPE_GIF:		$image_type = 'gif';	break;
			case IMAGETYPE_JPEG:	$image_type = 'jpeg';	break;
			case IMAGETYPE_PNG:		$image_type = 'png';	break;
			case IMAGETYPE_SWF:		$image_type = 'swf';	break;
			case IMAGETYPE_PSD:		$image_type = 'psd';	break;
			case IMAGETYPE_BMP:		$image_type = 'bmp';	break;
			case IMAGETYPE_TIFF_II:	$image_type = 'tiff';	break;
			case IMAGETYPE_TIFF_MM:	$image_type = 'tiff';	break;
			case IMAGETYPE_JPC:		$image_type = 'jpc';	break;
			case IMAGETYPE_JP2:		$image_type = 'jp2';	break;
			case IMAGETYPE_JPX:		$image_type = 'jpx';	break;
			case IMAGETYPE_JB2:		$image_type = 'jb2';	break;
			case IMAGETYPE_SWC:		$image_type = 'swc';	break;
			case IMAGETYPE_IFF:		$image_type = 'iff';	break;
			case IMAGETYPE_WBMP:	$image_type = 'wbmp';	break;
			case IMAGETYPE_XBM:		$image_type = 'xbm';	break;
		*/	default:				$image_type = 'jpeg';	break;
		}

		if ( $include_dot )
			$image_type = '.'.$image_type;

		return $image_type;
	}
}


if( function_comp( 'define_image_vars', 1 ) ) {
function define_image_vars ( )
{	/*
	 * @author Benjamin "balupton" Lupton {@link http://www.balupton.com}
	 * @category resourceLibraryFunction
	 * @package baluptonResourceLibrary
	 * @copyright (c) 2007 Benjamin "balupton" Lupton {@link http://www.balupton.com}
	 * @license Attribution-Share Alike 2.5 Australia - {@link http://creativecommons.org/licenses/by-sa/2.5/au/}
	 */

	global
		$IMAGE_TYPES, $IMAGE_EXTENSIONS, $IMAGE_TYPES_EXTENSIONS,
		$IMAGE_SUPPORTED_READ_TYPES, $IMAGE_SUPPORTED_READ_EXTENSIONS, $IMAGE_SUPPORTED_READ_TYPES_EXTENSIONS,
		$IMAGE_SUPPORTED_WRITE_TYPES, $IMAGE_SUPPORTED_WRITE_EXTENSIONS, $IMAGE_SUPPORTED_WRITE_TYPES_EXTENSIONS,
		$IMAGE_SUPPORTED_TYPES, $IMAGE_SUPPORTED_EXTENSIONS, $IMAGE_SUPPORTED_TYPES_EXTENSIONS;
	if ( isset($IMAGE_TYPES) )
		return true;

/*	$IMAGE_TYPES = array('GIF', 'JPEG', 'PNG', 'SWF', 'PSD', 'BMP', 'TIFF_II', 'TIFF_MM', 'JPC', 'JP2', 'JPX', 'JB2', 'SWC', 'IFF', 'WBMP', 'XBM');
	$IMAGE_EXTENSIONS = array('gif', 'jpeg', 'jpg', 'png', 'swf', 'psd', 'bmp', 'tiff', 'jpc', 'jp2', 'jpx', 'jb2', 'swc', 'iff', 'wbmp', 'xbm');
	$IMAGE_TYPES_EXTENSIONS = array(
		'GIF' => array('gif'),
		'JPEG' => array('jpeg','jpg'),
		'PNG' => array('png'),
		'SWF' => array('swf'),
		'PSD' => array('psd'),
		'BMP' => array('bmp'),
		'TIFF_II' => array('tiff'),
		'TIFF_MM' => array('tiff'),
		'JPC' => array('jpc'),
		'JP2' => array('jp2'),
		'JPX' => array('jpx'),
		'JB2' => array('jb2'),
		'SWC' => array('swc'),
		'IFF' => array('iff'),
		'WBMP' => array('wbmp'),
		'XBM' => array('xbm')
	);	*/

	$IMAGE_TYPES = array('GIF', 'JPEG', 'PNG');
	$IMAGE_EXTENSIONS = array('gif', 'jpeg', 'jpg', 'png');
	$IMAGE_TYPES_EXTENSIONS = array(
		'GIF' => array('gif'),
		'JPEG' => array('jpeg','jpg'),
		'PNG' => array('png')
	);

	$IMAGE_SUPPORTED_READ_TYPES = array();
	$IMAGE_SUPPORTED_READ_EXTENSIONS = array();
	$IMAGE_SUPPORTED_READ_TYPES_EXTENSIONS = array();

	$IMAGE_SUPPORTED_WRITE_TYPES = array();
	$IMAGE_SUPPORTED_WRITE_EXTENSIONS = array();
	$IMAGE_SUPPORTED_WRITE_TYPES_EXTENSIONS = array();

	$IMAGE_SUPPORTED_TYPES = array();
	$IMAGE_SUPPORTED_EXTENSIONS = array();
	$IMAGE_SUPPORTED_TYPES_EXTENSIONS = array();

	for ( $i = 0, $n = sizeof($IMAGE_TYPES); $i < $n; ++$i )
	{
		$image_type = $IMAGE_TYPES[$i];
		$image_extensions = $IMAGE_TYPES_EXTENSIONS[$image_type];
		$image_extension = $image_extensions[0];

		$image_read_function = 'imagecreatefrom'.$image_extension;
		$image_supported_read = function_exists($image_read_function);
		if ( $image_supported_read )
		{	// Add
			$IMAGE_SUPPORTED_READ_TYPES[] = $image_type;
			$IMAGE_SUPPORTED_READ_EXTENSIONS += $image_extensions;
			$IMAGE_SUPPORTED_READ_TYPES_EXTENSIONS[$image_type] = $image_extensions;
		}

		$image_write_function = 'image'.$image_extension;
		$image_supported_write = function_exists($image_write_function);
		if ( $image_supported_write )
		{	// Add
			$IMAGE_SUPPORTED_WRITE_TYPES[] = $image_type;
			$IMAGE_SUPPORTED_WRITE_EXTENSIONS += $image_extensions;
			$IMAGE_SUPPORTED_WRITE_TYPES_EXTENSIONS[$image_type] = $image_extensions;
		}

		if ( $image_supported_read && $image_supported_write )
		{	// Add
			$IMAGE_SUPPORTED_TYPES[] = $image_type;
			$IMAGE_SUPPORTED_EXTENSIONS += $image_extensions;
			$IMAGE_SUPPORTED_TYPES_EXTENSIONS[$image_type] = $image_extensions;
		}
	}

	return true;
}
define_image_vars();
}

if( function_comp( 'image_read_function', 1 ) ) {
function image_read_function ( $image_extension )
{
	$image_read_function = 'imagecreatefrom'.$image_extension;
	if ( !function_exists($image_read_function) )
		return false;
	return $image_read_function;
}
}

if( function_comp( 'image_write_function', 1 ) ) {
function image_write_function ( $image_extension )
{
	$image_write_function = 'image'.$image_extension;
	if ( !function_exists($image_write_function) )
		return false;
	return $image_write_function;
}
}

if( function_comp( 'read_image', 4 ) ) {
	function read_image( $args, $return_info = false )
	{	/*
		 * @author Benjamin "balupton" Lupton {@link http://www.balupton.com}
		 * @category resourceLibraryFunction
		 * @package baluptonResourceLibrary
		 * @version 4
		 * @date July 20, 2007
	     * @copyright (c) 2007 Benjamin "balupton" Lupton {@link http://www.balupton.com}
		 * @license Attribution-Share Alike 2.5 Australia - {@link http://creativecommons.org/licenses/by-sa/2.5/au/}
		 */

		/*
		 * Changelog
		 *
		 * Version 4, 20/07/2007
		 * - Cleaned
		 * Version 3, 12/08/2006
		 * - Changed it to use $args
		 */

		// Set defaults
		$image = NULL;

		// Extract
		if ( gettype($args) === 'array' )
			extract($args);
		else
			$image = $args;

		// Check image
		if ( empty($image) )
		{
			echo('<!-- ERROR:  '.__FUNCTION__.': '.__LINE__.': $image is empty: ['.var_export($image, true).']['.var_export($args, true).'] -->');
			return false;
		}

		// Get the type of the image
		$type = gettype($image);

		// Do stuff
		switch ( $type )
		{
			case 'string':
				// Try and create it
				$result = @imagecreatefromstring($image);
				if ( $result )
					return $result; // The image was a binary string

				// Check if it is a file
				if ( is_file($image) )
				{
					// Get the image type
					$image_type = exif_imagetype($image);
					if ( !$image_type )
					{	// Error
						echo('<!-- ERROR:  '.__FUNCTION__.': '.__LINE__.': $image did not point to a valid image: '."[$image]".' -->');
						return false;
					}

					switch ( $image_type )
					{
						case 1: { $image_extension = 'gif'; }
							break;
						case 2: { $image_extension = 'jpeg'; }
							break;
						case 3: { $image_extension = 'png'; }
							break;
						default:
							return false;
					}

				//  There is no image_type_to_extension function on PHP < 5.2
				//	$image_extension = image_type_to_extension($image_type, false);

					// Check if image is supported
					$image_read_function = image_read_function($image_extension);

					if ( !$image_read_function )
					{	// Error
						echo('<!-- ERROR:  '.__FUNCTION__.': '.__LINE__.': Unsupported image type: '."[$image_type][$image_extension]".' -->');
						return false;
					}

					// Read the image
					$image = call_user_func($image_read_function, $image);
					if ( !$image )
					{	// Error
						echo('<!-- ERROR:  '.__FUNCTION__.': '.__LINE__.': Failed to the read the image: '."[$image]".' -->');
						return false;
					}
					break;
				}

			case 'resource':
				// Check if it is already a image
				if ( @imagesx($image) )
				{	// We have a valid image
					break;
				}

			default:
				// Error
				echo('<!-- ERROR:  '.__FUNCTION__.': '.__LINE__.': Could not determine the image: '."[$image]:[$image_type]".' -->');
				$image = false;
				break;
		}

		// return
		if ( $return_info )
		{
			return compact('image', 'image_extension', 'image_type', 'image_read_function');
		}

		// return
		return $image;
	}
}

if( function_comp( 'resize_image', 3 ) ) {
	function resize_image( $args )
	{	/*
		 * @author Benjamin "balupton" Lupton {@link http://www.balupton.com}
		 * @category resourceLibraryFunction
		 * @package baluptonResourceLibrary
		 * @version 3
		 * @date July 20, 2007
	     * @copyright (c) 2007 Benjamin "balupton" Lupton {@link http://www.balupton.com}
		 * @license Attribution-Share Alike 2.5 Australia - {@link http://creativecommons.org/licenses/by-sa/2.5/au/}
		 */

		/*
		 * Changelog
		 *
		 * Version 3, 20/07/2007
		 * - Cleaned
		 * Version 2, 12/08/2006
		 * - Changed it to use $args
		 */

		// Set default variables
		$image
		= $width_old = $height_old
		= $width_new = $height_new
		= $canvas_width = $canvas_size
		= NULL;

		$x_old = $y_old
		= $x_new = $y_new
		= 0;

		// Exract user
		extract($args);

		// Read image
		$image = read_image($image);
		if ( empty($image) )
		{	// error
			echo '<!-- ERROR: '.__FUNCTION__.': '.__LINE__.': no image was specified -->';
			return false;
		}

		// Check new dimensions
		if ( empty($width_new) || empty($height_new) )
		{	// error
			echo '<!-- ERROR: '.__FUNCTION__.': '.__LINE__.': Desired/new dimensions not found -->';
			return false;
		}

		// Do old dimensions
		if ( empty($width_old) && empty($height_old) )
		{	// Get the old dimensions from the image
			$width_old = imagesx($image);
			$height_old = imagesy($image);
		}

		// Do canvas dimensions
		if ( empty($canvas_width) && empty($canvas_height) )
		{	// Set default
			$canvas_width = $width_new;
			$canvas_height = $height_new;
		}

		// Let's force integer values
		$width_old = intval($width_old);
		$height_old = intval($height_old);
		$width_new = intval($width_new);
		$height_new = intval($height_new);
		$canvas_width = intval($canvas_width);
		$canvas_height = intval($canvas_height);

		// Create the new image
		$image_new = imagecreatetruecolor($canvas_width, $canvas_height);

		// Resample the image
		$image_result = imagecopyresampled(
			// the new image
				$image_new,
			// the old image to update
				$image,
			// the new positions
				$x_new, $y_new,
			// the old positions
				$x_old, $y_old,
			// the new dimensions
				$width_new, $height_new,
			// the old dimensions
				$width_old, $height_old
		);

		// Check
		if ( !$image_result )
		{	// ERROR
			echo '<!-- ERROR: '.__FUNCTION__.': '.__LINE__.': the image failed to resample -->';
			return false;
		}

		// return
		return $image_new;
	}
}

if( function_comp( 'write_image', 1 ) ) {
	function write_image( $args )
	{	/*
		 * @author Benjamin "balupton" Lupton {@link http://www.balupton.com}
		 * @category resourceLibraryFunction
		 * @package baluptonResourceLibrary
		 * @version 1
		 * @date July 20, 2007
	     * @copyright (c) 2007 Benjamin "balupton" Lupton {@link http://www.balupton.com}
		 * @license Attribution-Share Alike 2.5 Australia - {@link http://creativecommons.org/licenses/by-sa/2.5/au/}
		 */

		/*
		 * Changelog
		 *
		 * Version 1, 20/07/2007
		 * - Created
		 */

		// Set default variables
		$image
		= $image_type
		= $location
		= NULL;

		$quality = 95;

		// Exract user
		extract($args);

		// If the image is a file
		if ( gettype($image) === 'string' && is_file($image) )
		{
			// Get the image type
			$image_type = exif_imagetype($image);
			if ( !$image_type )
			{	// Error
				echo('<!-- ERROR:  '.__FUNCTION__.': '.__LINE__.': $image did not point to a valid image: '."[$image]".' -->');
				return false;
			}
			$image_extension = image_type_to_extension($image_type, false);

			// Check Image
			$image = read_image($image);
		}
		else
		{
			// Check image type
			if ( empty($image_type) )
			{	// error
				echo '<!-- ERROR: '.__FUNCTION__.': '.__LINE__.': no image_type was specified -->';
				return false;
			}
		}

		// Check image
		if ( empty($image) )
		{	// error
			echo '<!-- ERROR: '.__FUNCTION__.': '.__LINE__.': no image was specified -->';
			return false;
		}

		// Check if image is supported
//		$image_write_function = image_write_function($image_extension);
		$image_write_function = 'imagejpeg';

		if ( !$image_write_function )
		{	// Error
			echo('<!-- ERROR:  '.__FUNCTION__.': '.__LINE__.': Unsupported image type: '."[$image_type][$image_extension]".' -->');
			return false;
		}

		// Read the image
		ob_start();
		$image = call_user_func($image_write_function, $image, $location, $quality);
		$error = strstr(ob_get_contents(), '</b>:');
		ob_end_flush();
		if ( !$image || $error )
		{	// Error
			echo('<!-- ERROR:  '.__FUNCTION__.': '.__LINE__.': Failed to the write the image: '."[$image]".' -->');
			return false;
		}

		echo('<!-- debug:  '.__FUNCTION__.': '.__LINE__.': '."[$image]".' -->');

		// Return
		return $image;
	}
}

if( function_comp( 'compress_image', 4 ) ) {
	function compress_image( $args )
	{	/*
		 * @author Benjamin "balupton" Lupton {@link http://www.balupton.com}
		 * @category resourceLibraryFunction
		 * @package baluptonResourceLibrary
		 * @version 4
		 * @date July 20, 2007
	     * @copyright (c) 2007 Benjamin "balupton" Lupton {@link http://www.balupton.com}
		 * @license Attribution-Share Alike 2.5 Australia - {@link http://creativecommons.org/licenses/by-sa/2.5/au/}
		 */

		/*
		 * Changelog
		 *
		 * Version 4, 20/07/2007
		 * - Cleaned
		 * Version 3, 12/08/2006
		 * - Changed it to use $args
		 * - Changed it to a 95% compression with a 5% decrease
		 */

		// Set default variables
		$image
		= $image_type
		= NULL;

		$quality = 95;
		$max_filesize = 0;

		// Exract user
		extract($args);

		// Read image + info
		$result = read_image($image, true);
		if ( !$result )
			return $result;

		// Update info
		extract($result);

		// Check Image
		if ( empty($image) )
		{	// error
			echo '<!-- ERROR: '.__FUNCTION__.': '.__LINE__.': no image was specified -->';
			return false;
		}

		// Max filesize
		if ( !empty($max_size) )
			$max_filesize = $max_size; // backwards compat

		// Do compression
		$location = NULL;
		ob_start();
		if ( !write_image(compact('image', 'location', 'quality', 'image_type', 'image_extension')) )
		{	// Writing the image failed
			ob_end_flush();
			echo '<!-- ERROR: '.__FUNCTION__.': '.__LINE__.': writing the image failed -->';
			return false;
		}
		$result = ob_get_contents();
		ob_end_clean();

		if ( $max_filesize != 0 )
		{	// Max filesize is now bytes!
			while ( $quality >= 5 && strlen($result) /* current filesize */ > $max_filesize )
			{
				ob_start();
				write_image(compact('image', 'location', 'quality', 'image_type', 'image_extension'));
				$result = ob_get_contents();
				ob_end_clean();
				$quality -= 5;
			}
		}

		return $result;
	}
}

if( function_comp( 'remake_image', 9.1 ) ) {
	function remake_image( $args )
	{	/*
		 * @author Benjamin "balupton" Lupton {@link http://www.balupton.com}
		 * @category resourceLibraryFunction
		 * @package baluptonResourceLibrary
		 * @version 9.1
		 * @date December 02, 2007
	     * @copyright (c) 2007 Benjamin "balupton" Lupton {@link http://www.balupton.com}
		 * @license Attribution-Share Alike 2.5 Australia - {@link http://creativecommons.org/licenses/by-sa/2.5/au/}
		 */

		/*
		 * Changelog
		 *
		 * Version 9.1, 02/12/2007
		 * - Added error checking on write
		 * Version 9, 20/07/2007
		 * - Cleaned
		 * Version 8, 12/08/2006
		 * - Changed it to use $args
		 * - Changed it to have a 95% compression
		 */

		// Set default variables
		$image
		= $image_type
		= NULL;

		$width_new = $height_new = 0;
		$resize_mode = 'area';
		$quality = 95;
		$max_filesize = 0;

		// Exract args
		extract($args);

		// Read image + info
		$result = read_image($image, true);
		if ( !$result )
			return $result;

		// Update info
		extract($result);

		// Check Image
		if ( empty($image) )
		{	// error
			echo '<!-- ERROR: '.__FUNCTION__.': '.__LINE__.': no image was specified -->';
			return false;
		}

		// Check new dimensions
		if ( !isset($args['width_new']) || !isset($args['height_new']) )
		{	// error
			echo '<!-- ERROR: '.__FUNCTION__.': '.__LINE__.': Desired/new dimensions not found -->';
			return false;
		}

		// Do old dimensions
		if ( empty($width_old) && empty($height_old) )
		{	// Get the old dimensions from the image
			$width_old = imagesx($image);
			$height_old = imagesy($image);
		}

		// Let's force integer values
		$width_old = intval($width_old);
		$height_old = intval($height_old);
		$width_new = intval($width_new);
		$height_new = intval($height_new);

		// Max filesize
		if ( !empty($size) )
			$max_filesize = $size; // backwards compat
		if ( !empty($max_size) )
			$max_filesize = $max_size; // backwards compat


		# REMAKE THE IMAGE

		// Get new resize stuff
		$result = resize(compact('image', 'resize_mode', 'width_old', 'height_old', 'width_new', 'height_new'));
		if ( !$result )
			return $result;

		// Update variables
		extract($result);

		// Resize the image
		$resize_image = compact(
			'image',
			'width_old', 'height_old',
			'width_new', 'height_new',
			'canvas_width', 'canvas_height',
			'x_old', 'y_old',
			'x_new', 'y_new'
		);

		/*
		echo '<!-- resize_image'.
		"\r\n".print_r($resize_image, true).
		"\r\n".'-->'."\r\n";
		*/

		$image = resize_image($resize_image);
		if ( !$image )
			return $image;

		// Compress the image
		$compress_image = compact('image', 'max_filesize', 'quality', 'image_type', 'image_extension');

		/*
		echo '<!-- compress_image'.
		"\r\n".print_r($compress_image, true).
		"\r\n".'-->'."\r\n";
		*/

		$image = compress_image($compress_image);
		if ( !$image )
			return $image;

		// Return the image
		return $image;
	}
}

?>