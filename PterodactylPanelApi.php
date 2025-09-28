<?php

namespace App\Addons\pterodactylpanelapi;

use App\App;
use App\Cli\App as AppCli;
use App\Chat\Database;
use App\Plugins\Events\Events\AppEvent;
use App\Plugins\AppPlugin;
use App\Addons\pterodactylpanelapi\Events\App\AppReadyEvent;

class PterodactylPanelApi implements AppPlugin
{
	/**
	 * @inheritDoc
	 */
	public static function processEvents(\App\Plugins\PluginEvents $event): void
	{
		$event->on(AppEvent::onRouterReady(), function ($eventInstance) {
			new AppReadyEvent($eventInstance);
		});
	}

	/**
	 * @inheritDoc
	 */
	public static function pluginInstall(): void
	{
		$appCli = AppCli::getInstance();
		$app = App::getInstance(true, true);

		if (!file_exists(__DIR__ . '/../../../storage/.env')) {
			$app->getLogger()->warning('Executed a command without a .env file');
			$appCli->send('The .env file does not exist. Please create one before running this command');
			exit;
		}

		try {
			$app->loadEnv();
			$db = new Database($_ENV['DATABASE_HOST'], $_ENV['DATABASE_DATABASE'], $_ENV['DATABASE_USER'], $_ENV['DATABASE_PASSWORD'], $_ENV['DATABASE_PORT']);
			$db = $db->getMysqli();
		} catch (\Exception $e) {
			$appCli->send('&cFailed to connect to the database: &r' . $e->getMessage());
			exit;
		}

		$logger = $app->getLogger();
	}

	/**
	 * @inheritDoc
	 */
	public static function pluginUninstall(): void
	{
		$appCli = AppCli::getInstance();
		$app = App::getInstance(true, true);

		if (!file_exists(__DIR__ . '/../../../storage/.env')) {
			$app->getLogger()->warning('Executed a command without a .env file');
			$appCli->send('The .env file does not exist. Please create one before running this command');
			exit;
		}

		try {
			$app->loadEnv();
			$db = new Database($_ENV['DATABASE_HOST'], $_ENV['DATABASE_DATABASE'], $_ENV['DATABASE_USER'], $_ENV['DATABASE_PASSWORD'], $_ENV['DATABASE_PORT']);
			$db = $db->getMysqli();
		} catch (\Exception $e) {
			$appCli->send('&cFailed to connect to the database: &r' . $e->getMessage());
			exit;
		}

		$logger = $app->getLogger();

		$appCli->send("&aUninstalling Pterodactyl Panel API...");
		$appCli->send("&aDropping Pterodactyl Panel API table...");

		$db->query("DROP TABLE IF EXISTS `featherpanel_pterodactylpanelapi_pterodactyl_api_key`");

		$logger->info("Pterodactyl Panel API table dropped");
		$appCli->send("&aPterodactyl Panel API table dropped");
	}
}