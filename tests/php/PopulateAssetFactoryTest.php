<?php

namespace DNADesign\Populate\Tests;

use DNADesign\Populate\PopulateFactory;
use SilverStripe\Assets\Folder;
use SilverStripe\Assets\Image;
use SilverStripe\Dev\SapphireTest;
use SilverStripe\Dev\TestOnly;

/**
 * Test populating files into assets folder.
 */
class PopulateAssetFactoryTest extends SapphireTest implements TestOnly
{
    /**
     * @var PopulateFactory
     */
    protected $factory;

    protected $usesDatabase = true;

    protected function setUp(): void
    {
        parent::setUp();
        $this->factory = new PopulateFactory();
    }

    /**
     * Assert that a file is loaded into the expected path within assets directory (Root path).
     */
    public function testLoadingFileToRootAssetsDirectory(): void
    {
        // Load a file using populate factory
        $obj = $this->factory->createObject(Image::class, 'image1', [
            'Filename' => 'image1.png',
            'PopulateFileFrom' => 'tests/php/fixture/assets/image1.png',
        ]);

        $this->assertInstanceOf(Image::class, $obj);

        // Assert that the file is created and exists in expected root directory of assets
        $this->assertEquals('image1.png', $obj->getFilename());
        $this->assertFileExists(ASSETS_PATH . '/image1.png');

        // Assert that no folder is created for a file in the root directory
        $this->assertSame(0, $obj->ParentID);
        $this->assertSame(0, Folder::get()->count());
    }

    /**
     * Assert that a file is loaded into the expected path within assets directory (Within folder)
     */
    public function testLoadingFileToFolderInAssetsDirectory(): void
    {
        // Load a file using populate factory
        $obj = $this->factory->createObject(Image::class, 'image1', [
            'Filename' => 'fixture/image1.png',
            'PopulateFileFrom' => 'tests/php/fixture/assets/image1.png',
        ]);

        $this->assertInstanceOf(Image::class, $obj);

        // Assert that the file is created and exists in expected root directory of assets
        $this->assertEquals('fixture/image1.png', $obj->getFilename());
        $this->assertFileExists(ASSETS_PATH . '/fixture/image1.png');

        // Assert that the file is placed in the folder from its filename
        $folder = Folder::find('fixture');
        $this->assertSame($folder->ID, $obj->ParentID);
    }
}
