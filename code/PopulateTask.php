<?php

namespace DNADesign\Populate;

use SilverStripe\Dev\BuildTask;

/**
 * @package populate
 */
class PopulateTask extends BuildTask {

	public function run($request) {
		Populate::requireRecords();
	}
}
