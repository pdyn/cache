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
 * File caching.
 */
class FileCache implements CacheInterface {
	/** @var string The directory to store the cached files */
	protected $cachedir = '';

	/** @var array A cache of hashed keys */
	protected $keycache = [];

	/**
	 * Constructor.
	 *
	 * @param string $cachedir The base cache directory.
	 */
	public function __construct($cachedir) {
		if (!file_exists($cachedir)) {
			mkdir($cachedir);
		}
		$this->cachedir = $cachedir;
	}

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
	public function get_all($key, array $types = array(), $prefix = '') {
		if (empty($key)) {
			throw new Exception('No key received by filecache', Exception::ERR_BAD_REQUEST);
		}
		$return = [];
		foreach ($types as $type) {
			$type = \pdyn\datatype\Sanitizer::alphanum($type);
			$filename = $this->get_filename($type, $key);
			if (file_exists($filename)) {
				$return[$type] = file_get_contents($filename);
			}
		}
		return $return;
	}

	/**
	 * Get a cache entry for a given key and type.
	 *
	 * @param string $type The cache type.
	 * @param string $key The main key.
	 * @return string|bool The stored cache data, or false if not present.
	 */
	public function get($type = null, $key = null) {
		if (empty($type) || empty($key)) {
			throw new Exception('Empty type or key received by filecache', Exception::ERR_BAD_REQUEST);
		}
		$filename = $this->get_filename($type, $key);
		return (file_exists($filename)) ? file_get_contents($filename) : false;
	}

	/**
	 * Get number of entries for a given type of data.
	 *
	 * @param string $type The cache type to analyze.
	 * @return int The number of entries for that type.
	 */
	public function size($type = null) {
		if (empty($type)) {
			throw new Exception('Empty type or key received by filecache', Exception::ERR_BAD_REQUEST);
		}
		$type = \pdyn\datatype\Sanitizer::alphanum($type);
		$files = glob($this->cachedir.'/'.$type.'/*');
		return count($files);
	}

	/**
	 * Delete a specific cache entry.
	 *
	 * @param string $type The cache type to delete.
	 * @param string $key The cache key to delete.
	 * @return bool Success/Failure
	 */
	public function delete($type = null, $key = null) {
		if (empty($type) || empty($key)) {
			throw new Exception('Empty type or key received by filecache', Exception::ERR_BAD_REQUEST);
		}
		$filename = $this->get_filename($type, $key);
		if (file_exists($filename)) {
			unlink($filename);
			return (!file_exists($filename)) ? true : false;
		} else {
			return true;
		}
	}

	/**
	 * Delete all expired cache entries, optionally restricted by type.
	 *
	 * @param string $type The cache type to delete.
	 * @return bool Success/Failure.
	 */
	public function gc($type = null) {
		// Does not currently support expiry.
		return true;
	}

	/**
	 * Store a cache entry.
	 *
	 * @param string $type The cache type - use this like a category for the data.
	 * @param string $key The cache key - a key to identify this information within the category $type.
	 * @param mixed $data The data to store. array, bool, int, and string types currently supported.
	 * @param int $expiry The timestamp this entry expires.
	 * @return int The database ID of the new entry.
	 */
	public function store($type, $key, $data, $expiry = null) {
		if (empty($type) || empty($key)) {
			throw new Exception('Empty type or key received by filecache', Exception::ERR_BAD_REQUEST);
		}
		if (!is_scalar($data)) {
			throw new Exception('You can only write scalar data to files.', Exception::ERR_BAD_REQUEST);
		}
		$filename = $this->get_filename($type, $key);
		$cachedir = dirname($filename);
		if (!file_exists($cachedir)) {
			mkdir($cachedir);
		}

		file_put_contents($filename, (string)$data);
		return basename($filename);
	}

	/**
	 * Get the filename for the cache for a given type and key.
	 *
	 * @param string $type The cache type - use this like a category for the data.
	 * @param string $key The cache key - a key to identify this information within the category $type.
	 * @return string The full filename.
	 */
	public function get_filename($type, $key) {
		if (!isset($this->keycache[$key])) {
			$this->keycache[$key] = md5($key);
		}

		$type = \pdyn\datatype\Sanitizer::alphanum($type);
		return $this->cachedir.'/'.$type.'/'.$this->keycache[$key];
	}
}
