<?php
class QueryBuilder {

    public $schemas = array(
		// QDFX
		'kpi' => array(
			'table' => 'qdfx_kpi_dsl_if_cisco_quarter',
			'parents' => array(
				'interface' => array(
					'from' => 'ne',
					'target' => 'id_interface'
				)
			)
		),
		'interface' => array(
			'table' => 'qdfx_interfaces',
			'parents' => array(
				'equipment' => array(
					'from' => 'id_eq',
					'target' => 'id_eq'
				)
			)
		),
		'equipment' => array(
			'table' => 'qdfx_equipments',
			'parents' => array(
			)
		),

		// PA
		'flow' => array(
			'table' => 'performanceFlows',
			'parents' => array(
				'router' => array(
					'from' => 'routerIP',
					'target' => 'ip'
					),
				'nbar' => array(
					'from' => 'APPLICATION_ID',
					'target' => 'APPLICATION_ID'
					)
				)
			),
		'nbar' => array(
			'table' => 'applicationMappingNBAR',
			'parents' => array(
				'nbar_groups_mapping' => array(
					'from' => 'APPLICATION_ID',
					'target' => 'nbar'
					)
				)
			),
		'nbar_groups' => array(
			'table' => 'nbar_groups',
			'parents' => array(
				)
			),
		'nbar_groups_mapping' => array(
			'table' => 'nbar_groups_mapping',
			'parents' => array(
				'nbar_groups' => array(
					'from' => 'nbar_groups',
					'target' => 'id'
					)
				)
			),
		'app' => array(
			'table' => 'applications',
			'parents' => array(
				)
			),
		'custom_app' => array(
			'table' => 'custom_application',
			'parents' => array(
				)
			),
		'custom_app_def' => array(
			'table' => 'custom_application_condition',
			'parents' => array(
				'custom_app' => array(
					'from' => 'custom_application',
					'target' => 'id'
					)
				)
			),
		'router' => array(
			'table' => 'router',
			'key' => 'ip',
			'parents' => array(
				'place' => array(
					'from' => 'place',
					'target' => 'id'
					)
				)
			),
		'place' => array(
			'table' => 'place',
			'key' => 'id',
			'parents' => array(
				)
			)
		);

	public $select = array();
	public $from;
	public $join = array();
	public $where = array();
	public $group = array();
	public $order = array();
	public $having = array();
	public $values = array();
	public $temp = array();
	public $connection;


	public function __construct($modl){
		if()
	}

	public function Context($context = array()){
		foreach ($context as $key => $value) {
			switch($key){
				case 'place':
					$this->Where('[place.' . (is_numeric($value) ? 'id' : 'name') . ']', '=', $value);
				break;
				case 'router':
					$this->Where('[router.ip]', '=', $value);
				break;
				case 'user':
					$this->Where('[flow.SOURCE_IP]', '=', $value);
				break;
				case 'nbar':
					$this->Where('[flow.APPLICATION_ID]', '=', $value);
				break;
				case 'multinbar':
					$this->Where('[flow.APPLICATION_ID]', 'IN', $value);
				break;
				case 'group':
					$this->Where('[nbar_groups_mapping.nbar_groups]', '=', $value);
				break;
				case 'app':
					$self = get_class($this);
					$qb = new $self($this->connection);
					$conditions = $qb->Select('[app.http]')
					->Select('[app.ip]')
					->Select('[app.port]')
					->Select('[app.nbar]')
					->From('app')
					->Where('[app.id]', '=', $value)
					->Exec();
					$conditions = $conditions[0];
					foreach ($conditions as $key => $value) {
						$ignore = false;
						if(!empty($value)){
							switch ($key) {
								case 'ip':
									$field = '[flow.DEST_IP]';
								break;
								case 'nbar':
									$field = '[flow.APPLICATION_ID]';
								break;
								case 'port':
									$field = '[flow.DEST_PORT]';
								break;
								default:
									$ignore = true;
								break;
							}
						}else{
							$ignore = true;
						}
						if(!$ignore){
							$this->Where($field, '=', $value);
						}
					}
				break;
				case 'application':
					$self = get_class($this);
					$qb = new $self($this->connection);
					$conditions = $qb->Select('[custom_app_def.field]')
					->Select('[custom_app_def.operator]')
					->Select('[custom_app_def.value]')
					->From('custom_app_def')
					->Where('[custom_app.' . (is_numeric($value) ? 'id' : 'name') . ']', '=', $value)
					->Exec();
					foreach ($conditions as $condition) {
						if($condition['operator'] == 'IN' || $condition['operator'] == 'NOT IN'){
							$condition['value'] = explode(',', $condition['value']);
						}
						$this->Where($condition['field'], $condition['operator'], $condition['value']);
					}
				break;
				case 'interval':
					$start = time();
					$end = $start;
					if(!is_array($value)){
						switch($value){
							case 'hour':
								$start -= 3600;
							break;
							case 'day':
								$start -= 86400;
							break;
							case 'week':
								$start -= 604800;
							break;
							case 'month':
								$start -= 2592000;
							break;
						}
						$start = date('Y-m-d H:i:s', $start);
						$end = date('Y-m-d H:i:s', $end);
					}else{
						$start = $value['start'];
						$end = $value['end'];
					}
					$this->Where('[flow.datetime]', 'BETWEEN', array($start, $end));
				break;
			}
		}
		return $this;
	}

