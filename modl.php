<?php

namespace Modl;

class Modl {

	public static $database;
	public static $table;
	public static $key = 'id';
	public static $configuration = 'default';
	private static $connection;


	/**
	* Instancie la bonne connection et récupère les infos des champs de la table
	**/
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
	public function read($id) {
		if(empty($id)){
			throw new Exception('Id must be specified');
		}
		$query = 'SELECT * FROM `' . $this->database . '`.`' . $this->table . '` WHERE `' . $this->key . '` = ?';
		if (($statement = $this->connection->prepare($query)->execute($values)) != false) {
			$data = $statement->fetchAll(\PDO::FETCH_ASSOC);
			return $data[0];
		}else{
			return false;
		}
	}


	public function insert($data){
		if(!$entity instanceOf Entity){
			throw new Exception('Not an entity');
		}
		foreach($data as $field => $value){
			if(!isset($this->fields[$field])){
				unset($data[$field]);
			}
		}
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
		foreach($data as $field => $value){
			if(!isset($this->fields[$field])){
				unset($data[$field]);
			}
		}
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

	/**
	* Retourne plusieurs lignes de résultats
	**/
	public function find($options = array()) {
		$qb = $this->getQueryBuilder();
		$qb->select('[' . $this->getClass() . '.*]');
		if(!empty($options['condition'])){
			foreach($options['condition'] as $condition){
				$qb->where($condition[0], $condition[1], $condition[2]);
			}
		}
		if(!empty($option['order'])){
			foreach($options['order'] as $order){
				$qb->order($condition[0], $condition[1]);
			}
		}
		if(!empty($option['limit'])){
			$qb->limit()
		}


	}

	public function getQueryBuilder(){
		$qb = new QueryBuilder();
		if(!empty($this->table)){
			$qb->from('[' . $this->getClass() . ']');
		}
		return $qb;
	}

	 public static function delete($id) {
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