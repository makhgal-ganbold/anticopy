<?php

/**
* @package     System - AntiCopy
* @subpackage  Plugin
* @author      Makhgal Ganbold
* @publisher   JExtBOX - BOX of Joomla Extensions (www.jextbox.com)
* @authorUrl   www.galaa.net
* @copyright   Copyright (C) 2011-2026 Makhgal Ganbold
* @license     GNU/GPL v2 or later - http://www.gnu.org/licenses/gpl-2.0.html
* @since       1.0
*/

namespace JExtBOX\Plugin\System\AntiCopy\Extension;

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\CMS\Uri\Uri;
use Joomla\Event\Event;
use Joomla\Event\SubscriberInterface;

final class AntiCopy extends CMSPlugin implements SubscriberInterface
{

	private static bool $skip = false;

	public static function getSubscribedEvents(): array
	{

		return [
			'onBeforeCompileHead' => 'onBeforeCompileHead',
			'onAfterRoute' => 'onAfterRoute',
			'onAfterRender' => 'onAfterRender',
		];

	}

	public function __construct(&$subject, $config)
	{

		parent::__construct($subject, $config);

		$app = Factory::getApplication();

		// Skip backend and API calls
		if ($app->isClient('administrator') || $app->isClient('api')) {
			self::$skip = true;
			return;
		}

		// Skip AJAX
		if ($this->isAjaxRequest()) {
			self::$skip = true;
			return;
		}

		// Define a current user's groups
		$user_groups = $app->getIdentity()->getAuthorisedGroups();

		// Restricted groups
		$restricted_groups = (array) $this->params->get('restrict_groups', []);

		// Add Public (1) and Guest (9) groups to the list of restricted groups
		foreach ([1, 9] as $gid) {
			if (!in_array($gid, $restricted_groups, true)) {
				$restricted_groups[] = $gid;
			}
		}

		// Check if user is restricted
		$restricted = count(array_diff($user_groups, $restricted_groups)) === 0;
		if (!$restricted) {
			self::$skip = true;
			return;
		}

	}

	function onAfterRoute()
	{

		// Skip if not applicable
		if (self::$skip) {
			return;
		}

	}

	function onBeforeCompileHead (): void
	{

		// Skip if not applicable
		if (self::$skip) {
			return;
		}

		$doc = Factory::getDocument();

		// Prevent framing (clickjacking protection)
		if ((int) $this->params->get('disallow_framing', 1) === 1) {
			$base = Uri::base();
			// Add JavaScript fallback
			$doc->addScriptDeclaration("
				if (window.top.location.href !== window.self.location.href && !window.top.location.href.startsWith('$base')) {
					window.top.location.href = window.self.location.href;
				}
			");
			// Add CSP header in site application
			$app = Factory::getApplication();
			if ($app->isClient('site')) {
				$app->getDocument()->setMetaData('Content-Security-Policy', "frame-ancestors 'self';");
			}
		}

		// (PAID) Detect if DevTools is open

		// (PAID) Restrict keyboard shortcuts

		// (PAID) Don't display body, if JS is disabled

		// Disable right click
		switch ((int) $this->params->get('disallow_r_click', 1)) {
			case 1: // whole page
				$doc->addScriptDeclaration('
					document.addEventListener("contextmenu", event => {
						event.preventDefault();
						return false;
					});
				');
				break;
			case 2: // only img tags
				$doc->addScriptDeclaration('
					document.addEventListener("contextmenu", event => {
						const el = event.target;
						const has_img = el.tagName === "IMG" || Array.from(el.children).some(child => child.tagName === "IMG");
						if (has_img) {
							event.preventDefault();
							return false;
						}
					});
				');
				break;
		}

		// Prevent page being printed
		if ((int) $this->params->get('disallow_print', 1) === 1) {
			$doc->addStyleDeclaration('@media print{body{display:none !important;}}');
		}

		// Disallow dragging
		if ((int) $this->params->get('disallow_drag', 1) === 1) {
			$doc->addScriptDeclaration("
				document.addEventListener('dragstart', event => {
					event.preventDefault();
					return false;
				});
			");
		}

		// Restrict copying
		if ((int) $this->params->get('disallow_copy', 1) === 1) {
			$notification = $this->params->get('show_message', 0)
				? trim($this->params->get('message', "You don't have permission to copy the content."))
				: '';

			$js = "
				function JExtBOXAntiCopyShowMSG() {
					const modal = document.getElementById('JExtBOXAntiCopyModal');
					if (modal && " . (!empty($notification) ? 'true' : 'false') . ") {
						modal.style.display = 'block';
					}
				}

				document.addEventListener('copy', e => {
					e.preventDefault();
					JExtBOXAntiCopyShowMSG();
					return false;
				});

				document.addEventListener('cut', e => {
					e.preventDefault();
					JExtBOXAntiCopyShowMSG();
					return false;
				});

				document.addEventListener('click', e => {
					const modal = document.getElementById('JExtBOXAntiCopyModal');
					if (modal && e.target === modal) {
						modal.style.display = 'none';
					}
				});
			";

			$doc->addScriptDeclaration($js);
		}

		// Add modal if message enabled
    if (!empty($notification)) {
			$modal = '
				<div id="JExtBOXAntiCopyModal" style="
					display:none;
					position:fixed;
					z-index:9999;
					left:0;top:0;width:100%;height:100%;
					background-color:rgba(0,0,0,0.4);
				">
					<div style="
						background:#fefefe;
						margin:10% auto;
						padding:2em;
						border:none;width:75%;
						text-align:center;
						border-radius:8px;
						box-shadow:0 2px 8px rgba(0,0,0,0.3);
					">
						' . htmlspecialchars($notification, ENT_QUOTES, 'UTF-8') . '
						<br><br>
						<button class="btn btn-primary" onclick="this.parentElement.parentElement.style.display=\'none\'">OK</button>
					</div>
				</div>
			';
			$doc->addCustomTag($modal);
		}

	}

	function onAfterRender()
	{

		// Skip if not applicable
		if (self::$skip) {
			return;
		}

		$app = Factory::getApplication();

		// Skip backend or non-HTML documents
		if ($app->isClient('administrator') || $app->getDocument()->getType() !== 'html') {
			return;
		}

		$html = $app->getBody();
		if (empty($html)) {
			return;
		}

		// Make HTML hard to read
		if ($this->params->get('sanitize_html', 1)) {
			// remove new lines and extra spaces between tags
			$html = preg_replace('/>\s+?</s', '><', $html);
		}

		// (PAID) Protect HTML body source code

		$app->setBody($html);

	}

	private function isAjaxRequest(): bool
	{

		$input = Factory::getApplication()->input;

		// 1: Legacy XHR (jQuery or legacy JS)
		if ($input->server->get('HTTP_X_REQUESTED_WITH') === 'XMLHttpRequest') {
			return true;
		}

		// 2: Content-Type (JSON)
		$contentType = $input->server->get('CONTENT_TYPE', '');
		if (stripos($contentType, 'application/json') !== false) {
			return true;
		}

		// 3: Joomla standart format (URL &format=json)
		if ($input->get('format') === 'json' || $input->get('task') === 'ajax') {
			return true;
		}

		// 4: HTTP Accept Header (Fetch API)
		$accept = $input->server->get('HTTP_ACCEPT', '');
		if (stripos($accept, 'application/json') !== false && stripos($accept, 'text/html') === false) {
			return true;
		}

		return false;

	}

}

?>
