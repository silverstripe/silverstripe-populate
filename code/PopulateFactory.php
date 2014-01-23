<?php

/**
 * @package populate
 */
class PopulateFactory extends FixtureFactory {

	/**
	 * Creates the object in the database as the original object will be wiped.
	 *
	 * @param string $class
	 * @param string $identifier
	 * @param array $data
	 */
	public function createObject($class, $identifier, $data = null) {
		DB::alteration_message("Creating $identifier ($class)");
		
		if($data) {
			foreach($data as $k => $v) {
				if(preg_match('/^`(.)*`;$/', $v)) {
					$str = substr($v, 1, -2);
					$pv = null;

					eval("\$pv = $str;");

					$data[$k] =	$pv;
				}
			}
		}

		// if any merge labels are defined then we should create the object
		// from that 
		$lookup = null;

		if(isset($data['PopulateMergeWhen'])) {
			$lookup = DataList::create($class)->where(
				$data['PopulateMergeWhen']
			);
		}
		else if(isset($data['PopulateMergeMatch'])) {
			$filter = array();

			foreach($data['PopulateMergeMatch'] as $field) {
				$filter[$field] = $data[$field];
			}

			if(!$filter) {
				throw new Exception('Not a valid PopulateMergeMatch filter');
			}

			$lookup = DataList::create($class)->filter($filter);
		}
		else if(isset($data['PopulateMergeAny'])) {
			$lookup = DataList::create($class);
		}

		if($lookup && $lookup->count() > 0) {
			$obj = $lookup->first();

			foreach($lookup->limit(null, 1) as $old) {
				if($old->hasExtension('Versioned')) {
					$old->deleteFromStage('Live');
				}

				$old->delete();
			}

			$obj->update($data);
			$obj->write();

			if($obj) {
				$this->fixtures[$class][$identifier] = $obj; 
			}
		}
		else {
			$obj = parent::createObject($class, $identifier, $data);
		}

		if($obj->hasExtension('Versioned')) {
			$obj->publish('Stage', 'Live');
			$obj->flushCache();
		}

		return $obj;
	}
}