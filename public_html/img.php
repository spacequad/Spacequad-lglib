<?php
//  $Id: functions.inc 525 2012-03-02 00:24:02Z root $
/**
*   Common functions for the Photo Competition plugin.
*   Based partially on timthumb 2.8.9 by Ben Gillbanks and Mark Maunder
*   See http://code.google.com/p/timthumb/ for the original
*
*   @author     Lee Garner <lee@leegarner.com>
*   @copyright  Copyright (c) 2009-2012 Lee Garner <lee@leegarner.com>
*   @package    lglib
*   @version    0.0.1
*   @license    http://opensource.org/licenses/gpl-2.0.php 
*               GNU Public License v2 or later
*   @filesource
*/

/*
 * --- TimThumb CONFIGURATION ---
 * To edit the configs it is best to create a file called timthumb-config.php
 * and define variables you want to customize in there. It will automatically be
 * loaded by timthumb. This will save you having to re-edit these variables
 * everytime you download a new version
*/
define('TIM_VERSION', '2.8.9');     // Version of this script 

$_IMG_CONF = array(
    'debug_level'   => 3,
    'memory_limit'  => '30M',
    'file_cache_enabled' => true,
    'cache_clean_interval' => 86400,
    'cache_max_age' => 604800,
    'cache_suffix'  => '.txt',
    'cache_prefix'  => 'timthumb_tmp',
    'cache_dir'     => './cache',
    'file_max_size' => 10485760,
    'browser_cache_age' => 864000,
    'browser_cache_disable' => false,
    'img_max_width' => 1500,
    'img_max_height' => 1500,
    'not_found_image' => 'notavailable.png',
    'error_image'   => '',
    'image_lib'     => 'gdlib',
    'path_to_mogrify' => '/usr/bin',
    'png_is_transparent' => true,
    'optipng_enabled' => false,
    'optipng_path' => '/usr/bin/optipng',
    'pngcrush_enabled' => false,
    'pngcrush_path' => '/usr/bin/pngcrush',
    'wait_between_fetch_errors' => 3600,
    'max_file_size' => 10485760,
);

define('BLOCK_EXTERNAL_LEECHERS', true);
define('ALLOW_ALL_EXTERNAL_SITES', true); 
define('ALLOW_EXTERNAL', true);
define('DEFAULT_Q', 90);
define('DEFAULT_ZC', 1);
define('DEFAULT_F', '');
define('DEFAULT_S', 0);
define('DEFAULT_CC', 'ffffff');
if(! defined('OPTIPNG_ENABLED') )       define ('OPTIPNG_ENABLED', false);
if(! defined('OPTIPNG_PATH') )          define ('OPTIPNG_PATH', '/usr/bin/optipng'); //This will run first because it gives better compression than pngcrush. 
if(! defined('PNGCRUSH_ENABLED') )      define ('PNGCRUSH_ENABLED', false);
if(! defined('PNGCRUSH_PATH') )         define ('PNGCRUSH_PATH', '/usr/bin/pngcrush'); //This will only run if OPTIPNG_PATH is not set or is not valid
//Load a config file if it exists. Otherwise, use the values below
if (file_exists(dirname(__FILE__) . '/config.php'))
    require_once 'config.php';
if(! defined('PNG_IS_TRANSPARENT') )    define ('PNG_IS_TRANSPARENT', FALSE);                       // Define if a png image should have a transparent background color. Use False value if you want to display a custom coloured canvas_colour 
if(! defined('CURL_TIMEOUT') )              define ('CURL_TIMEOUT', 20);                            // Timeout duration for Curl. This only applies if you have


class TimThumb
{
    protected $src = '';
    protected $is404 = false;
    protected $origDirectory = '';
    protected $cacheDirectory = '';
    protected $lastURLError = false;
    protected $localImage = '';
    protected $localImageMTime = 0;
    protected $url = false;
    protected $myHost = '';
    protected $isURL = false;
    protected $cachefile = '';
    protected $errors = array();
    protected $toDeletes = array();
    protected $startTime = 0;
    protected $lastBenchTime = 0;
    protected $cropTop = false;
    // Generally if timthumb.php is modifed (upgraded) then the salt changes
    // and all cache files are recreated. This is a backup mechanism to force
    // regen.
    protected $salt = TIM_VERSION;
    protected $fileCacheVersion = 1;
    // Designed to have three letter mime type, space, question mark and
    // greater than symbol appended. 6 bytes total.
    protected $filePrependSecurityBlock = "<?php die('Execution denied!'); //";
    protected static $curlDataWritten = 0;
    protected static $curlFH = false;
    protected $JpegQuality = 85;
    protected $path_to_mogrify;
    protected $image_lib;

    public static function start()
    {
        global $_IMG_CONF;

        $tim = new TimThumb();
        $tim->handleErrors();
        $tim->securityChecks();
        if($tim->tryBrowserCache()) {
            exit(0);
        }
        $tim->handleErrors();
        if ($_IMG_CONF['file_cache_enabled'] && $tim->tryServerCache()) {
            exit(0);
        }
        $tim->handleErrors();
        $tim->run();
        $tim->handleErrors();
        exit(0);
    }

