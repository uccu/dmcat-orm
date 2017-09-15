<?php
namespace Uccu\DmcatOrm\Database;

use Uccu\DmcatTool\Tool\E;
use Uccu\DmcatTool\Traits\InstanceTrait;
use PDO;
use PDOStatement;
use Uccu\DmcatTool\Tool\LocalConfig as Config;

class PdoMysql
{
	use InstanceTrait;

	public 	$prefix;
	public 	$database;
	private $_connection;

	private $_config;
	private $_results;
	private $_effectsNumber = 0;

	function __construct($name = null){

		$this->init_config($name);
		$this->connect();


	}
	

	# 连接数据库
	private function connect(){

		if(!$this->_config->DATABASE)E::throwEx('Database Not Selected');

		$this->_connection = new PDO(
			'mysql:dbname='.$this->_config->DATABASE.';host='.$this->_config->HOST.';charset='.$this->_config->CHARSET, 
			$this->_config->USER, 
			$this->_config->PASSWORD,
			[
				PDO::ATTR_PERSISTENT 	=> 	(bool)$this->_config->ATTR_PERSISTENT,
				PDO::ATTR_TIMEOUT 		=> 	$this->_config->ATTR_TIMEOUT,
				PDO::ATTR_AUTOCOMMIT 	=> 	$this->_config->ATTR_AUTOCOMMIT,
				PDO::ATTR_ERRMODE 		=>	(int)$this->_config->ATTR_ERRMODE,
				PDO::ATTR_CASE 			=>	(int)$this->_config->ATTR_CASE,
			]
		);

		!$this->_connection && E::throwEx('数据库连接失败');

		$auto = $this->_config->AUTOCOMMIT;
		$auto = is_null($auto) ? 1 : ( $auto ? 1 : 0);
		$this->_connection->setAttribute( PDO::ATTR_AUTOCOMMIT, $auto);

		
		

		return $this;

	}



	# 设置参数
	private function init_config($name){

		$this->_config = Config::pdoMysql();
		$this->prefix = $this->_config->PREFIX;
		$this->database = $this->_config->DATABASE;
		return $this;

	}

	# 设置results
	private function setResults(PDOStatement $results){

		$this->_results = $results;
		return $this;
	}

	# 执行sql语句并返回PDOStatement的实例
	function query(String $sql){

		$this->setResults($this->_connection->query($sql));
		return $this;
	}

	# 准备执行sql语句并返回PDOStatement的实例
	function prepare (String $sql){

		$this->serResults($this->_connection->prepare($sql));
		return $this;
	}

	# 执行sql、语句并返回影响的行数
	function exec(String $sql){

		$this->_effectsNumber = $this->_connection->exec($sql);
		return $this->_effectsNumber;
	}

	# 事务
	function start(){

		return $this->_connection->beginTransaction();
	}
	function commit(){

		return $this->_connection->commit();
	}
	function rollback(){

		return $this->_connection->rollBack();
	}
	function inTransaction(){

		return $this->_connection->inTransaction();
	}
	
	# 获取最后插入行的ID或序列值
	function insert_id(string $name = NULL ){

		return $this->_connection->lastInsertId($name);
	}

	# 获取sql影响的行数
	function affected_rows(){

		return $this->_effectsNumber = $this->_results->rowCount();
	}

	# 执行
	function execute(){

		return $this->_results->execute();
	}

	# 解析到对象
	function fetchObj(){

		$objs = $this->_results->fetchAll(PDO::FETCH_CLASS, 'stdClass');
		return $objs;

	}

	# 解析到数组
	function fetch_assoc(){

		$objs = $this->_results->fetchAll(PDO::FETCH_ASSOC);
		return $objs;
		
	}

	# 逐条解析到数组
	function fetch_array($resulttype = PDO::FETCH_ASSOC){

		if(!$this->_results)return false;
		return $this->_results->fetch($resulttype);
		
	}

	# 关闭游标，使语句能再次被执行
	function free_result(){
		if(!$this->_results)return false;
		return $this->_results->closeCursor();
	}



}