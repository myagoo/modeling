<?php

namespace Modeling;

class ConnectionManager {

	protected $configurations = array();

	protected static $instance;

	protected $connections = array();

	private function __construct() {
		$this->loadConfigurationFromPhpFile();
	}

	public function getConnection($name = 'default'){
		if(!isset($this->connections[$name])){
			if(!isset($this->configurations[$name])){
				throw new Exception('[MySql] given database name "' . $name . '" is not configured');
			}
			$dsn = 'mysql:host=' . $this->configurations[$name]['host'] . ';dbname=' . $this->configurations[$name]['database'];
			$this->connections[$name] = new PDO($dsn, $this->configurations[$name]['user'], $this->configurations[$name]['password']);
		}
		return $this->connections[$name];
	}

	public static function getInstance(){
		if(!isset(self::$instance)){
			self::$instance = new static();
		}
		return self::$instance;
	}

	public function loadConfigurationFromArray($array = array()){
		if(!is_array($array)){
			throw new Exception('toto');
		}
		$this->configurations = array_merge($this->configurations, $array);
	}
	public function loadConfigurationFromPhpFile($file = 'configuration.php'){
		if(!is_file($file)){
			throw new Exception('toto');
		}
		$this->loadConfigurationFromArray(include($file));
	}
}