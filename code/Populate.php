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

		if(!Director::isDev()) {
			throw new Exception('requireRecords can only be run in development environments');
		}

		$factory = Injector::inst()->create('PopulateFactory');

		foreach(self::config()->get('truncate_objects') as $objName) {
			// if the object has the versioned extension, make sure we delete
			// that as well
			foreach(DataList::create($objName) as $obj) {
				if($obj->hasExtension('Versioned')) {
					foreach($obj->getVersionedStages() as $stage) {
						$obj->deleteFromStage($stage);
					}
				}

				$obj->delete();
			}
		}

		foreach(self::config()->get('include_yaml_fixtures') as $fixtureFile) {
			$fixture = new YamlFixture($fixtureFile);
			$fixture->writeInto($factory);
		}

		return true;
	}
}