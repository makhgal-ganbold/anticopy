<?php

/**
* @package     System - AntiCopy
* @subpackage  Plugin
* @author      Makhgal Ganbold
* @publisher   JExtBOX - BOX of Joomla Extensions (www.jextbox.com)
* @authorUrl   www.galaa.net
* @copyright   Copyright (C) 2026 Makhgal Ganbold
* @license     GNU/GPL v2 or later - http://www.gnu.org/licenses/gpl-2.0.html
* @since       5.1.0
*/

defined('_JEXEC') or die;

use Joomla\CMS\Extension\PluginInterface;
use Joomla\CMS\Factory;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\DI\Container;
use Joomla\DI\ServiceProviderInterface;
use Joomla\Event\DispatcherInterface;
use JExtBOX\Plugin\System\AntiCopy\Extension\AntiCopy;

return new class () implements ServiceProviderInterface {

	public function register(Container $container): void
	{

		$container->set(
			PluginInterface::class,
			function (Container $container) {
				$dispatcher = $container->get(DispatcherInterface::class);
				$pluginData = (array) PluginHelper::getPlugin('system', 'anticopy');
				$plugin = new AntiCopy(
					$dispatcher, 
					$pluginData
				);
				$plugin->setApplication(Factory::getApplication());
				return $plugin;
			}
		);

	}

};

?>