    public function __construct()
    {
        global $_IMG_CONF;

        $this->startTime = microtime(true);
        date_default_timezone_set('UTC');
        $this->debug(1, "Starting new request from " . $this->getIP() .
            ' to ' . $_SERVER['REQUEST_URI']);

        // LgLib sets configurable config values in $_SESSION, and plugins may
        // override them with their own values
        if (!isset($_SESSION) || empty($_SESSION)) session_start();
        if (is_array($_SESSION['lglib'])) {
            $_IMG_CONF = array_merge($_IMG_CONF, $_SESSION['lglib']);
        }

        // Get the plugin name, if provided, to construct the session
        // variable names.
        if (isset($_GET['plugin'])) {
            $plugin = $_GET['plugin'];
            if (is_array($_SESSION[$plugin])) {
                $_IMG_CONF = array_merge($_IMG_CONF, $_SESSION[$plugin]);
            }
        }
        // At this point, $_IMG_CONF contains all configuration items.

        $this->origDirectory = $_IMG_CONF['origpath'];
        $this->cacheDirectory = $_IMG_CONF['cache_dir'];
        $this->image_lib = $_IMG_CONF['image_lib'];
        $this->path_to_mogrify = $_IMG_CONF['path_to_mogrify'];
        // Clean the cache before we do anything because we don't want the first visitor after
        // FILE_CACHE_TIME_BETWEEN_CLEANS expires to get a stale image. 
        $this->cleanCache();
        
        $this->myHost = preg_replace('/^www\./i', '', $_SERVER['HTTP_HOST']);
        $this->src = $this->param('src');
        $this->url = parse_url($this->src);

        if (strlen($this->src) <= 3) {    // we know there'll be an extension at least
            $this->error("No image specified");
            return false;
        }
        if (BLOCK_EXTERNAL_LEECHERS &&
            array_key_exists('HTTP_REFERER', $_SERVER) &&
            (!preg_match('/^https?:\/\/(?:www\.)?' . $this->myHost . '(?:$|\/)/i', $_SERVER['HTTP_REFERER']))) {
            // base64 encoded red image that says 'no hotlinkers'
            // nothing to worry about! :)
            $imgData = base64_decode("R0lGODlhUAAMAIAAAP8AAP///yH5BAAHAP8ALAAAAABQAAwAAAJpjI+py+0Po5y0OgAMjjv01YUZ\nOGplhWXfNa6JCLnWkXplrcBmW+spbwvaVr/cDyg7IoFC2KbYVC2NQ5MQ4ZNao9Ynzjl9ScNYpneb\nDULB3RP6JuPuaGfuuV4fumf8PuvqFyhYtjdoeFgAADs=");
            header('Content-Type: image/gif');
            header('Content-Length: ' . sizeof($imgData));
            header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');            header("Pragma: no-cache");            header('Expires: ' . gmdate ('D, d M Y H:i:s', time()));
            echo $imgData;            return false;
            exit(0);
        }
        if (preg_match('/^https?:\/\/[^\/]+/i', $this->src)) {
            $this->debug(2, "Is a request for an external URL: " . $this->src);
            $this->isURL = true;
        } else {
            $this->debug(2, "Is a request for an internal file: " . $this->src);
            $this->isURL = false;
        }
        if ($this->isURL) {
            if (!ALLOW_EXTERNAL) {
                $this->error("You are not allowed to fetch images from an external website.");
                return false;
            } elseif (ALLOW_ALL_EXTERNAL_SITES) {
                $this->debug(2, "Fetching from all external sites is enabled.");
            } else {
                $this->debug(2, "Fetching only from selected external sites is enabled.");
                $allowed = false;
                foreach ($ALLOWED_SITES as $site) {
                    if ((strtolower(substr($this->url['host'],-strlen($site)-1)) === strtolower(".$site")) || (strtolower($this->url['host'])===strtolower($site))) {
                        $this->debug(3, "URL hostname {$this->url['host']} matches $site so allowing.");
                        $allowed = true;
                    }
                }
                if (!$allowed) {
                    return $this->error("You may not fetch images from that site. To enable this site in timthumb, you can either add it to \$ALLOWED_SITES and set ALLOW_EXTERNAL=true. Or you can set ALLOW_ALL_EXTERNAL_SITES=true, depending on your security needs.");
                }
            }
            $cachePrefix = '_ext';
            $arr = explode('&', $_SERVER['QUERY_STRING']);
            asort($arr);
            $this->cachefile = $this->cacheDirectory . '/' .
                $_IMG_CONF['cache_prefix'] . $cachePrefix .
                md5($this->salt . implode('', $arr) .
                $this->fileCacheVersion) . $_IMG_CONF['cache_suffix'];
        } else {
            $cachePrefix = ($this->isURL ? '_ext_' : '_int_');
            $this->localImage = $this->getLocalImagePath($this->src);
            if (!$this->localImage) {
                $this->debug(1, "Could not find the local image: {$this->src}");
                if ($_IMG_CONF['not_found_image']) {
                    $this->src = $_IMG_CONF['not_found_image'];
                    $this->localImage = $this->getLocalImagePath($_IMG_CONF['not_found_image']);
                    if (!$this->localImage) return false;
                    else $this->debug(1, 'Using the not-found image instead');
                } else {
                    $this->error("Could not find the internal image you specified.");
                    $this->set404();
                    return false;
                }
            }
            $this->debug(1, "Local image path is {$this->localImage}");
            $this->localImageMTime = @filemtime($this->localImage);
            //We include the mtime of the local file in case in changes on disk.
            $this->cachefile = $this->cacheDirectory . '/' .
                $_IMG_CONF['cache_prefix'].
                md5($this->salt . $this->localImageMTime .
                    $_SERVER ['QUERY_STRING'] . $this->fileCacheVersion);
                //  . FILE_CACHE_SUFFIX;  omit here
        }
        $this->debug(2, "Cache file is: " . $this->cachefile);

        return true;
    }


    public function __destruct()
    {
        foreach($this->toDeletes as $del){
            $this->debug(2, "Deleting temp file $del");
            @unlink($del);
        }
    }


    public function run()
    {
        if ($this->isURL) {
            if (!ALLOW_EXTERNAL) {
                $this->debug(1, "Got a request for an external image but ALLOW_EXTERNAL is disabled so returning error msg.");
                $this->error("You are not allowed to fetch images from an external website.");
                return false;
            }
            $this->debug(3, "Got request for external image. Starting serveExternalImage.");
            if ($this->param('webshot')) {
                if (WEBSHOT_ENABLED) {
                    $this->debug(3, "webshot param is set, so we're going to take a webshot.");
                    $this->serveWebshot();
                } else {
                    $this->error("You added the webshot parameter but webshots are disabled on this server. You need to set WEBSHOT_ENABLED == true to enable webshots.");
                }
            } else {
                $this->debug(3, "webshot is NOT set so we're going to try to fetch a regular image.");
                $this->serveExternalImage();

            }
        } else {
            $this->debug(3, "Got request for internal image. Starting serveInternalImage()");
            $this->serveInternalImage();
        }
        return true;
    }


    protected function handleErrors()
    {
        global $_IMG_CONF;

        if ($this->haveErrors()) { 
            /*if (NOT_FOUND_IMAGE && $this->is404()) {
                if ($this->serveImg($this->origDirectory . '/' . NOT_FOUND_IMAGE)) {
                    exit(0);
                } else {
                    $this->error('Additionally, the 404 image that is configured could not be found or there was an error serving it.');
                }
            }*/

            if ($_IMG_CONF['error_image']) {
                if ($this->serveImg($_IMG_CONF['error_image'])) {
                    exit(0);
                } else {
                    $this->error("Additionally, the error image that is configured could not be found or there was an error serving it.");
                }
            }
            $this->serveErrors();
            exit(0); 
        }
        return false;
    }


