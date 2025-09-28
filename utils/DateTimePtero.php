<?php

namespace App\Addons\pterodactylpanelapi\utils;

class DateTimePtero
{
	/**
	 * Format a date string to the Pterodactyl API format.
	 *
	 * @param string $date The date string to format.
	 *
	 * @return string The formatted date string.
	 */
	public static function format(string $date): string
	{
		$dt = new \DateTime($date);
		return $dt->format('Y-m-d\TH:i:sP');
	}
}