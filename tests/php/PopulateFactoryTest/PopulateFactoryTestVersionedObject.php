<?php

namespace DNADesign\Populate\Tests\PopulateFactoryTest;

use SilverStripe\Dev\TestOnly;
use SilverStripe\ORM\DataObject;
use SilverStripe\Versioned\Versioned;

/**
 * @mixin Versioned
 */
class PopulateFactoryTestVersionedObject extends DataObject implements TestOnly
{
    private static string $table_name = 'PopulateFactoryTestVersionedObject';

    private static array $db = [
        'Title' => 'Varchar',
        'Content' => 'Varchar',
    ];

    private static array $extensions = [
        Versioned::class . '.versioned',
    ];
}