    /**
    *   Check the image modified time against the browser to see if we have to
    *   render the image or can just return a not-modified code
    *
    *   @return boolean     True if brower cache is ok, false to send the image
    */
    protected function tryBrowserCache()
    {
        global $_IMG_CONF;

        if ($_IMG_CONF['browser_cache_disable']) {
            $this->debug(3, "Browser caching is disabled");
            return false;
        }

        if (empty($_SERVER['HTTP_IF_MODIFIED_SINCE'])) {
            // don't have a browser cache value, can't check it
            return false;
        }

        $this->debug(3, "Got a conditional get");
        $mtime = false;
        //We've already checked if the real file exists in the constructor
        if (!is_file($this->cachefile . $_IMG_CONF['cache_suffix'])) {
            //If we don't have something cached, regenerate the cached image.
            return false;
        }

        if ($this->localImageMTime) {
            $mtime = $this->localImageMTime;
            $this->debug(3, "Local real file's modification time is $mtime");
        } else {
            //If it's not a local request then use the mtime of the cached file to determine the 304
            $mtime = @filemtime($this->cachefile . $_IMG_CONF['cache_suffix']);
            $this->debug(3, "Cached file's modification time is $mtime");
        }
        if (!$mtime) {
            // Can't figure out image timestamp
            return false;
        }

        // Get the broser if-modified-since value and convert to timestamp
        $iftime = strtotime($_SERVER['HTTP_IF_MODIFIED_SINCE']);
        $this->debug(3, "The conditional get's if-modified-since unixtime is $iftime");
        if ($iftime < 1) {
            $this->debug(3, "Got an invalid conditional get modified since time. Returning false.");
            return false;
        }
        if ($iftime < $mtime) {
            //Real file or cache file has been modified since last request, so force refetch.
            $this->debug(3, "File has been modified since last fetch.");
            return false;
        } else {
            //Otherwise serve a 304
            $this->debug(3, "File has not been modified since last get, so serving a 304.");
            header ($_SERVER['SERVER_PROTOCOL'] . ' 304 Not Modified');
            $this->debug(1, "Returning 304 not modified");
            return true;
        }
    }


    protected function tryServerCache()
    {
        global $_IMG_CONF;

        $this->debug(3, "Trying server cache");
        $cachefile = $this->cachefile . $_IMG_CONF['cache_suffix'];
        if (file_exists($cachefile)) {
            $this->debug(3, "Cachefile $cachefile exists");

            if ($this->serveCacheFile()) {
                $this->debug(3, "Succesfully served cachefile $cachefile");
                return true;
            } else {
                $this->debug(3, "Failed to serve cachefile $this->cachefile - Deleting it from cache.");
                //Image serving failed. We can't retry at this point, but lets remove it from cache so the next request recreates it
                @unlink($cachefile);
                return true;
            }
        }
    }


    protected function error($err)
    {
        $this->debug(3, "Adding error message: $err");
        $this->errors[] = $err;
        return false;

    }

    protected function haveErrors()
    {
        if (sizeof($this->errors) > 0) {
            return true;
        }
        return false;
    }


    protected function serveErrors()
    {
        header ($_SERVER['SERVER_PROTOCOL'] . ' 400 Bad Request');
        $html = '<ul>';
        foreach ($this->errors as $err) {
            $html .= '<li>' . htmlentities($err) . '</li>';
        }
        $html .= '</ul>';
        echo '<h1>A TimThumb error has occured</h1>The following error(s) occured:<br />' . $html . '<br />';
        echo '<br />Query String : ' . htmlentities ($_SERVER['QUERY_STRING']);
        echo '<br />TimThumb version : ' . TIM_VERSION . '</pre>';
    }


    /**
    *   Serves an image.  First trys the cache, then processes the image and
    *   serves again from cache.
    *
    *   @return boolean     True on success, False on failure
    */
    protected function serveInternalImage()
    {
        global $_IMG_CONF;

        $this->debug(3, "Local image path is $this->localImage");
        if (!$this->localImage) {
            $this->sanityFail("localImage not set after verifying it earlier in the code.");
            return false;
        }

        $fileSize = @filesize($this->localImage);
        if ($fileSize > $_IMG_CONF['file_max_size']) {
            $this->error("The file you specified is greater than the maximum allowed file size.");
            return false;
        } elseif ($fileSize <= 0) {
            $this->error("The file you specified is <= 0 bytes.");
            return false;
        }

        if ($this->tryServerCache()) {
            return true;
        } else {
            $this->debug(3, "Calling processImage() for local image.");
            if ($this->processImage($this->localImage)) {
                $this->serveCacheFile();
                return true;
            } else { 
                return false;
            }
        }
    }


    protected function serveExternalImage()
    {
        if (!preg_match('/^https?:\/\/[a-zA-Z0-9\-\.]+/i', $this->src)) {
            $this->error("Invalid URL supplied.");
            return false;
        }
        $tempfile = tempnam($this->cacheDirectory, 'timthumb');
        $this->debug(3, "Fetching external image into temporary file $tempfile");
        $this->toDelete($tempfile);
        #fetch file here
        if(! $this->getURL($this->src, $tempfile)){
            @unlink($this->cachefile);
            touch($this->cachefile);
            $this->debug(3, "Error fetching URL: " . $this->lastURLError);
            $this->error("Error reading the URL you specified from remote host." . $this->lastURLError);
            return false;
        }

        $mimeType = $this->getMimeType($tempfile);
        if(! preg_match("/^image\/(?:jpg|jpeg|gif|png)$/i", $mimeType)){
            $this->debug(3, "Remote file has invalid mime type: $mimeType");
            @unlink($this->cachefile);
            touch($this->cachefile);
            $this->error("The remote file is not a valid image. Mimetype = '" . $mimeType . "'" . $tempfile);
            return false;
        }
        if ($this->processImageAndWriteToCache($tempfile)) {
            $this->debug(3, "Image processed succesfully. Serving from cache");
            return $this->serveCacheFile();
        } else {
            return false;
        }
    }

