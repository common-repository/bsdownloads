<?php
/*
Plugin Name: BSDownloads
Plugin URI: http://wordpress.org/extend/plugins/bsdownloads/
Description: Listing of files for download
Version: 1.0
Author: Michal Nezerka 
Author URI: http://blue.pavoucek.cz
Text Domain: bsdownloads
Domain Path: /lang
*/

class BSDownloads
{
	var $_formatters;

	// single instance of this class (singleton pattern)
    private static $instance; 

	protected $pluginPath;
	protected $pluginUrl;

	// get instance of this class (singleton pattern)
	public static function getInstance()
	{
		if (!self::$instance)
			self::$instance = new BSDownloads();
		return self::$instance;
	}

	// constructor
	public function __construct()  
	{  
		$this->pluginPath = plugin_dir_path(__FILE__);
		$this->pluginUrl = plugin_dir_url(__FILE__);

		add_filter('query_vars', array($this, 'filterQueryVars'));
		add_shortcode('bsdownloads', array($this, 'shortcodeDownloads'));
		$this->_formatters = array('default' => 'BSDownloadsFormatterDefault');
	}

	function addFormatter($key, $className)
	{
		//	if (!is_a($obj, 'BSDownloadsFormatterDefault'))
		//			return;
		$this->_formatters[$key] = $className;
	}

	function filterQueryVars($qvars)
	{
		$qvars[] = 'bsdir';
		return $qvars;
	}

	function shortcodeDownloads($atts)
	{
		$atts = shortcode_atts(array(
			'source' => 'att',
			'path' => NULL,
			'show_parent' => false,
			'class' => NULL,
			'fmt' => 'default',
			'filter' => NULL
		), $atts); 


		if (is_null($atts['source']))
			return "No source specified.";

		if (!isset($this->_formatters[$atts['fmt']]))
			return "Formatter $fmt doesn't exist.";

		$formatter = new $this->_formatters[$atts['fmt']]($atts);
		if (!is_a($formatter, 'BSDownloadsFormatterDefault'))
			return 'Formatter ' . $atts['fmt'] . ' isn\'t valid BSDownloads formatter.';

		switch ($atts['source']) {
			case 'att':
				return $this->getFromAttachments($atts, $formatter);
			case 'fs':
				return $this->getFromFileSystem($atts);
			default:
				return 'unknown source';
		}
	}

	function getFromAttachments($atts, $formatter)
	{
		global $post;

		$result = $formatter->format_begin($class);
		$result .= $formatter->format_header();

		if (is_string($atts['filter']))
		{
			// expand list and trim individual items
			$filter = explode(',', $atts['filter']); 
			for ($i = 0; $i < count($filter); $i++)
				$filter[$i] = trim($filter[$i]);
		}
		else
			$filter = NULL;

		$args = array(
			'post_type' => 'attachment',
			'numberposts' => null,
			'post_status' => null,
			'post_parent' => $post->ID
		); 
		$attachments = get_posts($args);
		if ($attachments)
		{
			foreach ($attachments as $attachment) {
				$filePath = get_attached_file($attachment->ID, false);
				$fileExt = pathinfo($filePath, PATHINFO_EXTENSION);
				if (is_array($filter) && !in_array($fileExt, $filter))
					continue;
				$result .= $formatter->format_entry(
					apply_filters('the_title', $attachment->post_title),
					get_attached_file($attachment->ID, false),
				 	wp_get_attachment_url($attachment->ID, false));
			}
		}
		$result .= $formatter->format_footer($class);
		$result .= $formatter->format_end($class);

		return $result;
	}