	public function flush(){
		$this->select = array();
		$this->from = '';
		$this->join = array();
		$this->where = array();
		$this->group = array();
		$this->order = array();
		$this->values = array();
		$this->having = array();
		$this->limit = array();
		$this->temp = array();
		return $this;
	}

	public function parse($string) {
		$matches = array();
		preg_match_all('/\[([a-z_-]*)\.([a-z0-9_-]*)\]/i', $string, $matches, PREG_SET_ORDER);
		$return = array();
		foreach ($matches as $match) {
			$string = str_replace($match[0], '`' . $match[1] . '`.`' . $match[2] . '`', $string);
			$this->Table($match[1]);
		}
		return $string;
	}

	public function Select($field, $alias = false) {
		$field = $this->parse($field);
		$this->select[] = array('field' => $field, 'alias' => $alias);
		return $this;
	}

	public function Table($table) {
		$this->table[$table] = true;
		return $this;
	}

	public function From($table) {
		$this->from = $table;
		unset($this->table[$table]);
		return $this;
	}

	public function Join($table, $from) {
		$this->join[$table] = $from;
		return $this;
	}

	public function Group($field) {
		$field = $this->parse($field);
		$this->group[] = $field;
		return $this;
	}

	public function Order($field, $desc = false) {
		$field = $this->parse($field);
		$this->order[] = array(
			'field' => $field,
			'direction' => $desc ? 'DESC' : 'ASC'
		);
		return $this;
	}

	public function Limit($start, $offset) {
		$this->limit = array(
			'start' => $start,
			'offset' => $offset
		);
		return $this;
	}

	public function Where($field, $operator, $value) {
		$field = $this->parse($field);
		$this->where[] = array(
			'field' => $field,
			'operator' => $operator,
			'value' => $value
			);
		return $this;
	}
	public function Having($field, $operator, $value) {
		$field = $this->parse($field);
		$this->having[] = array(
			'field' => $field,
			'operator' => $operator,
			'value' => $value
			);
		return $this;
	}

	public function makeJoin() {
		foreach ($this->table as $table => $infos) {
			$this->guessJoin($table, $this->from);
		}
		$this->orderJoin($this->from);
		$this->join = $this->temp;
		return $this;
	}

	public function orderJoin($from){
		foreach ($this->join as $key => $value) {
			if($from == $value){
				$this->temp[$key] = $from;
				unset($this->join[$key]);
				$this->orderJoin($key);
			}
		}
	}


	public function guessJoin($target, $from) {
		if (!empty($this->schemas[$from]['parents'])) {
			foreach ($this->schemas[$from]['parents'] as $parent => $infos) {
				if ($parent == $target || $this->guessJoin($target, $parent)) {
					$this->Join($parent, $from);
					return true;
				}
			}
		}
		return false;
	}