    /**
    *   Clean old files from the cache.
    *
    *   @return boolean     True on success, False if nothing cleaned
    */
    protected function cleanCache()
    {
        global $_IMG_CONF;

        if ($_IMG_CONF['cache_clean_interval'] < 0) {
            // No cache cleaning required
            return false;
        }
        $this->debug(3, "cleanCache() called");
        $lastCleanFile = $this->cacheDirectory . '/timthumb_cacheLastCleanTime.touch';
        
        //If this is a new timthumb installation we need to create the file
        if (!is_file($lastCleanFile)) {
            $this->debug(1, "File tracking last clean doesn't exist. Creating $lastCleanFile");
            if (!touch($lastCleanFile)) {
                $this->error("Could not create cache clean timestamp file.");
            }
            return false;
        }

        if (@filemtime($lastCleanFile) < (time() - $_IMG_CONF['cache_clean_interval'])) {
            //Cache was last cleaned more than FILE_CACHE_TIME_BETWEEN_CLEANS ago
            $this->debug(1, 'Cache was last cleaned more than ' . $_IMG_CONF['cache_clean_interval'] . ' seconds ago. Cleaning now.');
            // Very slight race condition here, but worst case we'll have 2 or 3 servers cleaning the cache simultaneously once a day.
            if (!touch($lastCleanFile)) {
                $this->error("Could not create cache clean timestamp file.");
            }
            $files = glob($this->cacheDirectory . '/*' . $_IMG_CONF['cache_suffix']);
            if ($files) {
                $timeAgo = time() - $_IMG_CONF['cache_max_age'];
                foreach ($files as $file) {
                    if (@filemtime($file) < $timeAgo) {
                        $this->debug(3, "Deleting cache file $file older than max age: " . $_IMG_CONF['cache_max_age'] . ' seconds');
                        @unlink($file);
                    }
                }
            }
            return true;
        } else {
            $this->debug(3, 'Cache was cleaned less than ' . $_IMG_CONF['cache_clean_interval'] . ' seconds ago so no cleaning needed.');
        }
        return false;
    }


    /**
    *   Perform the image resizing and write the cache file
    *
    *   @param  string  $localImage Full path to image
    *   @return boolean     True on success, False on failure
    */
    protected function processImage($localImage)
    {
        global $_IMG_CONF;

        $sData = getimagesize($localImage);
        $origType = $sData[2];
        $mimeType = $sData['mime'];
        $s_width = $sData[0];
        $s_height = $sData[1];

        $this->debug(3, "Mime type of image is $mimeType");
        if (!preg_match('/^image\/(?:gif|jpg|jpeg|png)$/i', $mimeType)) {
            return $this->error("The image being resized is not a valid gif, jpg or png.");
        }

        // get standard input properties
        $new_width =  (int)abs($this->param('w', 0));
        $new_height = (int)abs($this->param('h', 0));

        // set default width and height if neither are set already
        if ($new_width == 0 && $new_height == 0) {
            $new_width = $s_width;
            $new_height = $s_height;
        }

        // ensure size limits can not be abused
        $new_width = min($new_width, $_IMG_CONF['img_max_width']);
        $new_height = min($new_height, $_IMG_CONF['img_max_height']);

        // set memory limit to be able to have enough space to resize larger images
        //$this->setMemoryLimit();

        // get both sizefactors that would resize one dimension correctly
        if ($new_width > 0 && $s_width > $new_width)
            $sizefactor_w = (double)($new_width / $s_width);
        else
            $sizefactor_w = 1;

        if ($new_height > 0 && $s_height > $new_height)
            $sizefactor_h = (double)($new_height / $s_height);
        else
            $sizefactor_h = 1;

        // Use the smaller factor to stay within the parameters
        $sizefactor = min($sizefactor_w, $sizefactor_h);
        $d_width = (int)($s_width * $sizefactor);
        $d_height = (int)($s_height * $sizefactor);

        //$tempfile = tempnam($this->cacheDirectory, 'photocomp_tmpimg_');
        $lockFile = $this->cachefile . '.lock';
        $fh = fopen($lockFile, 'w');
        if (!$fh) {
            return $this->error("Could not open the lockfile for writing an image.");
        }

        if (flock($fh, LOCK_EX)) {
            // rename generally overwrites, but doing this in case of platform
            // specific quirks. File might not exist yet.
            @unlink($this->cachefile . $_IMG_CONF['cache_suffix']);
            //switch ($_SESSION['image_lib']) {
            switch ($this->image_lib) {
            case 'gdlib':
                $result = $this->gd_imgResize($this->localImage,
                    $this->cachefile,
                    $s_height, $s_width, $d_height, $d_width, $mimeType);
                break;
            case 'imagemagick':
                $result = $this->im_imgResize($this->localImage,
                    $this->cachefile,
                    $s_height, $s_width, $d_height, $d_width, $mimeType);
                break;
            default:
                return false;
                break;
            }
            flock($fh, LOCK_UN);
            fclose($fh);
            @unlink($lockFile);
            if (!$result) return false;
        } else {
            fclose($fh);
            return $this->error("Could not get a lock for writing.");
        }

        return true;
    }


    /**
    *   Get the full path to the local image.
    *
    *   @param  string   $src    Source image name
    *   @return mixed   Full path to image, False on failure
    */
    protected function getLocalImagePath($src)
    {
        $src = ltrim($src, '/'); //strip off the leading '/'
        //Try src under docRoot
        $filePath = $this->origDirectory . '/' . $src;
        if (file_exists($filePath)) {
            $this->debug(3, "Found file as $filePath");
            return $filePath;
        } else {
            return false;
        }
    }


    /**
    *   Serve an internally-cached file to the browser.
    *
    *   @return boolean     True if file could be served, False if not.
    */
    protected function serveCacheFile()
    {
        global $_IMG_CONF;

        //$cachefile = $this->cachefile . $_IMG_CONF['cache_suffix'];
        $cachefile = $this->cachefile;
        $this->debug(3, "Serving $cachefile");

        if (!is_file($cachefile)) {
            $this->error("serveCacheFile called in thumb but we couldn't find the cached file.");
            return false;
        }

        $fp = fopen($cachefile, 'rb');
        if (!$fp) {
            return $this->error("Could not open cachefile.");
        }

        fseek($fp, strlen($this->filePrependSecurityBlock), SEEK_SET);
        $imgType = fread($fp, 3);
        fseek($fp, 3, SEEK_CUR);
        if (ftell($fp) != strlen($this->filePrependSecurityBlock) + 6) {
            @unlink($this->cachefile);
            return $this->error("The cached image file seems to be corrupt.");
        }

        $imageDataSize = filesize($this->cachefile) - (strlen($this->filePrependSecurityBlock) + 6);
        //$imageDataSize = filesize($cachefile);
        $this->sendImageHeaders($imgType, $imageDataSize);
        //$this->sendImageHeaders('image/jpeg', $imageDataSize);
        $bytesSent = @fpassthru($fp);
        fclose($fp);
        if ($bytesSent > 0) {
            return true;
        }

        $content = @file_get_contents($cachefile);
        if ($content != FALSE) {
            $content = substr($content, strlen($this->filePrependSecurityBlock) + 6);
            echo $content;
            $this->debug(3, "Served using file_get_contents and echo");
            return true;
        } else {
            $this->error("Cache file could not be loaded.");
            return false;
        }
    }


