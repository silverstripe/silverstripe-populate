<?php

namespace DNADesign\Populate\Tests\PopulateFactoryTest;

use SilverStripe\Dev\TestOnly;
use SilverStripe\ORM\DataObject;
use SilverStripe\Versioned\Versioned;

class PopulateFactoryTestVersionedObject extends DataObject implements TestOnly
{
    private static $table_name = 'PopulateFactoryTestVersionedObject';

    private static $db = [
        'Title' => 'Varchar',
        'Content' => 'Varchar',
    ];

    private static $extensions = [
        Versioned::class . '.versioned',
    ];
}
