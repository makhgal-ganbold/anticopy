<?php

/**
* @package     System - AntiCopy
* @subpackage  Plugin
* @author      Makhgal Ganbold
* @publisher   JExtBOX - BOX of Joomla Extensions (www.jextbox.com)
* @authorUrl   www.galaa.net
* @copyright   Copyright (C) 2011-2025 Makhgal Ganbold
* @license     GNU/GPL v2 or later - http://www.gnu.org/licenses/gpl-2.0.html
* @since       1.0
*/

declare(strict_types=1);

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\CMS\Uri\Uri;

class PlgSystemAntiCopy extends CMSPlugin
{

	private bool $skip = false;

	public function __construct(&$subject, $config)
	{

		parent::__construct($subject, $config);

		$app = Factory::getApplication();

		// Skip backend
		if ($app->isClient('administrator')) {
			$this->skip = true;
			return;
		}

		// Skip AJAX
		if (
			isset($_SERVER['HTTP_X_REQUESTED_WITH'])
			&& strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest'
		) {
			$this->skip = true;
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
			$this->skip = true;
			return;
		}

		/* * * * * * * * * * * * * * * * * * * * *
		 * Exclude specific pages by URL matching
		 * * * * * * * * * * * * * * * * * * * * */

		// Load excluded URL patterns
		$raw_excluded_urls = strtolower($this->params->get('excluded_urls', ''));
		$lines = preg_split('/\r\n|\r|\n/', $raw_excluded_urls);
		$excluded_urls = array_filter(array_map('trim', $lines));

		// Parse current URL
		$uri = Uri::getInstance();
		// Base folder (Joomla site folder)
		$basePath = strtolower(rtrim($uri->base(true), '/'));
		// Normalize basePath: ensure it starts with "/" (but no trailing "/")
		$basePath = $basePath === '' ? '' : '/' . trim($basePath, '/');
		// Full request path
		$fullPath = strtolower($uri->getPath());
		// Remove Joomla's index.php
		$fullPath = preg_replace('#/index\.php(/|$)#', '/', $fullPath);
		// Normalize path
		$fullPath = '/' . trim($fullPath, '/');
		// If fullPath starts with basePath, remove the basePath portion
		if ($basePath !== '' && str_starts_with($fullPath, $basePath)) {
			$pathAfterBase = substr($fullPath, strlen($basePath));
		} else {
			// Either base is empty (site at domain root) or fullPath doesn't contain base (edge-case)
			$pathAfterBase = $fullPath;
		}
		// Normalize resulting path (remove leading/trailing slashes)
		$current_path = trim($pathAfterBase, '/');
		// Treat homepage as single slash "/"
		if ($current_path === '') {
			$current_path = '/';
		}
		// Query string
		$current_query = strtolower($uri->getQuery());

		// Parse current query string into associative array (for flexible matching)
		parse_str($current_query, $current_query_arr);

		// Convert wildcard pattern into valid regex
		$makeRegex = function($pattern) {
			$pattern = trim($pattern);
			$pattern = rtrim($pattern, '/');
			$escaped = preg_quote($pattern, '/');
			$regex = str_replace('\*', '.*', $escaped);
			return '/^' . $regex . '$/i';
		};

		// Match a rule query string against the current query (order-independent)
		$matchQuery = function($rule_query, $current_query_arr, $makeRegex) {
			$pairs = explode('&', $rule_query);
			foreach ($pairs as $pair) {
				if ($pair === '') continue;
				$kv = explode('=', $pair, 2);
				$key = $kv[0];
				if (!array_key_exists($key, $current_query_arr)) {
					return false;
				}
				$currentValue = $current_query_arr[$key];
				$ruleValue = $kv[1] ?? ''; // empty value allowed
				if (!preg_match($makeRegex($ruleValue), $currentValue)) {
					return false;
				}
			}
			return true;
		};

		// Search Engine Friendly URLs
		$config = Factory::getConfig();
		$isSEFEnabled = (bool) $config->get('sef');

		// Check all excluded patterns
		foreach ($excluded_urls as $rule) {
			if ($rule === '') continue;
			$has_query = strpos($rule, '?') !== false;
			if ($has_query) {
				list($rule_path, $rule_query) = explode('?', $rule, 2);
				if ($isSEFEnabled) {
					$rule_path = trim($rule_path);
					$rule_path = trim($rule_path, '/');
					if ($rule_path === '') {
						$rule_path = '/';
					}
					$rule_query = trim($rule_query);
					// Match path
					if ($rule_path !== '/') {
						if ($rule_path !== $current_path && !preg_match($makeRegex($rule_path), $current_path)) {
							continue;
						}
					}
				}
				// Match query (order-insensitive)
				if ($rule_query !== '') {
					if (!$matchQuery($rule_query, $current_query_arr, $makeRegex)) {
						continue;
					}
				}
				// Matched
				$this->skip = true;
				return;
			} else {
				$rule_path = trim($rule);
				$rule_path = trim($rule_path, '/');
				if ($rule_path === '') {
					if (!$isSEFEnabled && $current_query !== '') {
						continue;
					}
					$rule_path = '/';
				}
				if ($rule_path === $current_path || preg_match($makeRegex($rule_path), $current_path)) {
					$this->skip = true;
					return;
				}
			}
		}

	}

	function onBeforeCompileHead(): void
	{

		// Skip if not applicable
		if ($this->skip) {
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
		if ($this->skip) {
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

		$app->setBody($html);

	}

}

?>
