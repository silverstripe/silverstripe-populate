<?php

namespace DNADesign\Populate\Tests;

use DNADesign\Populate\PopulateFactory;
use DNADesign\Populate\Tests\PopulateFactoryTest\PopulateFactoryTestObject;
use DNADesign\Populate\Tests\PopulateFactoryTest\PopulateFactoryTestVersionedObject;
use SilverStripe\Assets\File;
use SilverStripe\Assets\Image;
use SilverStripe\Dev\SapphireTest;
use SilverStripe\Dev\TestOnly;
use SilverStripe\Versioned\Versioned;

class PopulateFactoryTest extends SapphireTest implements TestOnly
{
    /**
     * @var PopulateFactory
     */
    private $factory;

    protected static $fixture_file = "PopulateFactoryTest.yml";

    protected $usesDatabase = true;

    protected static $extra_dataobjects = [
        PopulateFactoryTestObject::class,
        PopulateFactoryTestVersionedObject::class,
    ];

    protected function setUp(): void
    {
        parent::setUp();
        $this->factory = new PopulateFactory();
    }

    /**
     * Test version support. If an object has versioned then both the live and
     * staging tables should be updated. Other live records should be removed
     * as well.
     */
    public function testVersionedObjects(): void
    {
        $versioned = $this->objFromFixture(PopulateFactoryTestVersionedObject::class, 'objV1');

        $versioned->publishSingle();

        $obj = $this->factory->createObject(PopulateFactoryTestVersionedObject::class, 'test', [
            'Content' => 'Updated Version Foo',
            'PopulateMergeWhen' => "Title = 'Version Foo'",
        ]);

        $this->assertEquals($versioned->ID, $obj->ID);
        $this->assertEquals('Updated Version Foo', $obj->Content);

        $check = Versioned::get_one_by_stage(
            PopulateFactoryTestVersionedObject::class,
            'Live',
            "Title = 'Version Foo'"
        );

        $this->assertEquals('Updated Version Foo', $check->Content);
    }

    /**
     * As a utility you can include code to be evaluated the the yaml using
     * Field: `something::foo()`;
     */
    public function testCreateObjectPhpEval(): void
    {
        $obj = $this->factory->createObject(PopulateFactoryTestObject::class, 'test', [
            'Title' => '`sprintf("hi")`;',
        ]);

        $this->assertEquals('hi', $obj->Title);
    }

    /**
     * When a populatemergewhen clause is supplied, make sure it merges. If no
     * record found, one should be created
     *
     */
    public function testCreateObjectPopulateMergeWhen(): void
    {
        $obj = $this->factory->createObject(PopulateFactoryTestObject::class, 'test', [
            'Title' => 'Updated',
            'PopulateMergeWhen' => "Title = 'Foo'",
        ]);

        $this->assertEquals('Foo Content', $obj->Content, 'Records merged');

        $obj = $this->factory->createObject(PopulateFactoryTestObject::class, 'test', [
            'Title' => 'Updated',
            'PopulateMergeWhen' => "Title = 'This title is unknown'",
        ]);

        $this->assertGreaterThan(0, $obj->ID);
        $this->assertEquals('Updated', $obj->Title);
    }

    /**
     * When populatemergematch is provided then the matching should be done on
     * the given fields
     */
    public function testCreateObjectPopulateMergeMatch(): void
    {
        $id = PopulateFactoryTestObject::get()->filter([
            'Title' => 'Foo',
        ])->first()->ID;

        $obj = $this->factory->createObject(PopulateFactoryTestObject::class, 'test', [
            'Title' => 'Foo',
            'Content' => 'This has been replaced',
            'PopulateMergeMatch' => [
                'Title',
            ],
        ]);

        $this->assertEquals($id, $obj->ID, 'ID value has not changed');
        $this->assertEquals('This has been replaced', $obj->Content);
    }

    /**
     * When a lookup matches more than one page, only the first one should be
     * removed.
     */
    public function testMultipleMatchesRemoved(): void
    {
        $obj = $this->factory->createObject(PopulateFactoryTestObject::class, 'test', [
            'Title' => 'Updated',
            'PopulateMergeAny' => true,
        ]);

        $list = PopulateFactoryTestObject::get();

        $this->assertEquals(1, $list->count());
        $this->assertEquals('Updated', $list->first()->Title);
    }

    /**
     * Test to ensure creating standard file such as PDF and image with correct DataObject file.
     */
    public function testCreatingFileOrImage()
    {
        // Collection of data to create file and image
        $files = [
            'file.txt' => File::class,
            'image.png' => Image::class,
        ];

        // Create a file/image, check if data stored in database with expected DataObject class and file exists
        foreach ($files as $name => $class) {
            // BASE_PATH is prepended to the file path during populateFile(), so we need to remove it here
            $filePath = str_replace(BASE_PATH, '', sprintf('%s/../assets/%s', dirname(__FILE__), $name));
            $file = $this->factory->createObject(
                File::class,
                $name,
                [
                    'Filename' => $name,
                    'PopulateFileFrom' => $filePath,
                ]
            );

            $this->assertTrue((bool) $file?->exists());
            $this->assertEquals($class, $file->ClassName);
        }
    }
}