    /**
    *   Send the image headers
    *
    *   @param  string  $mimeType   Mime-type of image (image/jpeg, etc.)
    *   @param  integer $dataSize   Size of file
    *   @return boolean     True if headers could be sent, False if not
    */
    protected function sendImageHeaders($mimeType, $dataSize)
    {
        global $_IMG_CONF;

        if (!preg_match('/^image\//i', $mimeType)) {
            $mimeType = 'image/' . $mimeType;
        }
        if (strtolower($mimeType) == 'image/jpg') {
            $mimeType = 'image/jpeg';
        }
        $gmdate_expires = gmdate('D, d M Y H:i:s', strtotime('now +10 days')) . ' GMT';
        $gmdate_modified = gmdate('D, d M Y H:i:s') . ' GMT';

        // send content headers then display image
        header('Content-Type: ' . $mimeType);
        header('Accept-Ranges: none'); //Changed this because we don't accept range requests
        header('Last-Modified: ' . $gmdate_modified);
        header('Content-Length: ' . $dataSize);

        if ($_IMG_CONF['browser_cache_disable']) {
            $this->debug(3, "Browser cache is disabled so setting non-caching headers.");
            header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
            header("Pragma: no-cache");
            header('Expires: ' . gmdate ('D, d M Y H:i:s', time()));
        } else {
            $this->debug(3, "Browser caching is enabled");
            header('Cache-Control: max-age=' . $_IMG_CONF['browser_cache_age']. ', must-revalidate');
            header('Expires: ' . $gmdate_expires);
        }
        return true;
    }

    protected function securityChecks()
    {
    }


    /**
    *   Returns the value of a GET parameter, or a default if not set
    *
    *   @param  string  $property   Name of parameter
    *   @param  mixed   $default    Optional default value
    *   @return mixed       Parameter value if set, or default
    */
    protected function param($property, $default = '')
    {
        if (isset($_GET[$property])) {
            return $_GET[$property];
        } else {
            return $default;
        }
    }


    protected function openImage($mimeType, $src)
    {
        switch ($mimeType) {
        case 'image/jpeg':
            $image = imagecreatefromjpeg($src);
            break;

        case 'image/png':
            $image = imagecreatefrompng($src);
            break;

        case 'image/gif':
            $image = imagecreatefromgif($src);
            break;
            
        default:
            $this->error("Unrecognised mimeType");
        }

        return $image;
    }


    // FIXME: remove function, only used for debug logging
    protected function getIP()
    {
        $rem = @$_SERVER["REMOTE_ADDR"];
        $ff = @$_SERVER["HTTP_X_FORWARDED_FOR"];
        $ci = @$_SERVER["HTTP_CLIENT_IP"];
        if (preg_match('/^(?:192\.168|172\.16|10\.|127\.)/', $rem)) {
            if ($ff) return $ff;
            elseif ($ci) return $ci;
            else return $rem;
        } else {
            if ($rem) return $rem;
            elseif ($ff) return $ff;
            elseif ($ci) return $ci;
            else return "UNKNOWN";
        }
    }


    /**
    *   Write a debug message to the log file
    *   A higher DEBUG_LEVEL causes more logging
    *
    *   @param  integer $level  Severity level
    *   @param  string  $msg    Message to be logged
    */
    protected function debug($level, $msg)
    {
        global $_IMG_CONF;

        if ($level <= $_IMG_CONF['debug_level']) {
            $execTime = sprintf('%.6f', microtime(true) - $this->startTime);
            $tick = sprintf('%.6f', 0);
            if ($this->lastBenchTime > 0) {
                $tick = sprintf('%.6f', microtime(true) - $this->lastBenchTime);
            }
            $this->lastBenchTime = microtime(true);
            error_log("TimThumb Debug line " . __LINE__ . " [$execTime : $tick]: $msg");
        }
    }


    protected function sanityFail($msg)
    {
        return $this->error("Error rendering image: $msg");
    }


    protected function getMimeType($file)
    {
        $info = getimagesize($file);
        if (is_array($info) && $info['mime']) {
            return $info['mime'];
        }
        return '';
    }


    protected function setMemoryLimit()
    {
        global $_IMG_CONF;

        $inimem = ini_get('memory_limit');
        $inibytes = TimThumb::returnBytes($inimem);
        $ourbytes = TimThumb::returnBytes($_IMG_CONF['memory_limit']);
        if ($inibytes < $ourbytes) {
            ini_set('memory_limit', $_IMG_CONF['memory_limit']);
            $this->debug(3, "Increased memory from $inimem to " . $_IMG_CONF['memory_limit']);
        } else {
            $this->debug(3, 'Not adjusting memory size because the current setting is ' . $inimem . ' and our size of ' . $_IMG_CONF['memory_limit'] . ' is smaller.');
        }
    }


    protected static function returnBytes($size_str)
    {
        switch (substr ($size_str, -1)) {
        case 'M': case 'm':
            return (int)$size_str * 1048576;
            break;
        case 'K': case 'k':
            return (int)$size_str * 1024;
            break;
        case 'G': case 'g':
            return (int)$size_str * 1073741824;
            break;
        default:
            return $size_str;
            break;
        }
    }


    protected function serveImg($file)
    {
        $s = getimagesize($file);
        if (!($s && $s['mime'])) {
            return false;
        }

        header('Content-Type: ' . $s['mime']);
        header('Content-Length: ' . filesize($file));
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        header("Pragma: no-cache");
        $bytes = @readfile($file);
        if($bytes > 0){
            return true;
        }
        $content = @file_get_contents($file);
        if ($content != FALSE) {
            echo $content;
            return true;
        }
        return false;

    }


    protected function set404()
    {
        $this->is404 = true;
    }


    protected function is404()
    {
        return $this->is404;
    }


