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
				if(!(is_array($v)) && preg_match('/^`(.)*`;$/', $v)) {
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
		$mode = null;

		if(isset($data['PopulateMergeWhen'])) {
			$mode = 'PopulateMergeWhen';

			$lookup = DataList::create($class)->where(
				$data['PopulateMergeWhen']
			);

			unset($data['PopulateMergeWhen']);

		} else if(isset($data['PopulateMergeMatch'])) {
			$mode = 'PopulateMergeMatch';
			$filter = array();

			foreach($data['PopulateMergeMatch'] as $field) {
				$filter[$field] = $data[$field];
			}

			if(!$filter) {
				throw new Exception('Not a valid PopulateMergeMatch filter');
			}

			$lookup = DataList::create($class)->filter($filter);
	
			unset($data['PopulateMergeMatch']);
		} else if(isset($data['PopulateMergeAny'])) {
			$mode = 'PopulateMergeAny';
			$lookup = DataList::create($class);

			unset($data['PopulateMergeAny']);
		}

		if($lookup && $lookup->count() > 0) {
			$obj = $lookup->first();
		
			foreach($lookup as $old) {
				if($old->ID == $obj->ID) {
					continue;
				}
				
				if($old->hasExtension('Versioned')) {
					foreach($old->getVersionedStages() as $stage) {
						$old->deleteFromStage($stage);
					}
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
			foreach($obj->getVersionedStages() as $stage) {
				if($stage !== $obj->getDefaultStage()) {
					$obj->write();

					$obj->publish($obj->getDefaultStage(), $stage);
				}
			}

			$obj->flushCache();
		}

		return $obj;
	}
}