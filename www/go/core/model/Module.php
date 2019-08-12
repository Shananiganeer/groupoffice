<?php
namespace go\core\model;

use Exception;
use go\core;
use go\core\model\Acl;
use go\core\acl\model\AclOwnerEntity;
use go\core\App;
use go\core\db\Query;
use go\core\orm\Entity;
use go\core\Settings;
use go\core\validate\ErrorCode;

class Module extends AclOwnerEntity {
	public $id;
	public $name;
	public $package;
	public $sort_order;
	public $admin_menu;
	public $version;
	public $enabled;


	/**
	 * This is here for compatibility with old modules management page that's not refactored yet. Remove when refactored.
	 * @deprecated
	 */
	public function getAclId() {
		return $this->aclId;
	}

	protected function canCreate()
	{
		return GO()->getAuthState()->getUser()->isAdmin();
	}
	
	protected static function textFilterColumns()
	{
		return ['name', 'package'];
	}
	
	protected function internalSave() {
		
		if($this->isNew()) {
			$this->sort_order = $this->nextSortOrder();			
		}
		
		if(!parent::internalSave()) {
			return false;
		}
		
		$settings = $this->getSettings();
		if($settings && !$settings->save()) {
			return false;
		}

		if($this->isModified(['enabled'])) {
			GO()->rebuildCache();
		}

		//When module groups change the groups change too. Because the have a "modules" property.
		$aclChanges = $this->getAclChanges();
		if(!empty($aclChanges)) {
			Group::entityType()
				->changes(
					GO()->getDbConnection()
						->select('id as entityId, aclId, "0" as destroyed')
						->from('core_group')
						->where('id', 'IN', array_keys($aclChanges)
					)
				);
		}
		
		return true;
	}
	
	public function package(){
		return self::PACKAGE_COMMUNITY;
	}
	
	private function nextSortOrder() {
		$query = new Query();			
		$query->from("core_module");

		if($this->package == "core") {
			$query->selectSingleValue("COALESCE(MAX(sort_order), 0) + 1")
				->where(['package' => "core"]);
		} else
		{
			$query->selectSingleValue("COALESCE(MAX(sort_order), 100) + 1")
				->where('package', '!=', "core");
		}

		return $query->single();
	}
	

	protected static function defineMapping() {
		return parent::defineMapping()->addTable('core_module', 'm');
	}
	
	
	/**
	 * Get the module base file object
	 * 
	 * @return core\Module
	 */
	public function module() {
		if($this->package == "core" && $this->name == "core") {
			return App::get();
		}
		
		$cls = $this->getModuleClass();
		
		return new $cls;
	}	
	
	private function getModuleClass() {		
		return "\\go\\modules\\" . $this->package ."\\" . $this->name ."\\Module";
	}

	public function folder() {
		$root = GO()->getEnvironment()->getInstallFolder();
		return $root->getFolder("/go/modules/" . $this->package ."/" . $this->name ."/");
	}	
	
	public function isAvailable() {
		
		
		if(!isset($this->package)) {
			//if module has not been refactored yet package is not set. 
			//handle this with old class
			$cls = "GO\\" . ucfirst($this->name) . "\\" . ucfirst($this->name) .'Module';
			if(!class_exists($cls)){
				return false;
			}
			
			return (new $cls)->isAvailable();
		}
		
		if($this->package == "core" && $this->name == "core") {
			return true;
		}
		
		//todo, how to handle licenses for future packages?
		$cls = $this->getModuleClass();
		return class_exists($cls) && (new $cls)->isLicensed();
	}

