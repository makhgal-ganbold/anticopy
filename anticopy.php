<?php

/**
* @package       System - AntiCopy
* @author        Galaa
* @publisher     JExtBOX - BOX of Joomla Extensions (www.jextbox.com)
* @authorUrl     www.galaa.mn
* @copyright     Copyright (C) 2011-2021 Galaa
* @license       This extension in released under the GNU/GPL License - http://www.gnu.org/copyleft/gpl.html
*/

// no direct access
defined('_JEXEC') or die;

// Import library dependencies
jimport( 'joomla.plugin.plugin' );

class plgSystemAntiCopy extends JPlugin {

	function onAfterRender() {

		// Skip backend
		if (JFactory::getApplication()->isClient('administrator'))
			return;

		// Get HTML
		$html = JFactory::getApplication()->getBody();

		// Prevent framing
		if ($this->params->get('disallow_framing', 1))
			$html = str_ireplace('</head>', '<script>if (!window.top.location.href.startsWith("'.JURI::base().'") && window.top.location.href != window.self.location.href) window.top.location.href = window.self.location.href;</script>'."\n".'</head>', $html);

		// Define a current user's groups
		$user_groups = JFactory::getUser()->getAuthorisedGroups();

		// Restricted groups
		$restricted_groups = $this->params->get('restrict_groups', array());
		settype($restricted_groups, 'array');

		// Add Public and Guest groups to the restricted groups
		if (!in_array(1, $restricted_groups))
			array_push($restricted_groups, 1);
		if (!in_array(9, $restricted_groups))
			array_push($restricted_groups, 9);

		// Set Permission
		if (count(array_diff($user_groups, $restricted_groups)) == 0) {

			// Disable right click
			switch ($this->params->get('disallow_r_click', 1)) {
				case 0:
					break;
				case 1:
					$html = str_ireplace('</head>', '<script>
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
						}
					</script>'."\n".'</head>', $html);
					break;
				case 2:
					$html = str_ireplace('<img ', '<img oncontextmenu="return false" ', $html);
					break;
			}

			// Prevent page being printed
			if ($this->params->get('disallow_print', 1))
				$html = str_ireplace('</head>', '<style type="text/css">@media print{body{display:none !important;}}</style>'."\n".'</head>', $html);

			// Disallow dragging
			if ($this->params->get('disallow_drag', 1))
				$html = str_ireplace('</head>', '<script>
					if (document.addEventListener) {
						document.addEventListener("dragstart", function(e){e.preventDefault();return false;});
					} else if (document.attachEvent) {
						document.attachEvent("ondragstart", function(e){e.preventDefault();return false;});
					}
					</script>'."\n".'</head>', $html);

			// Restrict copying
			if ($this->params->get('disallow_copy', 1)) {
				$notification = $this->params->get('show_message', 0) ? trim($this->params->get('message', 'Stop copying the copyrighted material!')) : '';
				$html = str_ireplace('</head>', '<script>
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
					}
				</script>'."\n".'</head>', $html);
				if (!empty($notification))
					$html = str_ireplace('</body>', '
<div id="JExtBOXAntiCopyModal" style="display:none;position:fixed;z-index:1000;left:0;top:0;width:100%;height:100%;overflow:auto;background-color:rgb(0,0,0);background-color: rgba(0,0,0,0.4);">
	<div style="background-color:#fefefe;margin:10% auto;padding:2em;border:none;width:75%;text-align:center;">
		'.$notification.'
	</div>
</div>'."\n".'</body>', $html);
				}

			// Make HTML hard to read
			if ($this->params->get('sanitize_html', 1) || $this->params->get('encode_html', 1))
				$html = preg_replace(array(
					'/>[^\S ]+</',	// whitespaces between tags, except space
					'/[ \t]+/'			// whitespace sequences
				), array(
					'> <',
					' '
				), $html);

		}

		// Set HTML
		JFactory::getApplication()->setBody($html);

	}

}

?>
