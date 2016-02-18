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

namespace pdyn\cache\tests;

/**
 * Test DbCache.
 *
 * @group pdyn
 * @group pdyn_cache
 * @codeCoverageIgnore
 */
class DbCacheTest extends \PHPUnit_Framework_TestCase {
	/**
	 * Construct and return a DbCache object.
	 *
	 * @return \pdyn\cache\DbCache A constructed DbCache object.
	 */
	protected function get_cache_object() {
		if (!class_exists('\pdyn\database\pdo\sqlite\DbDriver')) {
			$this->markTestSkipped('No database class available.');
			return false;
		}
		$DB = new \pdyn\database\pdo\sqlite\DbDriver(['\pdyn\cache\DbCacheDbSchema']);
		$DB->connect('sqlite::memory:');
		$DB->set_prefix('pdyncachetest_');
		$tables = $DB->get_schema();
		foreach ($tables as $tablename => $tableschema) {
			$DB->structure()->create_table($tablename);
		}
		return new \pdyn\cache\DbCache($DB);
	}

	/**
	 * Dataprovider for test_store_retrieve.
	 *
	 * @return array Array of test parameters.
	 */
	public function dataprovider_test_store_retrieve() {
		return [
			[['testone' => 'testtwo']],
			[true],
			[false],
			[1],
			[10],
			[0],
			['test'],
			[''],
		];
	}

	/**
	 * Test storing and retrieving information from the cache.
	 *
	 * @dataProvider dataprovider_test_store_retrieve
	 */
	public function test_store_retrieve($testdata) {
		$testtype = 'testtype';
		$testkey = 'testkey';
		$expiry = time() + 10;

		$cache = $this->get_cache_object();
		$cache->store($testtype, $testkey, $testdata, $expiry);

		$retrieved = $cache->get($testtype, $testkey);

		// Assert return structure
		$this->assertNotEmpty($retrieved);
		$this->assertInternalType('array', $retrieved);
		$this->assertArrayHasKey('type', $retrieved);
		$this->assertArrayHasKey('key', $retrieved);
		$this->assertArrayHasKey('data', $retrieved);
		$this->assertArrayHasKey('expires', $retrieved);

		// Assert cache IDs - type, key
		$this->assertEquals($testtype, $retrieved['type']);
		$this->assertEquals($testkey, $retrieved['key']);

		// Assert Data
		if (is_array($testdata)) {
			$this->assertTrue(is_array($retrieved['data']));
		} elseif (is_bool($testdata)) {
			$this->assertTrue(is_bool($retrieved['data']));
		} elseif (is_int($testdata)) {
			$this->assertTrue(is_int($retrieved['data']));
		} else {
			$this->assertTrue(is_string($retrieved['data']));
		}
		$this->assertEquals($testdata, $retrieved['data']);

		// Assert Expiry
		$this->assertEquals($expiry, $retrieved['expires']);
	}

	/**
	 * Test the cache respects the expiry date, and doesn't return expired data.
	 */
	public function test_get_respects_expiry() {
		$cache = $this->get_cache_object();
		$cache->store('testtype', 'testkey', 'testdata', time() - 100);

		$record = $cache->get('testtype', 'testkey');
		$this->assertEmpty($record);
	}

	/**
	 * Dataprovider for the test_get_all method.
	 *
	 * @return array Array of test parameters.
	 */
	public function dataprovider_test_get_all() {
		return [
			[
				['test_array', 'test_bool', 'test_int', 'test_str'],
				'testkey',
				''
			],
			[
				['array', 'bool', 'int', 'str'],
				'testkey',
				'test_'
			]
		];
	}

	/**
	 * Test get_all method.
	 *
	 * @dataProvider dataprovider_test_get_all
	 */
	public function test_get_all($get_types, $get_key, $get_prefix) {
		$cache = $this->get_cache_object();
		$expiry = time() + 100;
		$testdata = [
			'array' => ['one' => 'two'],
			'bool' => true,
			'int' => 33,
			'str' => 'teststring!',
		];
		foreach ($testdata as $type => $val) {
			$cache->store('test_'.$type, 'testkey', $val, $expiry);
		}

		// Test getting all records, with specific types.
		$cacherecs = $cache->get_all($get_key, $get_types, $get_prefix);

		// Test structure
		$this->assertNotEmpty($cacherecs);
		$this->assertInternalType('array', $cacherecs);
		$this->assertEquals(count($get_types), count($cacherecs));

		// Test data
		foreach ($get_types as $type) {
			$this->assertArrayHasKey($type, $cacherecs);
			$datatype = (mb_strpos($type, 'test_') === 0) ? mb_substr($type, 5) : $type;
			switch($datatype) {
				case 'array':
					$this->assertTrue(is_array($cacherecs[$type]['data']));
					break;
				case 'bool':
					$this->assertTrue(is_bool($cacherecs[$type]['data']));
					break;
				case 'int':
					$this->assertTrue(is_int($cacherecs[$type]['data']));
					break;
				case 'str':
					$this->assertTrue(is_string($cacherecs[$type]['data']));
					break;
			}
			$this->assertEquals($testdata[$datatype], $cacherecs[$type]['data']);
		}
	}

