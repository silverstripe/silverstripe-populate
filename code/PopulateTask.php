<?php

/**
 * @package populate
 */
class PopulateTask extends BuildTask {
	
	public function run($request) {
		Populate::requireRecords();
	}
}