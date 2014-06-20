<?php
/**
*   Class to handle images
*
*   @author     Lee Garner <lee@leegarner.com>
*   @copyright  Copyright (c) 2012-2014 Lee Garner <lee@leegarner.com>
*   @package    lglib
*   @version    0.0.5
*   @license    http://opensource.org/licenses/gpl-2.0.php 
*               GNU Public License v2 or later
*   @filesource
*/


/**
 *  Image-handling class
 */
class lgImage 
{

    /**
     *  Calculate the new dimensions needed to keep the image within
     *  the provided width & height while preserving the aspect ratio.
     *
     *  @param  integer $width New width, in pixels
     *  @param  integer $height New height, in pixels
     *  @return array   $old_width, $old_height, $newwidth, $newheight
     */
    public static function reDim($orig_path, $width=0, $height=0)
    {
        $dimensions = getimagesize($orig_path);
        $s_width = $dimensions[0];
        $s_height = $dimensions[1];

        // get both sizefactors that would resize one dimension correctly
        if ($width > 0 && $s_width > $width)
            $sizefactor_w = (double) ($width / $s_width);
        else
            $sizefactor_w = 1;

        if ($height > 0 && $s_height > $height)
            $sizefactor_h = (double) ($height / $s_height);
        else
            $sizefactor_h = 1;

        // Use the smaller factor to stay within the parameters
        $sizefactor = min($sizefactor_w, $sizefactor_h);

        $newwidth = (int)($s_width * $sizefactor);
        $newheight = (int)($s_height * $sizefactor);

        return array($s_width, $s_height, $newwidth, $newheight);
    }


    /**
     *  Resize an image to the specified dimensions, placing the resulting
     *  image in the specified location.  At least one of $newWidth or
     *  $newHeight must be specified.
     *
     *  @param  string  $type       Either 'thumb' or 'disp'
     *  @param  integer $newWidth   New width, in pixels
     *  @param  integer $newHeight  New height, in pixels
     *  @return string          Blank if successful, error message otherwise.
     */
    public static function ReSize($src, $dst, $newWidth=0, $newHeight=0)
    {
        global $_LGLIB_CONF;

        // Calculate the new dimensions
        list($sWidth,$sHeight,$dWidth,$dHeight) = 
            lgImage::reDim($src, $newWidth, $newHeight);

        // Get the mime type for the glFusion resizing functions
        $mime_type = image_type_to_mime_type(exif_imagetype($src));

        // Returns an array, with [0] either true/false and [1] 
        // containing a message.
        $result = array();
        if (function_exists(_img_resizeImage)) {
            $result = _img_resizeImage($src, $dst, $sHeight, $sWidth, 
                            $dHeight, $dWidth, $mime_type);
        } else {
            $result[0] = false;
        }

        if ($result[0] == true)
            return '';
        else {
            COM_errorLog("Failed to convert $src ($sHeight x $sWidth) to $dst ($dHeight x $dWidth)");
            return 'invalid image conversion';
        }

    }   // function reSize()

     
}   // class lgImage

?>
