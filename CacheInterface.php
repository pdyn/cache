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

/**
 * An interface defining caching objects.
 */
interface CacheInterface {
	/**
	 * Get multiple cache entries for a single key.
	 *
	 * This is useful if you're storing multiple types of information that all related to a single entity.
	 *
	 * @param string $key The cache key to use.
	 * @param array $types An array of cache types to get.
	 * @param string $prefix (Optional) A prefix to transparently attach to each cache type when searching and remove when
	 *                        returning.
	 * @return array An array of retrieved cache info indexed by cache type. If data for a requested type is not present or
	 *               expired, it will not be included in the return array.
	 */
	public function get_all($key, array $types = array(), $prefix = '');

	/**
	 * Get a cache entry for a given key and type.
	 *
	 * @param string $type The cache type.
	 * @param string $key The main key.
	 * @return mixed The stored cache data as an array, or false.
	 */
	public function get($type = null, $key = null);

	/**
	 * Get number of entries for a given type of data.
	 *
	 * @param string $type The cache type to analyze.
	 * @return int The number of entries for that type.
	 */
	public function size($type = null);

	/**
	 * Delete a specific cache entry.
	 *
	 * @param string $type The cache type to delete.
	 * @param string $key The cache key to delete.
	 * @return bool Success/Failure
	 */
	public function delete($type = null, $key = null);

	/**
	 * Delete all expired cache entries, optionally restricted by type.
	 *
	 * @param string $type The cache type to delete.
	 * @return bool Success/Failure.
	 */
	public function gc($type = null);

	/**
	 * Store a cache entry.
	 *
	 * @param string $type The cache type - use this like a category for the data.
	 * @param string $key The cache key - a key to identify this information within the category $type.
	 * @param mixed $data The data to store. array, bool, int, and string types currently supported.
	 * @param int $expiry The timestamp this entry expires.
	 * @return int An ID that refers to the new data.
	 */
	public function store($type, $key, $data, $expiry);
}
