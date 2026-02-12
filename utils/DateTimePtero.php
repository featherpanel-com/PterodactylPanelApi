<?php

/*
 * This file is part of FeatherPanel.
 *
 * Copyright (C) 2025 MythicalSystems Studios
 * Copyright (C) 2025 FeatherPanel Contributors
 * Copyright (C) 2025 Cassian Gherman (aka NaysKutzu)
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published
 * by the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * See the LICENSE file or <https://www.gnu.org/licenses/>.
 */

namespace App\Addons\pterodactylpanelapi\utils;

class DateTimePtero
{
    /**
     * Format a date string to the Pterodactyl API format.
     *
     * @param string $date the date string to format
     *
     * @return string the formatted date string
     */
    public static function format(string $date): string
    {
        $dt = new \DateTime($date);

        return $dt->format('Y-m-d\TH:i:sP');
    }
}