	/**
	 * Test that the get_all method respects the data's expiry date.
	 */
	public function test_get_all_respects_expiry() {
		$cache = $this->get_cache_object();
		$cache->store('testtype1', 'testkey', 'testdata1', time() + 100);
		$cache->store('testtype2', 'testkey', 'testdata1', time() - 100);

		$records = $cache->get_all('testkey');
		$this->assertEquals(1, count($records));
		$this->assertArrayHasKey('testtype1', $records);
		$this->assertArrayNotHasKey('testtype2', $records);
	}

	/**
	 * Test size method.
	 */
	public function test_size() {
		$cache = $this->get_cache_object();
		$expiry = time() + 100;
		$cache->store('testtype1', 'testkey', 'testdata', $expiry);
		$cache->store('testtype1', 'testkey2', 'testdata', $expiry);
		$cache->store('testtype2', 'testkey1', 'testdata', $expiry);
		$cache->store('testtype2', 'testkey2', 'testdata', $expiry);
		$cache->store('testtype2', 'testkey3', 'testdata', $expiry);

		$size = $cache->size();
		$this->assertEquals(5, $size);

		$size = $cache->size('testtype1');
		$this->assertEquals(2, $size);

		$size = $cache->size('testtype2');
		$this->assertEquals(3, $size);

		$size = $cache->size('testtype3');
		$this->assertEquals(0, $size);
	}

	/**
	 * Dataprovider for the test_delete method.
	 *
	 * @return array Array of test parameters.
	 */
	public function dataprovider_test_delete() {
		return [
			['testtype1', null],
			[null, 'testkey'],
			[null, 'testkey2'],
			['testtype1', 'testkey'],
		];
	}

	/**
	 * Test the delete method.
	 *
	 * @dataProvider dataprovider_test_delete
	 */
	public function test_delete($type, $key) {
		$cache = $this->get_cache_object();
		$expiry = time() + 100;
		$cache->store('testtype1', 'testkey', 'testdata', $expiry);
		$cache->store('testtype1', 'testkey2', 'testdata', $expiry);
		$cache->store('testtype2', 'testkey1', 'testdata', $expiry);
		$cache->store('testtype2', 'testkey2', 'testdata', $expiry);
		$cache->store('testtype2', 'testkey3', 'testdata', $expiry);

		// Verify item exists.
		$item = $cache->get($type, $key);
		$this->assertNotEmpty($item);

		// Delete item.
		$cache->delete($type, $key);

		// Verify item doesn't exist.
		$item = $cache->get($type, $key);
		$this->assertEmpty($item);
	}

	/**
	 * Test the gc method.
	 */
	public function test_gc() {
		$cache = $this->get_cache_object();
		$cache->store('testtype1', 'testkey', 'testdata', time() + 100);
		$cache->store('testtype2', 'testkey', 'testdata', time() - 100);
		$cache->store('testtype3', 'testkey', 'testdata', time() + 100);

		$cache->gc();
		$records = $cache->get_all('testkey');
		$this->assertEquals(2, count($records));

		$cache->store('testtype1', 'testkey1', 'testdata', time() + 100);
		$cache->store('testtype2', 'testkey1', 'testdata', time() - 100);
		$cache->store('testtype3', 'testkey1', 'testdata', time() + 100);
		$cache->store('testtype1', 'testkey2', 'testdata', time() - 100);
		$cache->store('testtype2', 'testkey2', 'testdata', time() + 100);
		$cache->store('testtype3', 'testkey2', 'testdata', time() + 100);

		$cache->gc('testtype1');
		$cache->gc('testtype2');

		$records1 = $cache->get_all('testkey1');
		$this->assertInternalType('array', $records1);
		$this->assertEquals(2, count($records1));
		$this->assertArrayHasKey('testtype1', $records1);
		$this->assertArrayNotHasKey('testtype2', $records1);
		$this->assertArrayHasKey('testtype3', $records1);

		$records2 = $cache->get_all('testkey2');
		$this->assertInternalType('array', $records2);
		$this->assertEquals(2, count($records2));
		$this->assertArrayNotHasKey('testtype1', $records2);
		$this->assertArrayHasKey('testtype2', $records2);
		$this->assertArrayHasKey('testtype3', $records2);
	}
}
