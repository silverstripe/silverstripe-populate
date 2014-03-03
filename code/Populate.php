<?php

/**
 * @package populate
 */
class Populate extends Object {
		
	/**
	 * @config
	 *
	 * @var array
	 */
	private static $include_yaml_fixtures = array();

	/**
	 * @config
	 *
	 * An array of classes to clear from the database before importing. While
	 * populating sitetree it may be worth clearing the 'SiteTree' table.
	 *
	 * @var array
	 */
	private static $truncate_objects = array();

	/**
	 * Flag to determine if we're already run for this session (i.e to prevent
	 * parent calls invoking {@link requireRecords} twice).
	 *
	 * @var bool
	 */
	private static $ran = false;

	/**
	 * @var bool
	 *
	 * @throws Exception
	 */
	public static function requireRecords($force = false) {
		if(self::$ran && !$force) {
			return true;
		}

		self::$ran = true;

		if(!(Director::isDev() || Director::isTest())) {
			throw new Exception('requireRecords can only be run in development or test environments');
		}

		$factory = Injector::inst()->create('PopulateFactory');

		foreach(self::config()->get('truncate_objects') as $objName) {
			$versions = array();

			if(class_exists($objName)) {
				foreach(DataList::create($objName) as $obj) {
					// if the object has the versioned extension, make sure we delete
					// that as well
					if($obj->hasExtension('Versioned')) {
						foreach($obj->getVersionedStages() as $stage) {
							$versions[$stage] = true;

							$obj->deleteFromStage($stage);
						}
					}

					try {
						@$obj->delete();
					} catch(Exception $e) {
						// notice
					}
				}
			}

			if($versions) {
				self::truncate_versions($objName, $versions);
			}

			foreach(ClassInfo::getValidSubClasses($objName) as $table) {
				self::truncate_table($table);
				self::truncate_versions($table, $versions);
			}

			self::truncate_table($objName);
		}

		foreach(self::config()->get('include_yaml_fixtures') as $fixtureFile) {
			$fixture = new YamlFixture($fixtureFile);
			$fixture->writeInto($factory);

			$fixture = null;
		}
		

		return true;
	}

	private static function truncate_table($table) {
		if(ClassInfo::hasTable($table)) {
			if(method_exists(DB::getConn(), 'clearTable')) {
				DB::getConn()->clearTable($table);
			} else {
				DB::query("TRUNCATE \"$table\"");
			}
		}
	}

	private static function truncate_versions($table, $versions) {
		self::truncate_table($table .'_versions');
		
		foreach($versions as $stage => $v) {
			self::truncate_table($table . '_'. $stage);
		}
	}
}