	/**
	 * Finds a module based on the given class name
	 * returns null if it belongs to the core.
	 * 
	 * @param string $className
	 * @return self
	 * @throws Exception
	 */
	public static function findByClass($className) {
		
		switch($className) {	
			
			case strpos($className, "go\\core") === 0 || strpos($className, "GO\\Base") === 0:
				$module = Module::find()->where(['name' => "core", "package" => "core"])->single();				
				break;
			
			default:				
				
				$classNameParts = explode('\\', $className);

				if($classNameParts[0] == "GO") {
					//old framework eg. GO\Projects2\Model\TimeEntry
					$name = strtolower($classNameParts[1]);
					$package = null;
				} else
				{
					$package = $classNameParts[2];
					$name = $classNameParts[3];
				}
				
				$module = Module::find()->where(['name' => $name, 'package' => $package])->single();
				
				// Needed for modules which are partly refactored.
				// For example: The email account entity is required in the new framework 
				// and the email module itself is not refactored yet.
				// Can be removed when all is refactored.
				if(!$module) {
					$module = Module::find()->where(['name' => $name, 'package' => null])->single();
				}
		}
		
		if(!$module) {
			throw new Exception("Module not found for ".$className);
			
		}
		return $module;
	}
	
	protected function internalValidate() {
		
		if(!$this->isNew()) {
			if($this->package == 'core' && $this->isModified('enabled')) {
				throw new \Exception("You can't disable core modules");		
			}
			
			if($this->isModified(['name', 'package'])) {
				$this->setValidationError('name', ErrorCode::FORBIDDEN,"You can't change the module name and package");
			}
		}
		
		
		return parent::internalValidate();
	}
	
	protected function internalDelete() {
		
		if($this->package == "core") {
			throw new \Exception("You can't delete core modules");
		}
	
		//hard delete!
		return Entity::internalDelete();
	}
	
	/**
	 * Get all installed and available modules.
	 * @return self[]
	 */
	public static function getInstalled() {
		$modules = Module::find()->where(['enabled' => true])->all();
		
		$available = [];
		foreach($modules as $module) {
			if($module->isAvailable()) {
				$available[] = $module;
			}
		}
		
		return $available;
	}
	
	/**
	 * Check if a module is available
	 * 
	 * @param string $package
	 * @param string $name
	 * @param int $userId
	 * @param int $level
	 * @return boolean
	 */
	public static function isAvailableFor($package, $name, $userId = null, $level = Acl::LEVEL_READ) {

		if($package == "legacy") {
			$package = null;
		}

		$query = static::find()->where(['package' => $package, 'name' => $name, 'enabled' => true]);
		static::applyAclToQuery($query, $level, $userId);
		
		return $query->single() !== false;
	}
	
	/**
	 * Find a module by package and name
	 * 
	 * @param string $package
	 * @param string $name
	 * @param bool $enabled
	 * @return self
	 */
	public static function findByName($package, $name, $enabled = true) {
		if($package == "legacy") {
			$package = null;
		}
		return static::find()->where(['package' => $package, 'name' => $name, 'enabled' => $enabled])->single();
	}

	/**
	 * Check if a module is installed
	 *
	 * @param string $package
	 * @param string $name
	 * @return bool
	 */
	public static function isInstalled($package, $name) {
		return static::findByName($package, $name) != false;
	}
	
	/**
	 * Get module settings
	 * 
	 * @return Settings
	 */
	public function getSettings() {
		if(!isset($this->package)) {
			return null;
		}

		if(!$this->isAvailable()) {
			return null;
		}
		
		return $this->module()->getSettings();
	}
	
	/**
	 * Returns all module entities with info
	 * @return EntityType[]
	 */
	public function getEntities() {		
		$es = [];

		foreach(core\orm\EntityType::findAll((new core\orm\Query)->where(['moduleId' => $this->id])) as $e) {
			$es[$e->getName()] = $e;
		}

		return $es;
	}

	public function setEntities($entities) {
		$current = $this->getEntities();

		foreach($entities as $name => $e) {
			if(isset($e['defaultAcl'])) {
				$current[$name]->setDefaultAcl($e['defaultAcl']);
			}
		}
	}
	
	public function setSettings($value) {
		$this->getSettings()->setValues($value);
	}
}
