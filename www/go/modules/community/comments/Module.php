<?php
namespace go\modules\community\comments;

use go\core\model\User;
use go\core;
use go\core\orm\Mapping;
use go\core\orm\Property;
use go\modules\community\comments\model\Settings;
use go\core\cron\GarbageCollection;
use go\core\orm\EntityType;
use go\core\orm\Query;
use GO\Base\Db\ActiveRecord;

class Module extends core\Module {	

	public function getAuthor() {
		return "Intermesh BV";
	}
	
	public function defineListeners() {
		GarbageCollection::on(GarbageCollection::EVENT_RUN, static::class, 'garbageCollection');
	}
	
	public static function garbageCollection() {
		$types = EntityType::findAll();

		GO()->debug("Cleaning up comments");
		foreach($types as $type) {
			if($type->getName() == "Link" || $type->getName() == "Search") {
				continue;
			}

			$cls = $type->getClassName();

			if(is_a($cls,  ActiveRecord::class, true)) {
				$tableName = $cls::model()->tableName();
			} else{
				$tableName = array_values($cls::getMapping()->getTables())[0]->getName();
			}
			$query = (new Query)->select('sub.id')->from($tableName);

			$stmt = GO()->getDbConnection()->delete('core_search', (new Query)
				->where('entityId', '=', $type->getId())
				->andWhere('entityId', 'NOT IN', $query)
			);
			$stmt->execute();

			GO()->debug("Deleted ". $stmt->rowCount() . " comments for $cls");
		}
	}
}
