<?php

namespace DNADesign\Populate\Tests\PopulateFactoryTest;

use SilverStripe\ORM\DataObject;
use SilverStripe\Dev\TestOnly;
use SilverStripe\Versioned\Versioned;

class PopulateFactoryTestVersionedObject extends DataObject implements TestOnly {
    private static $table_name = 'PopulateFactoryTestVersionedObject';

    private static $db = array(
        'Title' => 'Varchar',
        'Content' => 'Varchar'
    );

    private static $extensions = array(
        Versioned::class . '.versioned'
    );
}
