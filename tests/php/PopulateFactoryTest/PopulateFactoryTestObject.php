<?php

namespace DNADesign\Populate\Tests\PopulateFactoryTest;

use SilverStripe\ORM\DataObject;
use SilverStripe\Dev\TestOnly;

/**
 * @package populate
 */
class PopulateFactoryTestObject extends DataObject implements TestOnly {
    private static $table_name = 'PopulateFactoryTestObject';

    private static $db = array(
        'Title' => 'Varchar',
        'Content' => 'Varchar'
    );

    private static $has_one = array(
        'RelatedTest' => PopulateFactoryTestObject::class
    );
}
