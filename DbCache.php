<?php
namespace pdyn\cache;

/**
 * Caching class.
 */
class DbCache implements CacheInterface {
	/** @var \pdyn\database\DbDriverInterface An active database connection. */
	protected $DB;

	/** @var int The length of time a cached object is valid, in seconds. */
	protected $ttl = 7200;

	/** @var \Psr\Log\LoggerInterface A logging object. */
	protected $logger;

	/**
	 * Constructor.
	 *
	 * @param \pdyn\database\DbDriverInterface &$DB An active database connection.
	 * @param \Psr\Log\LoggerInterface $logger (Optional) A logging object to log with.
	 */
	public function __construct(\pdyn\database\DbDriverInterface &$DB, \Psr\Log\LoggerInterface $logger = null) {
		$this->DB =& $DB;
		if (!empty($logger)) {
			$this->logger = $logger;
		}
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
		$dbparams = [];
		$conditions = [];

		// Add key to conditions.
		$conditions['key'] = $key;

		// Add types to conditions.
		if (!empty($types)) {
			$conditions_types = [];
			foreach ($types as $type) {
				$conditions_types[] = $prefix.$type;
			}
			$conditions['type'] = $conditions_types;
		}

		$records = $this->DB->get_records('cache', $conditions);
		$return = [];

		foreach ($records as $record) {
			if ($record['expires'] <= time()) {
				$this->delete($record['type'], $record['key']);
				continue;
			}

			$returntype = (!empty($prefix) && mb_strpos($record['type'], $prefix) === 0)
				? mb_substr($record['type'], mb_strlen($prefix))
				: $record['type'];

			$return[$returntype] = $record;

			try {
				$return[$returntype]['data'] = unserialize($record['data']);
			} catch (\Exception $e) {
				// Data corruption. Delete the cached record.
				if (!empty($this->logger)) {
					$logmsg = 'Error unserializing cache record with type '.$type.' and key '.$key.', error: '.$e->getMessage();
					$this->logger->error($logmsg);
				}
				$this->delete($record['type'], $record['key']);
				continue;
			}

		}

		return $return;
	}

	/**
	 * Get a cache entry for a given key and type.
	 *
	 * @param string $type The cache type.
	 * @param string $key The main key.
	 * @return mixed The stored cache data as an array, or false.
	 */
	public function get($type = null, $key = null) {
		$conditions = [];
		if (!empty($type)) {
			$conditions['type'] = $type;
		}
		if (!empty($key)) {
			$conditions['key'] = $key;
		}
		$record = $this->DB->get_record('cache', $conditions);

		if (empty($record)) {
			return false;
		}
		if ($record['expires'] <= time()) {
			$this->delete($type, $key);
			return false;
		}

		try {
			$record['data'] = unserialize($record['data']);
		} catch (\Exception $e) {
			// Data corruption. Delete the cached record.
			if (!empty($this->logger)) {
				$logmsg = 'Error unserializing cache record with type '.$type.' and key '.$key.', error: '.$e->getMessage();
				$this->logger->error($logmsg);
			}
			$this->delete($type, $key);
			return false;
		}

		return $record;
	}

	/**
	 * Get number of entries for a given type of data.
	 *
	 * @param string $type The cache type to analyze.
	 * @return int The number of entries for that type.
	 */
	public function size($type = null) {
		$sql = 'SELECT count(id) as cachesize FROM {cache} ';
		$conditions = [];

		if (!empty($type)) {
			$conditions['type'] = $type;
		}

		$result = $this->DB->get_record('cache', $conditions, [], 'count(id) as cachesize');
		return (isset($result['cachesize'])) ? (int)$result['cachesize'] : 0;
	}

	/**
	 * Delete a specific cache entry.
	 *
	 * @param string $type The cache type to delete.
	 * @param string $key The cache key to delete.
	 * @return bool Success/Failure
	 */
	public function delete($type = null, $key = null) {
		$conditions = [];
		if (!empty($type)) {
			$conditions['type'] = $type;
		}
		if (!empty($key)) {
			$conditions['key'] = $key;
		}
		$this->DB->delete_records('cache', $conditions);
		return true;
	}

	/**
	 * Delete all expired cache entries, optionally restricted by type.
	 *
	 * @param string $type The cache type to delete.
	 * @return bool Success/Failure.
	 */
	public function gc($type = null) {
		$select = 'expires <= ?';
		$params = [time()];
		if (!empty($type)) {
			$select .= ' AND type = ?';
			$params[] = $type;
		}

		$this->DB->delete_records_select('cache', $select, $params);
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
	public function store($type, $key, $data, $expiry) {
		$this->delete($type, $key);
		$rec = [
			'type' => $type,
			'key' => $key,
			'data' => serialize($data),
			'expires' => $expiry,
		];
		return $this->DB->insert_record('cache', $rec);
	}
}
