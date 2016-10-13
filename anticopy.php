<?php

/**
* @package       System - AntiCopy
* @author        Galaa
* @publisher     JExtBOX - BOX of Joomla Extensions (www.jextbox.com)
* @authorUrl     www.galaa.mn
* @copyright     Copyright (C) 2011-2016 Galaa
* @license       This extension in released under the GNU/GPL License - http://www.gnu.org/copyleft/gpl.html
*/

// no direct access
defined('_JEXEC') or die;

// Import library dependencies
jimport( 'joomla.plugin.plugin' );

class plgSystemAntiCopy extends JPlugin {

	function onAfterRender() {

		// Backend
		$app = JFactory::getApplication();
		if($app->isAdmin()) return;

		// Prepare Script
		$html = JResponse::getBody();

		// Check User
		$user = JFactory::getUser();
		$user_groups = $user->getAuthorisedGroups();
		$restricted_groups = $this->params->get('restrict_groups', array());
		settype($restricted_groups, 'array');

		// Auto add Public and Guest
		if(!in_array(1, $restricted_groups) && !empty($restricted_groups)){
			array_push($restricted_groups, 1);
		}elseif(in_array(1, $restricted_groups) && count($restricted_groups) == 1){
			array_push($restricted_groups, 9);
		}

		// Set Permission
		if(count(array_diff($user_groups, $restricted_groups)) == 0) {

			// Prevent page from being printed
			if($this->params->get('disallow_print')) {
				$html = preg_replace("/<\/head>/", '<style type="text/css"> @media print { body { display:none } } </style>' . "\n</head>", $html);
			} // Prevent page from being printed

			// Try to prevent print screen
			if($this->params->get('disallow_printscreen')) {
				$printscreen = '
<script type="text/javascript">
$(document).ready(function() {
	$(window).keydown(function(e){
		if(e.keyCode == 44){
			e.preventDefault();
		}
	});
	$(window).focus(function() {
		$("body").show();
	}).blur(function() {
		$("body").hide();
	});
}); 
</script>';
				$html = preg_replace("/<\/head>/", $printscreen . "\n</head>", $html);
			} // Try to prevent print screen

			// Popup Message
			if($this->params->get('show_message')) {
				$comment = "";
			} else {
				$comment = "//"; // JS comment disables the function alert
			}
			$message = trim($this->params->get('message'));

			// Ban Right Click
			switch($this->params->get('disallow_r_click')){
				case 0:
					break;
				case 1:
					$r_click = "
<script type=\"text/javascript\">
	function clickExplorer() {
		if( document.all ) {
			${comment}alert('".$message."');
		}
		return false;
	}
	function clickOther(e) {
		if( document.layers || ( document.getElementById && !document.all ) ) {
			if ( e.which == 2 || e.which == 3 ) {
				${comment}alert('".$message."');
				return false;
			}
		}
	}
	if( document.layers ) {
		document.captureEvents( Event.MOUSEDOWN );
		document.onmousedown=clickOther;
	}
	else {
		document.onmouseup = clickOther;
		document.oncontextmenu = clickExplorer;
	}
</script>";
					$html = preg_replace("/<\/head>/", $r_click . "\n</head>", $html);
					break;
				case 2:
					$html = preg_replace('/<img /', '<img oncontextmenu="return false" ', $html);
					break;
			} // Ban Right Click

			// Disable Copy and Drag
			if($this->params->get('disallow_copy')) {

				$copy = "
<script type=\"text/javascript\">
document.addEventListener('dragstart', function(e){
    e.preventDefault();
});
document.addEventListener('copy', function(e){
    e.preventDefault();
	${comment}alert('".$message."');
});
</script>";
				$html = preg_replace("/<\/head>/", $copy . "\n</head>", $html);

				$html = preg_replace('/<\/head>/', '<meta http-equiv="imagetoolbar" content="no">'."\n</head>", $html);

			} // Disable Copy and Drag

		} // Set Permission

		// Restrict the framing
		if($this->params->get('disallow_framing')) {
			$framing = "
<script type=\"text/javascript\">
	if (top!==self) {
		top.location=location;
	}
</script>";
			$html = preg_replace("/<\/body>/", $framing . "\n</body>", $html);
		} // Restrict the framing

		// Response
		JResponse::setBody($html);
		return true;

	} // onAfterRender

} // class

?>
