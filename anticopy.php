<?php

/**
* @package       System - AntiCopy
* @author        Galaa
* @publisher     JExtBOX - BOX of Joomla Extensions (www.jextbox.com)
* @authorUrl     www.galaa.mn
* @authorEmail   contact@galaa.mn
* @copyright     Copyright (C) 2011-2013 Galaa
* @license       This extension in released under the GNU/GPL License - http://www.gnu.org/copyleft/gpl.html
*/

// no direct access
defined( '_JEXEC' ) or die( 'Restricted access' );

// Import library dependencies
jimport( 'joomla.event.plugin' );

class plgSystemAntiCopy extends JPlugin {

	function plgSystemAntiCopy( &$subject, $config ) {
		parent::__construct( $subject, $config );
	}

	function onAfterRender() {

		// Backend
		$app = JFactory::getApplication();
		if($app->isAdmin()) return;

		// Prepare Script
		$html = JResponse::getBody();

		// Parameter
		$plugin =& JPluginHelper::getPlugin( 'system', 'anticopy' );
 		$params = new JParameter( $plugin->params );

		// Check User
		$utypes_opt = array($params->get( 'utype1' ), $params->get( 'utype2' ), $params->get( 'utype3' ), $params->get( 'utype4' ), $params->get( 'utype5' ), $params->get( 'utype6' ), $params->get( 'utype7' ));
		$utypes = array('Registered', 'Author', 'Editor', 'Publisher', 'Manager', 'Administrator', 'Super Administrator');
		foreach($utypes_opt as $k => $v)
			if(!$v)
				unset($utypes[$k]);
		$user =& JFactory::getUser();

		// Set Permission
		if(in_array($user->usertype, $utypes) || ( ($params->get( 'utype0' ) == 0) && $user->guest )) {

			// Prevent page from being printed
			if($this->params->get('disallow_print')) {
				$html = preg_replace("/<\/head>/", '<style type="text/css"> @media print { body { display:none } } </style>' . "\n</head>", $html);
			} // Prevent page from being printed

			// Try to prevent print screen
			if($this->params->get('disallow_printscreen')) {
				$html = preg_replace("/<body/", '<body onload="setInterval(\'window.clipboardData.clearData()\',20)"', $html);
			} // Try to prevent print screen

			// Show Popup Message
			$show_message = $this->params->get('show_message');
			$message = trim($this->params->get('message'));
			if($show_message) {
				$comment = "";
			}
			else {
				$comment = "//";
			}

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

			// Disable Select and Copy
			$disallow_select_and_copy = $this->params->get('disallow_copy');
			if($disallow_select_and_copy != '0') {
				// Disable text selection
				if($disallow_select_and_copy == '2'){
					$disallow_select_and_copy = 'true';
				}else{
					$disallow_select_and_copy = 'false';
				}
				$select = "
<script type=\"text/javascript\">
	function disableSelection(target){
	if (typeof target.onselectstart!=\"undefined\") //IE
		target.onselectstart=function(){return false}
	else if (typeof target.style.MozUserSelect!=\"undefined\") //Firefox
		target.style.MozUserSelect=\"none\"
	else //Other (Opera etc)
		target.onmousedown=function(){return ".$disallow_select_and_copy."}
	target.style.cursor = \"default\"
	}
</script>";
				$html = preg_replace("/<\/head>/", $select . "\n</head>", $html);
				$select = "
<script type=\"text/javascript\">
	disableSelection(document.body)
</script>";
				$html = preg_replace("/<\/body>/", $select . "\n</body>", $html);

				$html = preg_replace('/<img /', '<img ondragstart="return false;" ', $html);
				$html = preg_replace('/<a /', '<a ondragstart="return false;" ', $html);

				$copy = "
<script type=\"text/javascript\">
	/* <![CDATA[ */
		window.addEvent('domready', function() {
			document.body.oncopy = function() {
				${comment}alert('".$message."');
				return false;
			}
		});
	/* ]]> */
</script>";
				$html = preg_replace("/<\/head>/", $copy . "\n</head>", $html);

				$html = preg_replace('/<\/head>/', '<meta http-equiv="imagetoolbar" content="no">'."\n</head>", $html);

			} // Disable Select and Copy

		} // Set Permission

		// Restrict the framing
		$disallow_framing = $this->params->get('disallow_framing');
		if($disallow_framing) {
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