	public function getQuery() {
		$this->makeJoin();
		$values = array();
		$query = 'SELECT ';
		foreach ($this->select as $select) {
			$query .= $select['field'];
			if (!empty($select['alias'])) {
				$query .= ' AS "' . $select['alias'] . '"';
			}
			$query .= ',' . PHP_EOL;
		}
		$query = substr($query, 0, -2) . PHP_EOL;
		$query .= 'FROM ' . $this->schemas[$this->from]['table'] . ' AS ' . $this->from . PHP_EOL;
		foreach ($this->join as $target => $from) {
			$infos = $this->schemas[$from]['parents'][$target];
			$query .= 'LEFT JOIN `' . $this->schemas[$target]['table'] . '` AS ' . $target . ' ON `' . $target . '`.`' . $infos['target'] . '` = `' . $from . '`.`' . $infos['from'] . '`' . PHP_EOL;
		}
		$i = 0;
		foreach ($this->where as $where) {
			if (!$i) {
				$query .= 'WHERE ';
			} else {
				$query .= 'AND ';
			}
			if (in_array($where['operator'], array('=', '<>', '>', '<', '>=', '<=', 'LIKE', 'NOT LIKE'))) {
				$query .= $where['field'] . ' ' . $where['operator'] . ' ?';
				$values[] = $where['value'];
			}
			if (in_array($where['operator'], array('IN', 'NOT IN'))) {
				$query .= $where['field'] . ' ' . $where['operator'] . ' (';
					foreach ($where['value'] as $value) {
						$query .= '?, ';
						$values[] = $value;
					}
					$query = substr($query, 0, -2) . ')';
			}
			if (in_array($where['operator'], array('BETWEEN'))) {
				$query .= $where['field'] . ' ' . $where['operator'] . ' ? AND ?';
				$values[] = $where['value'][0];
				$values[] = $where['value'][1];
			}
			$query .= PHP_EOL;
			$i++;
		}
		if(!empty($this->group)){
			$query .= 'GROUP BY ' . implode(', ', $this->group) . PHP_EOL;
		}
		$i = 0;
		foreach ($this->having as $having) {
			if (!$i) {
				$query .= 'HAVING ';
			} else {
				$query .= 'AND ';
			}
			if (in_array($having['operator'], array('=', '<>', '>', '<', '>=', '<=', 'LIKE', 'NOT LIKE'))) {
				$query .= $having['field'] . ' ' . $having['operator'] . ' ?';
				$values[] = $having['value'];
			}
			if (in_array($having['operator'], array('IN', 'NOT IN'))) {
				$query .= $having['field'] . ' ' . $having['operator'] . ' (';
					foreach ($having['value'] as $value) {
						$query .= '?, ';
						$values[] = $value;
					}
					$query = substr($query, 0, -2) . ')';
			}
			if (in_array($having['operator'], array('BETWEEN'))) {
				$query .= $having['field'] . ' ' . $having['operator'] . ' ? AND ?';
				$values[] = $having['value'][0];
				$values[] = $having['value'][1];
			}
			$query .= PHP_EOL;
			$i++;
		}
		if(!empty($this->order)){
			$query .= 'ORDER BY ';
			foreach ($this->order as $value) {
				$query .= $value['field'] . ' ' . $value['direction'] . ', ';
			}
			$query = substr($query, 0,  -2) . PHP_EOL;
		}
		if(!empty($this->limit)){
			$query .= 'LIMIT ' . $this->limit['start'] . ', ' . $this->limit['offset'] . PHP_EOL;
		}
		return array('query' => $query, 'values' => $values);
	}

	public function Query($query, $values = array()){
		if($query){
			$statement = $this->connection->prepare($query);
			if (!$statement) {
				return false;
			}
			if (!$statement->execute($values)) {
				return false;
			}
			return $statement;
		}else{
			return false;
		}
	}

	public function Exec($query = null, $values = null) {
		$sql = array();
		if(!empty($query)){
			if(is_array($query)){
				$sql = $query;
			}else{
				$sql['query'] = $query;
			}
			if(!empty($values) && is_array($values)){
				$sql['values'] = $values;
			}else{
				$sql['values'] = array();
			}
		}else{
			$sql = $this->getQuery();
		}
		$statement = $this->connection->prepare($sql['query']);
		if (!$statement) {
			return false;
		}
		if (!$statement->execute($sql['values'])) {
			return false;
		}

		$results = $statement->fetchAll(\PDO::FETCH_ASSOC);
		$this->flush();
		return $results;
	}

}