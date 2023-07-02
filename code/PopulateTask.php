<?php

namespace DNADesign\Populate;

use Exception;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\Dev\BuildTask;

class PopulateTask extends BuildTask
{
    private static string $segment = 'PopulateTask';

    /**
     * @param HTTPRequest $request
     * @throws Exception
     */
    public function run($request)
    {
        Populate::requireRecords();
    }
}
