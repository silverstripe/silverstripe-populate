<?php

namespace DNADesign\Populate\Tests\PopulateFactoryTest;

use SilverStripe\Dev\TestOnly;
use SilverStripe\ORM\DataObject;

/**
 * @package populate
 */
class PopulateFactoryTestObject extends DataObject implements TestOnly
{
    private static $table_name = 'PopulateFactoryTestObject';

    private static $db = [
        'Title' => 'Varchar',
        'Content' => 'Varchar',
    ];

    private static $has_one = [
        'RelatedTest' => PopulateFactoryTestObject::class,
    ];
}
