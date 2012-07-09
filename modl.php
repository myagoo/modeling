<?php

namespace Modl;

class Modl {

	public static $database;
	public static $table;
	public static $key = 'id';
	public static $configuration = 'default';
	private static $connection;

	/**
	 *
	 * */

	private function __construct() {
		$this->connection = ConnectionManager::getInstance()->getConnection($this->configuration);
		$query = 'DESCRIBE `' . $this->database . '`.`' . $this->table . '`';
		$statement = $this->connection->prepare($query);
		if (!$statement->execute() ) {
			return false;
		}
		while ( $row = $statement->fetch(PDO::FETCH_ASSOC) ) {
			$field = $row['Field'];
			unset($row['Field']);
			$this->fields[$field] = $row;
		}
	}

	public static function getInstance(){
		if(!isset(self::$instance)){
			self::$instance = new static();
		}
		return self::$instance;
	}


	/**
	 * Permet une lecture rapide d'un modèle grace à son identifiant
	 * */
	public function get($id) {
		if(empty($id)){
			throw new Exception('Id must be specified');
		}
		$query = 'SELECT * FROM `' . $this->database . '`.`' . $this->table . '` WHERE `' . $this->key . '` = ?';
		if (($statement = $this->connection->prepare($query)->execute($values)) != false) {
			$data = $statement->fetchAll(\PDO::FETCH_ASSOC);
			return EntityManager::getInstance()->create($data[0]);
		}else{
			return false;
		}
	}


	protected function extractDataFromEntity($entity){
		$data = array();
		$fields = get_object_vars($entity);
		foreach($fields as $field => $value){
			if(isset($this->fields[$field])){
				$data[$field] = $value;
			}
		}
		return $data;
	}

	public function insert($entity){
		if(!$entity instanceOf Entity){
			throw new Exception('Not an entity');
		}
		$data = $this->extractDataFromEntity($entity);
		if(empty($data)){
			throw new Exception('Empty data');
		}
		if(isset($this->fields['created'])){
			$data['created'] = date('Y-m-d H:i:s');
		}
		$query = 'INSERT `' . $this->database . '`.`' . $this->table . '` SET ';
		$values = array();
		foreach($data as $field => $value){
			$query .= '`' . $field . '` = ?, ';
			$values[] = $value;
		}
		$query = substr($query, 0, -2);
		if ( $this->connection->prepare($query)->execute($values) ) {
			return $this->connection->lastInsertId();
		}else{
			return false;
		}
	}

	public function update($entity){
		if(!$entity instanceOf Entity){
			throw new Exception('Not an entity');
		}
		$data = $this->extractDataFromEntity($entity);
		// Key is not specified
		if(!isset($data[$this->key])){
			throw new Exception('Primary key must be specified');
		}
		// Only the key is specified
		if(count($data) == 1){
			throw new Exception('Empty data');
		}
		if(isset($this->fields['modified'])){
			$data['modified'] = date('Y-m-d H:i:s');
		}
		$query = 'UPDATE `' . $this->database . '`.`' . $this->table . '` SET ';
		$values = array();
		foreach($data as $field => $value){
			if($field != $this->key){
				$query .= '`' . $field . '` = ?, ';
				$values[] = $value;
			}
		}
		$query = substr($query, 0, -2) . ' WHERE `' . $this->key . '` = ?';
		$values[] = $data[$this->key];
		if ( $this->connection->prepare($query)->execute($values) ) {
			return $data[$this->key];
		}else{
			return false;
		}
	}

	public function find($options = array()) {
		$conditions = '1=1';
		$fields = '*';
		if (!empty($this->fields)) {
			$fields = '';
			foreach ($this->fields as $fieldName => $informations) {
				$fields .= $this->table . '.' . $fieldName . ' as ' . get_Class($this) . '_' . $fieldName . ', ';
			}
			$fields = substr($fields, 0, -2);
		}
		$limit = '';
		$order = $this->table . '.' . $this->key . ' ASC';
		$left_outer = '';
		//
		if (!empty($this->belongsTo)) {
			foreach ($this->belongsTo as $modelName) {
				$model = $this->load($modelName);
				//E.G. : , posts.id as post_id
				$fields .= ', ' . $model->table . '.' . $model->key . ' as ' . $modelName . '_' . $model->key;
				$fields .= ', ' . $model->table . '.' . $model->displayField . ' as ' . $modelName . '_' . $model->displayField;
				$left_outer .= ' LEFT OUTER JOIN ' . $model->table . ' ON ' . $this->table . '.' . $modelName . '_id = ' . $model->table . '.id';
			}
		}
		//
		if (!empty($options['conditions'])) {
			$conditions = $options['conditions'];
		}
		if (!empty($options['fields'])) {
			$fields = $options['fields'];
		}
		if (!empty($options['limit'])) {
			$limit = ' LIMIT ' . $options['limit'];
		}
		if (!empty($options['order'])) {
			$order = $this->table . '.' . $options['order'];
		}
		$query = 'SELECT ' . $fields . ' FROM ' . $this->table . $left_outer . ' WHERE ' . $conditions . ' ORDER BY ' . $order . $limit;
		$results = mysql_query($query) or die(mysql_error());
		if (mysql_num_rows($results)) {
			$i = 0;
			while ($row = mysql_fetch_assoc($results)) {
				foreach ($row as $fieldName => $value) {
					$pos = strpos($fieldName, '_');
					$prefix = substr($fieldName, 0, $pos);
					$sufix = substr($fieldName, $pos + 1, strlen($fieldName));
					if ($prefix == get_Class($this)) {
						$data[$i][$sufix] = $value;
					} else {
						$data[$i][$prefix][$sufix] = $value;
					}
				}
				$i++;
			}
			return $data;
		} else {
			return false;
		}
	}

	 public static function delete($id = null) {
		if ( empty($id) ) {
			throw new Exception('Missing argument for delete method');
		}
		$query = 'DELETE FROM `' . $this->database . '`.`' . $this->table . '` WHERE ' . $this->key . ' = ?';
		$values[] = $id;
		if($this->$connection->prepare($query)->execute($values)){
			return true;
		}else{
			return false;
		}
	}
}