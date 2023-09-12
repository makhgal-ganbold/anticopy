<?php

/**
* @package       System - AntiCopy
* @author        Galaa
* @publisher     JExtBOX - BOX of Joomla Extensions (www.jextbox.com)
* @authorUrl     www.galaa.net
* @copyright     Copyright (C) 2011-2021 Galaa
* @license       This extension in released under the GNU/GPL License - http://www.gnu.org/copyleft/gpl.html
*/

// no direct access
defined('_JEXEC') or die;

// Import library dependencies
jimport( 'joomla.plugin.plugin' );

class plgSystemAntiCopy extends JPlugin {

	private $skip = false;

	/**
	 * Constructor.
	 *
	 * @param   object  &$subject  The object to observe.
	 * @param   array   $config	An optional associative array of configuration settings.
	 *
	 * @since   1.0
	 */
	public function __construct(&$subject, $config) {

		// Calling the parent Constructor
		parent::__construct($subject, $config);

		// Skip backend
		if (JFactory::getApplication()->isClient('administrator')) {
			$this->skip = true;
			return;
		}

		// Skip AJAX
		if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] === 'XMLHttpRequest') {
			$this->skip = true;
			return;
		}

		// Define a current user's groups
		$user_groups = JFactory::getUser()->getAuthorisedGroups();
		// Restricted groups
		$restricted_groups = $this->params->get('restrict_groups', array());
		settype($restricted_groups, 'array');
		// Add Public and Guest groups to the list of restricted groups
		if (!in_array(1, $restricted_groups))
			array_push($restricted_groups, 1);
		if (!in_array(9, $restricted_groups))
			array_push($restricted_groups, 9);
		// Check permission
		$restricted = count(array_diff($user_groups, $restricted_groups)) == 0;
		// Skip permitted user groups
		if (!$restricted) {
			$this->skip = true;
			return;
		}

		// Excluded URLs
		$excluded_urls = explode(PHP_EOL, strtolower($this->params->get('excluded_urls', '')));
		foreach ($excluded_urls as &$excluded_url)
			$excluded_url = trim($excluded_url);
		// Check URL exclusion with exact and wildcard pattern matching
		$current_url = strtolower(\Joomla\CMS\Uri\Uri::getInstance()->toString());
		$excluded = false;
		foreach ($excluded_urls as $excluded_url) {
			if ($current_url == $excluded_url || preg_match('/^'.str_replace('\*', '.*', preg_quote($excluded_url, '/')).'$/i', $current_url)) {
				$excluded = true;
				break;
			}
		}
		// Skip excluded URLs
		if ($excluded) {
			$this->skip = true;
			return;
		}

	}

	function onBeforeCompileHead() {

		// Get document
		$doc = JFactory::getDocument();

		// Prevent framing
		if ($this->params->get('disallow_framing', 1))
			$doc->addScriptDeclaration('
	if (!window.top.location.href.startsWith("'.JURI::base().'") && window.top.location.href != window.self.location.href)
		window.top.location.href = window.self.location.href;');

		// Skip
		if ($this->skip) {
			return;
		}

		// Disable right click
		if ($this->params->get('disallow_r_click', 1) == 1)
			$doc->addScriptDeclaration('
	if (document.addEventListener) {
		document.addEventListener("contextmenu", function(e){
			e.preventDefault();
			return false;
		});
	} else if (document.attachEvent) {
		document.attachEvent("oncontextmenu", function(e){
			e.preventDefault();
			return false;
		});
	}');

		// Prevent page being printed
		if ($this->params->get('disallow_print', 1))
			$doc->addStyleDeclaration('@media print{body{display:none !important;}}');

		// Disallow dragging
		if ($this->params->get('disallow_drag', 1))
			$doc->addScriptDeclaration('
	if (document.addEventListener) {
		document.addEventListener("dragstart", function(e){e.preventDefault();return false;});
	} else if (document.attachEvent) {
		document.attachEvent("ondragstart", function(e){e.preventDefault();return false;});
	}');

		// Restrict copying
		if ($this->params->get('disallow_copy', 1)) {
			$notification = $this->params->get('show_message', 0) ? trim($this->params->get('message', 'Stop copying the copyrighted material!')) : '';
			$doc->addScriptDeclaration('
	function JExtBOXAntiCopyShowMSG() {
		if ('.(!empty($notification) ? 'true' : 'false').') {
			document.getElementById("JExtBOXAntiCopyModal").style.display="block";
		}
	}
	if (document.addEventListener) {
		document.addEventListener("copy", function(e){
			e.preventDefault();
			JExtBOXAntiCopyShowMSG();
			return false;
		});
		document.addEventListener("cut", function(e){
			e.preventDefault();
			JExtBOXAntiCopyShowMSG();
			return false;
		});
		document.addEventListener("click", function(e){
			if (e.target == document.getElementById("JExtBOXAntiCopyModal")) {
				document.getElementById("JExtBOXAntiCopyModal").style.display="none";
			}
		});
	} else if (document.attachEvent) {
		document.attachEvent("oncopy", function(e){
			e.preventDefault();
			JExtBOXAntiCopyShowMSG();
			return false;
		});
		document.attachEvent("oncut", function(e){
			e.preventDefault();
			JExtBOXAntiCopyShowMSG();
			return false;
		});
		document.attachEvent("onclick", function(e){
			if (e.target == document.getElementById("JExtBOXAntiCopyModal")) {
				document.getElementById("JExtBOXAntiCopyModal").style.display="none";
			}
		});
	}');
		}

	}

	function onAfterRender() {

		// Skip
		if ($this->skip) {
			return;
		}

		// Get HTML
		$html = JFactory::getApplication()->getBody();

		// Disable right click for images
		if ($this->params->get('disallow_r_click', 1) == 2) {
			$html = str_ireplace('<img ', '<img oncontextmenu="return false" ', $html);
		}

		// Message on copy and cut
		if ($this->params->get('disallow_copy', 1) && $this->params->get('show_message', 0)) {
			$notification =  trim($this->params->get('message', 'Stop copying the copyrighted material!'));
			if (!empty($notification))
				$html = str_ireplace('</body>', '
<div id="JExtBOXAntiCopyModal" style="display:none;position:fixed;z-index:1000;left:0;top:0;width:100%;height:100%;overflow:auto;background-color:rgb(0,0,0);background-color: rgba(0,0,0,0.4);">
<div style="background-color:#fefefe;margin:10% auto;padding:2em;border:none;width:75%;text-align:center;">
	'.$notification.'
</div>
</div>'."\n".'</body>', $html);
		}

		// Make HTML hard to read
		if ($this->params->get('sanitize_html', 1)) {
			// sequential new lines with white spaces between tags
			$i = 0;
			while (preg_match_all('/<\/?[a-z0-9]+[^>]*>[\s]*[\r\n][\s]*<\/?[a-z0-9]+[^>]*>/iU', $html) && $i < 10) {
				$i++;
				$html = preg_replace('/(<\/?[a-z0-9]+[^>]*>[\s]*)[\r\n]([\s]*<\/?[a-z0-9]+[^>]*>)/iU', '$1$2', $html);
			}
			// sequential tabs and spaces between tags
			$i = 0;
			while (preg_match_all('/<\/?[a-z0-9]+[^>]*>[\s]{2,}<\/?[a-z0-9]+[^>]*>/iU', $html) && $i < 10) {
				$i++;
				$html = preg_replace('/(<\/?[a-z0-9]+[^>]*>)[\s]{2,}(<\/?[a-z0-9]+[^>]*>)/iU', '$1 $2', $html);
			}
		}

		// Set HTML
		JFactory::getApplication()->setBody($html);

	}

}

?>
