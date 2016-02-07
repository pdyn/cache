<?php
/*
This program is free software: you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation, either version 3 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program.  If not, see <http://www.gnu.org/licenses/>.
*/

/**
 * @copyright 2010 onwards James McQuillan (http://pdyn.net)
 * @author James McQuillan <james@pdyn.net>
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace pdyn\cache;

use \pdyn\base\Exception;

/**
 * A test database schema.
 */
class DbCacheDbSchema extends \pdyn\database\DbSchema {
	public static function cache() {
		return [
			'columns' => [
				'id' => 'id',
				'type' => 'smallstr',
				'key' => 'str',
				'data' => 'mediumblob',
				'expires' => 'int',
			],
			'keys' => [
				'PRIMARY' => ['id', true],
				'typekeyuniq' => ['type,key', true],
			],
		];
	}
}