    /**
    *   Calculate the new dimensions needed to keep the image within
    *   the provided width & height while preserving the aspect ratio.
    *
    *   @param  integer $width New width, in pixels
    *   @param  integer $height New height, in pixels
    *   @return array   $old_width, $old_height, $newwidth, $newheight
    */
    protected function reDim($width=0, $height=0)
    {
        $file = $this->filePath();
        $dimensions = getimagesize($this->pathOrig . '/' . $file);
        //$dimensions = getimagesize($this->pathOrig . '/' . $this->basename);
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


    protected function gd_imgResize($srcImage, $destImage, $sImageHeight, $sImageWidth,
        $dImageHeight, $dImageWidth, $mimeType)
    {
        global $_IMG_CONF;

        // set memory limit to be able to have enough space to resize larger images
        $this->setMemoryLimit();

        switch ( $mimeType ) {
        case 'image/jpeg' :
        case 'image/jpg' :
            $image = @imagecreatefromjpeg($srcImage);
            break;
        case 'image/png' :
            $image = @imagecreatefrompng($srcImage);
            break;
        case 'image/bmp' :
            $image = @imagecreatefromwbmp($srcImage);
            break;
        case 'image/gif' :
            $image = @imagecreatefromgif($srcImage);
            break;
        case 'image/x-targa' :
        case 'image/tga' :
            return $this->error('TGA format not supported by GD Libs');
        default :
            return $this->error('IMG_resizeImage: GD Libs only nupport JPG, PNG, and GIF image types.');
        }
        if (!$image) {
            return $this->error('IMG_resizeImage: GD Libs failed to create working image.');
        }
        if (($dImageHeight > $sImageHeight) && ($dImageWidth > $sImageWidth)) {
            $dImageWidth = $sImageWidth;
            $dImageHeight = $sImageHeight;
        }
        $newimage = imagecreatetruecolor($dImageWidth, $dImageHeight);
        imagecopyresampled($newimage, $image, 0,0,0,0,  $dImageWidth, $dImageHeight, $sImageWidth, $sImageHeight);
        imagedestroy($image);
        $destImage .= $_IMG_CONF['cache_suffix'];

        switch ($mimeType) {
        case 'image/jpeg' :
        case 'image/jpg' :
            imagejpeg($newimage,$destImage,$this->JpegQuality);
            break;
        case 'image/png' :
            $pngQuality = ceil(intval(($this->JpegQuality / 100) + 8));
            imagepng($newimage,$destImage,$pngQuality);
            break;
        case 'image/bmp' :
            imagewbmp($newimage,$destImage);
            break;
        case 'image/gif' :
            imagegif($newimage,$destImage);
            break;
        }
        imagedestroy($newimage);
        return true;
    }


    /**
    *   Execute a system command. Taken directly from glFusion's UTL_exec() function
    *
    *   @param  string  $cmd    Command to execute
    *   @return array       Array of (command results, command status)
    */
    protected function execCmd($cmd)
    {
        $status ='';
        $results=array();
        if (PHP_OS == "WINNT") {
            $cmd .= '>NUL 2>&1';
            exec('"' . $cmd . '"', $results, $status);
        } else {
            exec($cmd, $results, $status);
        }
        return array($results, $status);
    }


    /**
    *   Use ImageMagick to resize an image
    *
    *   @uses   TimThumb::execCmd
    */
    protected function im_imgResize($srcImage, $destImage, $sImageHeight, $sImageWidth,
        $dImageHeight, $dImageWidth, $mimeType)
    {
        global $_IMG_CONF;

        //if (empty($_SESSION['path_to_mogrify'])) return false;
        if (empty($this->path_to_mogrify)) return false;

        $newdim = $dImageWidth . 'x' . $dImageHeight;
        //$im_cmd = '"' . $_SESSION['path_to_mogrify'] . "/convert" . '"' . 
        $im_cmd = '"' . $this->path_to_mogrify . "/convert" . '"' . 
                " -flatten -quality {$this->JpegQuality} -size $newdim $srcImage -geometry $newdim $destImage";
        list($results, $status) = $this->execCmd($im_cmd);
        if ($status == 0) {
            @rename($destImage, $destImage . $_IMG_CONF['cache_suffix']);
            return true;
        } else {
            return false;
        }
    }

    protected function toDelete($name)
    {
        $this->debug(3, "Scheduling file $name to delete on destruct.");
        $this->toDeletes[] = $name;
    }


    protected function getURL($url, $tempfile){
        $this->lastURLError = false;
        $url = preg_replace('/ /', '%20', $url);
        if(function_exists('curl_init')){
            $this->debug(3, "Curl is installed so using it to fetch URL.");
            self::$curlFH = fopen($tempfile, 'w');
            if(! self::$curlFH){
                $this->error("Could not open $tempfile for writing.");
                return false;
            }
            self::$curlDataWritten = 0;
            $this->debug(3, "Fetching url with curl: $url");
            $curl = curl_init($url);
            curl_setopt ($curl, CURLOPT_TIMEOUT, CURL_TIMEOUT);
            curl_setopt ($curl, CURLOPT_USERAGENT, "Mozilla/5.0 (Windows NT 6.1) AppleWebKit/534.30 (KHTML, like Gecko) Chrome/12.0.742.122 Safari/534.30");
            curl_setopt ($curl, CURLOPT_RETURNTRANSFER, TRUE);
            curl_setopt ($curl, CURLOPT_HEADER, 0);
            curl_setopt ($curl, CURLOPT_SSL_VERIFYPEER, FALSE);
            curl_setopt ($curl, CURLOPT_WRITEFUNCTION, 'timthumb::curlWrite');
            @curl_setopt ($curl, CURLOPT_FOLLOWLOCATION, true);
            @curl_setopt ($curl, CURLOPT_MAXREDIRS, 10);

            $curlResult = curl_exec($curl);
            fclose(self::$curlFH);
            $httpStatus = curl_getinfo($curl, CURLINFO_HTTP_CODE);
            if($httpStatus == 404){
                $this->set404();
            }
            if($httpStatus == 302){
                $this->error("External Image is Redirecting. Try alternate image url");
                return false;
            }
            if($curlResult){
                curl_close($curl);
                return true;
            } else {
                $this->lastURLError = curl_error($curl);
                curl_close($curl);
                return false;
            }
        } else {
            $img = @file_get_contents ($url);
            if($img === false){
                $err = error_get_last();
                if(is_array($err) && $err['message']){
                    $this->lastURLError = $err['message'];
                } else {
                    $this->lastURLError = $err;
                }
                if(preg_match('/404/', $this->lastURLError)){
                    $this->set404();
                }

                return false;
            }
            if(! file_put_contents($tempfile, $img)){
                $this->error("Could not write to $tempfile.");
                return false;
            }
            return true;
        }

    }

    public static function curlWrite($h, $d)
    {
        global $_IMG_CONF;

        fwrite(self::$curlFH, $d);
        self::$curlDataWritten += strlen($d);
        if (self::$curlDataWritten > $_IMG_CONF['max_file_size']){
            return 0;
        } else {
            return strlen($d);
        }
    }

    protected function processImageAndWriteToCache($localImage)
    {
        global $_IMG_CONF;

        $sData = getimagesize($localImage);
        $origType = $sData[2];
        $mimeType = $sData['mime'];

        $this->debug(3, "Mime type of image is $mimeType");
        if (!preg_match('/^image\/(?:gif|jpg|jpeg|png)$/i', $mimeType)) {
            return $this->error("The image being resized is not a valid gif, jpg or png.");
        }

        if (!function_exists ('imagecreatetruecolor')) {
            return $this->error('GD Library Error: imagecreatetruecolor does not exist - please contact your webhost and ask them to install the GD library');
        }

        if (function_exists ('imagefilter') && defined ('IMG_FILTER_NEGATE')) {
            $imageFilters = array (
                1 => array (IMG_FILTER_NEGATE, 0),
                2 => array (IMG_FILTER_GRAYSCALE, 0),
                3 => array (IMG_FILTER_BRIGHTNESS, 1),
                4 => array (IMG_FILTER_CONTRAST, 1),
                5 => array (IMG_FILTER_COLORIZE, 4),
                6 => array (IMG_FILTER_EDGEDETECT, 0),
                7 => array (IMG_FILTER_EMBOSS, 0),
                8 => array (IMG_FILTER_GAUSSIAN_BLUR, 0),
                9 => array (IMG_FILTER_SELECTIVE_BLUR, 0),
                10 => array (IMG_FILTER_MEAN_REMOVAL, 0),
                11 => array (IMG_FILTER_SMOOTH, 0),
            );
        }
        // get standard input properties        
        $new_width =  (int) abs ($this->param('w', 0));
        $new_height = (int) abs ($this->param('h', 0));
        $zoom_crop = (int) $this->param('zc', DEFAULT_ZC);
        $quality = (int) abs ($this->param('q', DEFAULT_Q));
        $align = $this->cropTop ? 't' : $this->param('a', 'c');
        $filters = $this->param('f', DEFAULT_F);
        $sharpen = (bool) $this->param('s', DEFAULT_S);
        $canvas_color = $this->param('cc', DEFAULT_CC);
        $canvas_trans = (bool) $this->param('ct', '1');

        // set memory limit to be able to have enough space to resize larger images
        $this->setMemoryLimit();

        // open the existing image
        $image = $this->openImage ($mimeType, $localImage);
        if ($image === false) {
            return $this->error('Unable to open image.');
        }

        // Get original width and height
        $width = imagesx($image);
        $height = imagesy($image);
        $origin_x = 0;
        $origin_y = 0;

        // set default width and height if neither are set already
        if ($new_width == 0 && $new_height == 0) {
            $new_width = $width;
            $new_height = $height;
        }

        // ensure size limits can not be abused
        $new_width = min($new_width, $_IMG_CONF['img_max_width']);
        $new_height = min($new_height, $_IMG_CONF['img_max_height']);

        // generate new w/h if not provided
        if ($new_width && !$new_height) {
            $new_height = floor ($height * ($new_width / $width));
        } else if ($new_height && !$new_width) {
            $new_width = floor ($width * ($new_height / $height));
        }
        // scale down and add borders
        if ($zoom_crop == 3) {

            $final_height = $height * ($new_width / $width);

            if ($final_height > $new_height) {
                $new_width = $width * ($new_height / $height);
            } else {
                $new_height = $final_height;
            }

        }

        // create a new true color image
        $canvas = imagecreatetruecolor ($new_width, $new_height);
        imagealphablending ($canvas, false);

        if (strlen($canvas_color) == 3) { //if is 3-char notation, edit string into 6-char notation
            $canvas_color =  str_repeat(substr($canvas_color, 0, 1), 2) . str_repeat(substr($canvas_color, 1, 1), 2) . str_repeat(substr($canvas_color, 2, 1), 2);
        } else if (strlen($canvas_color) != 6) {
            $canvas_color = DEFAULT_CC; // on error return default canvas color
        }

        $canvas_color_R = hexdec (substr ($canvas_color, 0, 2));
        $canvas_color_G = hexdec (substr ($canvas_color, 2, 2));
        $canvas_color_B = hexdec (substr ($canvas_color, 4, 2));

        // Create a new transparent color for image
        // If is a png and PNG_IS_TRANSPARENT is false then remove the alpha transparency 
        // (and if is set a canvas color show it in the background)
        if(preg_match('/^image\/png$/i', $mimeType) && !PNG_IS_TRANSPARENT && $canvas_trans){
            $color = imagecolorallocatealpha ($canvas, $canvas_color_R, $canvas_color_G, $canvas_color_B, 127);
        }else{
            $color = imagecolorallocatealpha ($canvas, $canvas_color_R, $canvas_color_G, $canvas_color_B, 0);
        }


        // Completely fill the background of the new image with allocated color.
        imagefill ($canvas, 0, 0, $color);

        // scale down and add borders
        if ($zoom_crop == 2) {

            $final_height = $height * ($new_width / $width);

            if ($final_height > $new_height) {

                $origin_x = $new_width / 2;
                $new_width = $width * ($new_height / $height);
                $origin_x = round ($origin_x - ($new_width / 2));

            } else {

                $origin_y = $new_height / 2;
                $new_height = $final_height;
                $origin_y = round ($origin_y - ($new_height / 2));

            }

        }

        // Restore transparency blending
        imagesavealpha ($canvas, true);

        if ($zoom_crop > 0) {

            $src_x = $src_y = 0;
            $src_w = $width;
            $src_h = $height;

            $cmp_x = $width / $new_width;
            $cmp_y = $height / $new_height;

            // calculate x or y coordinate and width or height of source
            if ($cmp_x > $cmp_y) {

                $src_w = round ($width / $cmp_x * $cmp_y);
                $src_x = round (($width - ($width / $cmp_x * $cmp_y)) / 2);

            } else if ($cmp_y > $cmp_x) {

                $src_h = round ($height / $cmp_y * $cmp_x);
                $src_y = round (($height - ($height / $cmp_y * $cmp_x)) / 2);

            }

            // positional cropping!
            if ($align) {
                if (strpos ($align, 't') !== false) {
                    $src_y = 0;
                }
                if (strpos ($align, 'b') !== false) {
                    $src_y = $height - $src_h;
                }
                if (strpos ($align, 'l') !== false) {
                    $src_x = 0;
                }
                if (strpos ($align, 'r') !== false) {
                    $src_x = $width - $src_w;
                }
            }

            imagecopyresampled ($canvas, $image, $origin_x, $origin_y, $src_x, $src_y, $new_width, $new_height, $src_w, $src_h);

        } else {

            // copy and resize part of an image with resampling
            imagecopyresampled ($canvas, $image, 0, 0, 0, 0, $new_width, $new_height, $width, $height);

        }

        if ($filters != '' && function_exists ('imagefilter') && defined ('IMG_FILTER_NEGATE')) {
            // apply filters to image
            $filterList = explode ('|', $filters);
            foreach ($filterList as $fl) {

                $filterSettings = explode (',', $fl);
                if (isset ($imageFilters[$filterSettings[0]])) {

                    for ($i = 0; $i < 4; $i ++) {
                        if (!isset ($filterSettings[$i])) {
                            $filterSettings[$i] = null;
                        } else {
                            $filterSettings[$i] = (int) $filterSettings[$i];
                        }
                    }

                    switch ($imageFilters[$filterSettings[0]][1]) {

                        case 1:

                            imagefilter ($canvas, $imageFilters[$filterSettings[0]][0], $filterSettings[1]);
                            break;

                        case 2:

                            imagefilter ($canvas, $imageFilters[$filterSettings[0]][0], $filterSettings[1], $filterSettings[2]);
                            break;
                        case 3:

                            imagefilter ($canvas, $imageFilters[$filterSettings[0]][0], $filterSettings[1], $filterSettings[2], $filterSettings[3]);
                            break;

                        case 4:

                            imagefilter ($canvas, $imageFilters[$filterSettings[0]][0], $filterSettings[1], $filterSettings[2], $filterSettings[3], $filterSettings[4]);
                            break;

                        default:

                            imagefilter ($canvas, $imageFilters[$filterSettings[0]][0]);
                            break;

                    }
                }
            }
        }

        // sharpen image
        if ($sharpen && function_exists ('imageconvolution')) {

            $sharpenMatrix = array (
                    array (-1,-1,-1),
                    array (-1,16,-1),
                    array (-1,-1,-1),
                    );

            $divisor = 8;
            $offset = 0;

            imageconvolution ($canvas, $sharpenMatrix, $divisor, $offset);

        }
        //Straight from Wordpress core code. Reduces filesize by up to 70% for PNG's
        if ( (IMAGETYPE_PNG == $origType || IMAGETYPE_GIF == $origType) && function_exists('imageistruecolor') && !imageistruecolor( $image ) && imagecolortransparent( $image ) > 0 ){
            imagetruecolortopalette( $canvas, false, imagecolorstotal( $image ) );
        }

        $imgType = "";
        $tempfile = tempnam($this->cacheDirectory, 'timthumb_tmpimg_');
        if(preg_match('/^image\/(?:jpg|jpeg)$/i', $mimeType)){
            $imgType = 'jpg';
            imagejpeg($canvas, $tempfile, $quality);
        } else if(preg_match('/^image\/png$/i', $mimeType)){
            $imgType = 'png';
            imagepng($canvas, $tempfile, floor($quality * 0.09));
        } else if(preg_match('/^image\/gif$/i', $mimeType)){
            $imgType = 'gif';
            imagegif($canvas, $tempfile);
        } else {
            return $this->sanityFail("Could not match mime type after verifying it previously.");
        }

        if($imgType == 'png' && OPTIPNG_ENABLED && OPTIPNG_PATH && @is_file(OPTIPNG_PATH)){
            $exec = OPTIPNG_PATH;
            $this->debug(3, "optipng'ing $tempfile");
            $presize = filesize($tempfile);
            $out = `$exec -o1 $tempfile`; //you can use up to -o7 but it really slows things down
            clearstatcache();
            $aftersize = filesize($tempfile);
            $sizeDrop = $presize - $aftersize;
            if($sizeDrop > 0){
                $this->debug(1, "optipng reduced size by $sizeDrop");
            } else if($sizeDrop < 0){
                $this->debug(1, "optipng increased size! Difference was: $sizeDrop");
            } else {
                $this->debug(1, "optipng did not change image size.");
            }
        } else if($imgType == 'png' && PNGCRUSH_ENABLED && PNGCRUSH_PATH && @is_file(PNGCRUSH_PATH)){
            $exec = PNGCRUSH_PATH;
            $tempfile2 = tempnam($this->cacheDirectory, 'timthumb_tmpimg_');
            $this->debug(3, "pngcrush'ing $tempfile to $tempfile2");
            $out = `$exec $tempfile $tempfile2`;
            $todel = "";
            if(is_file($tempfile2)){
                $sizeDrop = filesize($tempfile) - filesize($tempfile2);
                if($sizeDrop > 0){
                    $this->debug(1, "pngcrush was succesful and gave a $sizeDrop byte size reduction");
                    $todel = $tempfile;
                    $tempfile = $tempfile2;
                } else {
                    $this->debug(1, "pngcrush did not reduce file size. Difference was $sizeDrop bytes.");
                    $todel = $tempfile2;
                }
            } else {
                $this->debug(3, "pngcrush failed with output: $out");
                $todel = $tempfile2;
            }
            @unlink($todel);
        }

        $this->debug(3, "Rewriting image with security header.");
        $tempfile4 = tempnam($this->cacheDirectory, 'timthumb_tmpimg_');
        $context = stream_context_create ();
        $fp = fopen($tempfile,'r',0,$context);
        file_put_contents($tempfile4, $this->filePrependSecurityBlock . $imgType . ' ?' . '>'); //6 extra bytes, first 3 being image type 
        file_put_contents($tempfile4, $fp, FILE_APPEND);
        fclose($fp);
        @unlink($tempfile);
        $this->debug(3, "Locking and replacing cache file.");
        $lockFile = $this->cachefile . '.lock';
        $fh = fopen($lockFile, 'w');
        if(! $fh){
            return $this->error("Could not open the lockfile for writing an image.");
        }
        if(flock($fh, LOCK_EX)){
            @unlink($this->cachefile); //rename generally overwrites, but doing this in case of platform specific quirks. File might not exist yet.
            rename($tempfile4, $this->cachefile);
            flock($fh, LOCK_UN);
            fclose($fh);
            @unlink($lockFile);
        } else {
            fclose($fh);
            @unlink($lockFile);
            @unlink($tempfile4);
            return $this->error("Could not get a lock for writing.");
        }
        $this->debug(3, "Done image replace with security header. Cleaning up and running cleanCache()");
        imagedestroy($canvas);
        imagedestroy($image);
        return true;
    }


}

TimThumb::start();

?>
