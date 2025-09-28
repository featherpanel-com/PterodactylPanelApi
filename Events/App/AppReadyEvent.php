<?php

namespace App\Addons\pterodactylpanelapi\Events\App;

use Symfony\Component\Routing\RouteCollection;

class AppReadyEvent
{
	public const ROUTES_DIR = __DIR__ . '/../../routes';
	public function __construct($router)
	{
		self::registerApiRoutes($router);
	}

	/**
	 * Register all api endpoints using Symfony Routing.
	 *
	 * @param RouteCollection $routes The Symfony RouteCollection instance
	 */
	private function registerApiRoutes(RouteCollection $routes): void
	{
		$iterator = new \RecursiveIteratorIterator(
			new \RecursiveDirectoryIterator(self::ROUTES_DIR, \FilesystemIterator::SKIP_DOTS),
			\RecursiveIteratorIterator::SELF_FIRST
		);

		foreach ($iterator as $file) {
			if ($file->isFile() && $file->getExtension() === 'php') {
				$register = require $file->getPathname();
				if (is_callable($register)) {
					$register($routes);
				}
			}
		}
	}

}