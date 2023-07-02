<?php

namespace DNADesign\Populate\Tests\PopulateFactoryTest;

use SilverStripe\Dev\TestOnly;
use SilverStripe\ORM\DataObject;
use SilverStripe\Versioned\Versioned;

/**
 * @mixin Versioned
 */
class PopulateFactoryTestObject extends DataObject implements TestOnly
{
    private static string $table_name = 'PopulateFactoryTestObject';

    private static array $db = [
        'Title' => 'Varchar',
        'Content' => 'Varchar',
    ];

    private static array $has_one = [
        'RelatedTest' => PopulateFactoryTestObject::class,
    ];
}
