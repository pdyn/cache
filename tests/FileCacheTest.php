<?php
namespace pdyn\cache;

/**
 * Test FileCache
 * @group pdyn
 * @group pdyn_cache
 */
class FileCacheTest extends \PHPUnit_Framework_TestCase {
	/** @var string Directory to store cache files. */
	protected $cachedir = '';

	/**
	 * PHPUnit setup - create temp cache dir.
	 */
	protected function setUp() {
		$this->cachedir = sys_get_temp_dir().'/pdyn_cache_'.uniqid();
		mkdir($this->cachedir);
	}

	/**
	 * Delete a folder and all subfolders.
	 *
	 * @param string $path The absolute path to the folder to delete.
	 * @return bool Success/Failure.
	 */
	public static function rrmdir($path) {
		if (is_file($path)) {
			return unlink($path);
		} elseif (is_dir($path)) {
			$dir_members = scandir($path);
			foreach ($dir_members as $member) {
				if ($member !== '.' && $member !== '..') {
					static::rrmdir($path.'/'.$member);
				}
			}
			return @rmdir($path);
		}
	}

	/**
	 * PHPUnit teardown - delete temp cache dir.
	 */
	protected function tearDown() {
		static::rrmdir($this->cachedir);
    }

	/**
	 * Test store method.
	 */
	public function test_store() {
		$cache = new \pdyn\cache\FileCache($this->cachedir);
		$key = $cache->store('testtype', 'testkey', 'testdata');
		$this->assertNotEmpty($key);
		$this->assertTrue(file_exists($this->cachedir));
		$this->assertTrue(file_exists($this->cachedir.'/testtype/'));
		$this->assertTrue(file_exists($this->cachedir.'/testtype/'.$key));
		$this->assertEquals('testdata', file_get_contents($this->cachedir.'/testtype/'.$key));
	}

	/**
	 * Test get method.
	 */
	public function test_get() {
		$cache = new \pdyn\cache\FileCache($this->cachedir);
		$key = $cache->store('testtype', 'testkey', 'testdata');
		$data = $cache->get('testtype', 'testkey');
		$this->assertEquals('testdata', $data);
	}

	/**
	 * Test get_all method.
	 */
	public function test_get_all() {
		$cache = new \pdyn\cache\FileCache($this->cachedir);
		$expected = [
			'testtype1' => 'testdata1',
			'testtype2' => 'testdata2'
		];
		foreach ($expected as $type => $data) {
			$cache->store($type, 'testkey', $data);
		}
		$data = $cache->get_all('testkey', ['testtype1', 'testtype2']);
		$this->assertEquals($expected, $data);
	}

	/**
	 * Test size method.
	 */
	public function test_size() {
		$cache = new \pdyn\cache\FileCache($this->cachedir);
		$tostore = [
			'testkey1' => 'testdata1',
			'testkey2' => 'testdata2',
			'testkey3' => 'testdata3',
			'testkey4' => 'testdata4',
		];
		foreach ($tostore as $key => $data) {
			$cache->store('testtype', $key, $data);
		}
		$size = $cache->size('testtype');
		$this->assertEquals(count($tostore), $size);
	}

	/**
	 * Test delete method.
	 */
	public function test_delete() {
		$cache = new \pdyn\cache\FileCache($this->cachedir);

		// Store data.
		$deletetarget_key = $cache->store('testtype1', 'testkey1', 'testdata1');
		$sametype_key = $cache->store('testtype1', 'testkey2', 'testdata2');
		$samekey_key = $cache->store('testtype2', 'testkey1', 'testdata3');
		$diffall_key = $cache->store('testtype2', 'testkey2', 'testdata3');

		// Ensure the data was cached.
		$this->assertTrue(file_exists($this->cachedir.'/testtype1/'.$deletetarget_key));
		$this->assertTrue(file_exists($this->cachedir.'/testtype1/'.$sametype_key));
		$this->assertTrue(file_exists($this->cachedir.'/testtype2/'.$samekey_key));
		$this->assertTrue(file_exists($this->cachedir.'/testtype2/'.$diffall_key));

		// Delete.
		$result = $cache->delete('testtype1', 'testkey1');
		$this->assertTrue($result);

		// Ensure only the specified data was deleted.
		$this->assertTrue(!file_exists($this->cachedir.'/testtype1/'.$deletetarget_key));
		$this->assertTrue(file_exists($this->cachedir.'/testtype1/'.$sametype_key));
		$this->assertTrue(file_exists($this->cachedir.'/testtype2/'.$samekey_key));
		$this->assertTrue(file_exists($this->cachedir.'/testtype2/'.$diffall_key));
	}
}
