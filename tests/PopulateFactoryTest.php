<?php

/**
 * @package populate
 */
class PopulateFactoryTest extends SapphireTest {
	
	protected static $fixture_file = "PopulateFactoryTest.yml";

	protected $extraDataObjects = array(
		'PopulateFactoryTest_TestObject',
		'PopulateFactoryTest_TestVersionedObject'
	);

	public function setUp() {
		parent::setUp();

		$this->factory = new PopulateFactory();
	}


	/**
	 * Test version support. If an object has versioned then both the live and
	 * staging tables should be updated. Other live records should be removed
	 * as well.
	 */
	public function testVersionedObjects() {
		$versioned = $this->objFromFixture(
			'PopulateFactoryTest_TestVersionedObject', 'objV1'
		);

		$versioned->publish('Stage', 'Live');

		$obj = $this->factory->createObject('PopulateFactoryTest_TestVersionedObject', 'test', array(
			'Content' => 'Updated Version Foo',
			'PopulateMergeWhen' => "Title = 'Version Foo'"
		));

		$this->assertEquals($versioned->ID, $obj->ID);
		$this->assertEquals('Updated Version Foo', $obj->Content);

		$check = Versioned::get_one_by_stage(
			'PopulateFactoryTest_TestVersionedObject', 
			'Live', 
			"Title = 'Version Foo'"
		);

		$this->assertEquals('Updated Version Foo', $check->Content);
	}

	/**
	 * As a utility you can include code to be evaluated the the yaml using
	 * Field: `something::foo()`;
	 */
	public function testCreateObjectPhpEval() {
		$obj = $this->factory->createObject('PopulateFactoryTest_TestObject', 'test', array(
			'Title' => '`sprintf("hi")`;'
		));

		$this->assertEquals('hi', $obj->Title);
	}

	/**
	 * When a populatemergewhen clause is supplied, make sure it merges. If no
	 * record found, one should be created
	 * 
	 */
	public function testCreateObjectPopulateMergeWhen() {
		$obj = $this->factory->createObject('PopulateFactoryTest_TestObject', 'test', array(
			'Title' => 'Updated',
			'PopulateMergeWhen' => "Title = 'Foo'"
		));

		$this->assertEquals('Foo Content', $obj->Content, 'Records merged');

		$obj = $this->factory->createObject('PopulateFactoryTest_TestObject', 'test', array(
			'Title' => 'Updated',
			'PopulateMergeWhen' => "Title = 'This title is unknown'"
		));

		$this->assertGreaterThan(0, $obj->ID);
		$this->assertEquals('Updated', $obj->Title);
	}

	/**
	 * When populatemergematch is provided then the matching should be done on
	 * the given fields
	 */
	public function testCreateObjectPopulateMergeMatch() {
		$id = PopulateFactoryTest_TestObject::get()->filter(array(
			'Title' => 'Foo'
		))->first()->ID;

		$obj = $this->factory->createObject('PopulateFactoryTest_TestObject', 'test', array(
			'Title' => 'Foo',
			'Content' => 'This has been replaced',
			'PopulateMergeMatch' => array(
				'Title'
			)
		));

		$this->assertEquals($id, $obj->ID, 'ID value has not changed');
		$this->assertEquals('This has been replaced', $obj->Content);
	}

	/**
	 * When a lookup matches more than one page, only the first one should be 
	 * removed.
	 */
	public function testMultipleMatchesRemoved() {
		$obj = $this->factory->createObject('PopulateFactoryTest_TestObject', 'test', array(
			'Title' => 'Updated',
			'PopulateMergeAny' => true
		));

		$list = PopulateFactoryTest_TestObject::get();

		$this->assertEquals(1, $list->count());
		$this->assertEquals('Updated', $list->first()->Title);
	}

}

/**
 * @package populate
 */
class PopulateFactoryTest_TestObject extends DataObject implements TestOnly {

	private static $db = array(
		'Title' => 'Varchar',
		'Content' => 'Varchar'
	);

	private static $has_one = array(
		'RelatedTest' => 'PopulateTest_TestObject'
	);
}

/**
 * @package populate
 */
class PopulateFactoryTest_TestVersionedObject extends DataObject implements TestOnly {

	private static $db = array(
		'Title' => 'Varchar',
		'Content' => 'Varchar'
	);

	private static $extensions = array(
		"Versioned('Stage', 'Live')"
	);
}