	function getFromFileSystem($atts)
	{
		global $wp_query;

		# root directory for this bsview
		$rootDir = path_join(ABSPATH, $path);
		$rootUrl = get_site_url() . '/' . $path;

		if (!is_dir($rootDir))
			return "directory $rootDir doesn't exist";

		# decode subdirectory
		if (isset($wp_query->query_vars['bsdir']))
		{
			$subDir = base64_decode(urldecode($wp_query->query_vars['bsdir']));
			$parentSubDir = substr($subDir, 0, strrpos($subDir, '/'));
		}
		else
			$parentSubDir = NULL; 

		$currentDir = path_join($rootDir, $subDir);
		$currentUrl = $rootUrl . '/' . $subDir;


		$resultDirs = '';
		$resultFiles = '';
		$resultInfo = '';

		# add link to parent item
		if (!is_null($parentSubDir))
		{
			$parentDir = path_join($path, $parentSubDir);
			$parentUrl = strlen($parentSubDir) > 0 ? add_query_arg('bsdir', base64_encode($parentSubDir)) : get_permalink();
			$resultDirs .= $formatter->format_entry('..', $parentDir, $parentUrl);
		}

		# loop through all files in directory
		if ($handle = opendir($currentDir))
		{
			while (false !== ($fileName = readdir($handle)))
			{
				# skip parent and current folders
				if ($fileName == '.' || $fileName == '..')
					continue;

				# physical path to file
				$filePath = $currentDir . '/' . $fileName;

				# read readme file
				if (strtolower($fileName) == 'readme.txt')
				{
					$resultInfo = file_get_contents($filePath);
					continue;
				}

				# link to file
				if (is_dir($filePath))
				{
					$relativeUrl = strlen($subDir) > 0 ?  path_join($subDir, $fileName) : $fileName;
					$fileUrl = add_query_arg('bsdir', base64_encode($relativeUrl));
					$resultDirs .= $formatter->format_entry($fileName, $filePath, $fileUrl);
				}
				else
				{
					$fileUrl = path_join($currentUrl, $fileName);
					$resultFiles .= $formatter->format_entry($fileName, $filePath, $fileUrl);
				}
			}
		}

		$result .= $formatter->format_begin($class);
		$result .= $formatter->format_info($resultInfo);
		$result .= $formatter->format_header();
		$result .= $resultDirs;
		$result .= $resultFiles;
		$result .= $formatter->format_footer($class);
		$result .= $formatter->format_end($class);

		return $result;
	}
}

/* default template functions */
class BSDownloadsFormatterDefault
{
	// constructor
	public function __construct($atts)
	{  
		$this->_atts = $atts;
	}

	function format_begin($class) {
		$result = '<div class="bsdownloads';
		if (isset($this->_atts['class']))
		   	$result .= ' ' . $this->_atts['class'];
		$result .= '">';
		return $result;
	}
	function format_end($class) { return '</div>'; }
	function format_header() { return '<ul>'; }
	function format_footer() { return '</ul>'; }
	function format_info($info) { return '<div class="info">' . $info . '</div>'; }
	function format_entry($fileName, $filePath, $fileUrl)
	{
		$result = '<li><a href="' . $fileUrl . '">';
		$result .= '<img src="' . $this->get_icon($filePath) . '" />';
		$result .= '<span class="filename">' . $fileName . '</span><span class="filesize"></span>';
		if (is_file($filePath))
			$result .= '<span class="filesize">(' . $this->getHumanReadableFilesize(filesize($filePath)) . ')</span>';
		$result .= '</a></li>';
		return $result;
	}

	function get_icon($filePath)
	{
		$icon = 'file.png';

		if (is_dir($filePath))
		{
			$icon = 'folder.gif';
		}
		else
		{
			$extension = pathinfo($filePath, PATHINFO_EXTENSION); 
			switch ($extension)
			{
				case 'tab':
				case 'tef':
				case 'gpx':
					$icon = 'file.png';
					break;
				case 'xml':
					$icon = 'xml.png';
					break;
				case 'jpg':
				case 'jpeg':
				case 'gif':
				case 'png':
					$icon = 'image.png';
					break;
				case 'pdf':
					$icon = 'acrobat.png';
					break;
			}	

		}

		return plugins_url('icons/' . $icon, __FILE__ );
	}

	function getHumanReadableFilesize($size)
	{
    	$mod = 1024;
 
    	$units = explode(' ','B KB MB GB TB PB');
    	for ($i = 0; $size > $mod; $i++) {
			$size /= $mod;
		}
 
		return round($size, 1) . ' ' . $units[$i];
}


}
// create plugin instance at right time
add_action('plugins_loaded', 'BSDownloads::getInstance');

?>
