<?php

namespace DNADesign\Populate;

use Exception;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\Dev\BuildTask;

/**
 * @package populate
 */
class PopulateTask extends BuildTask
{
    private static $segment = 'PopulateTask';
        
    /**
     * @param HTTPRequest $request
     * @throws Exception
     */
    public function run($request)
    {
        Populate::requireRecords();
    }
}
