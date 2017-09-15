<?php

namespace Uccu\DmcatOrm;
use Uccu\DmcatTool\Tool\LocalConfig as Config;
use Uccu\DmcatTool\Tool\E;
use Uccu\DmcatTool\Traits\InstanceTrait;

class Tool{

    use InstanceTrait;

    # 缓存sql，重复利用查询
    private $sqls = [];
    
    public $columns = [];

    # 初始化
    function __construct(){
        # 获取数据库类型
        $this->database = Config::get('DATABASE');
        # 输出是否过滤null
        $model_null     = Config::get('MODEL_NULL');
        $this->model_null = is_null($model_null) || $model_null ? true :false;

    }

    # 获取数据模型（用到才会连接）
    function __get($name){

        if($name=='mb'){

            $mod = 'Uccu\DmcatOrm\Database\\'.$this->database;

            return $this->mb = $mod::getSingleInstance();
        }elseif($name == 'tables'){
            return $this->tables = $this->fetch_all('SHOW TABLES','Tables_in_'.$this->mb->database);
        }
        return null;

    }
    
    # 事务三连
    function commit(){
		return $this->mb->commit();
	}
	function rollback(){
		return $this->mb->rollback();
	}
    function start(){
		return $this->mb->start();
    }
    
    # insert,replace,update,delete
    function query($sql){

        $ret = $this->mb->query($sql);
        
        if ($ret) {

            $cmd = trim(strtoupper(substr($sql, 0, strpos($sql, ' '))));

            if ($cmd === 'UPDATE' || $cmd === 'DELETE') {

                $ret = $this->mb->affected_rows();

            } elseif ($cmd === 'INSERT' || $cmd === 'REPLACE') {

                $ret = $this->mb->insert_id();

            }

        }

        return $ret;


    }

    /** select查询
     * fetch_all
     * @param mixed $sql 语句
     * @param mixed $keyfield 根据哪个字段排序
     * @param mixed $key 键使用keyfield
     * @param mixed $sort 数组归类
     * @return mixed 
     */
    function fetch_all($sql, $keyfield = '',$key = 0,$sort = 0) {

        # 使用重复利用查询sql
        $key = (int)$key;
        $sort = (int)$sort;
        if(isset($this->sqls[$sql.$keyfield.$key.$sort]))return $this->sqls[$sql.$keyfield.$key.$sort];
        
		$data = [];

		$this->query($sql);

		while ($row = $this->mb->fetch_array()) {

            if(!$this->model_null)foreach($row as &$v)if(is_null($v))$v = '';
            if($keyfield && isset($row[$keyfield])){
                if($key){
                    if($sort){
                        $data[$row[$keyfield]][] = $row;
                    }else{
                        $data[$row[$keyfield]] = $row;
                    }
                }else{
                    $data[] = $row[$keyfield];
                }
            }else{
                $data[] = $row;
            }
            
		}

		$this->mb->free_result();
        $this->sqls[$sql.$keyfield.$key.$sort] = $data;

		return $data;

    }
    # 表名
    function quote_table($tableName){
        
        !is_string($tableName) && E::throwEx('Undefined Table\'s Name');
        $tableName = $this->mb->prefix.$tableName;
		$str = $this->quote_field($tableName);
		
		return $str;
		
    }
    # 过滤字段名
    function quote_field($field){
		
		if(!is_string($field))E::throwEx('Undefined Field\'s Name');
        $fields = explode('.',$field);
        foreach($fields as &$v)$v  = '`' . str_replace('`', '', $v) . '`';
        $field = implode('.',$fields);
		return $field;

    }
    # 过滤字段值
    function quote($str = NULL){
		
        if (is_string($str))return '\'' . addcslashes($str, "\n\r\\'\"\032") . '\'';
		elseif (is_int($str) or is_float($str))return  $str ;
		elseif (is_array($str)) {

			foreach ($str as &$v)$v = $this->quote($v);
			return $str;

		}elseif (is_bool($str))return $str ? '1' : '0';
        elseif (is_null($str))return 'NULL';
		else return '\'\'';

	}
    function format($hql = '', $arg = array() ,Model $model ,$checkField = true) {

        $sql = preg_replace_callback('#([ =\-,\+\(]|^)([a-z\*][a-zA-Z0-9_\.]*)#',function($m) use ($model){
            if(substr_count($m[2],'.')==0 && $checkField && !$model->hasField($m[2]))return $m[0];
            $field = new Field($m[2],$model,$checkField);
            return $m[1].$field->fullName;
        },$hql);
		$count = substr_count($sql, '%');

		if (!$count) {
			return $sql;
		} elseif ($count > count($arg)) {
			E::throwEx('Sql Needs '.$count.' Args' );
		}

		$len = strlen($sql);
		$i = $find = 0;
		$ret = '';
		while ($i <= $len && $find < $count) {
			if ($sql{$i} == '%') {
				$next = $sql{$i + 1};

                switch($next){
                    case 'F':
                        $field = new Field($arg[$find],$model);
                        $ret .= $field->fullName;
                        break;
                    case 'N':
                        $field = new Field($arg[$find],$model);
                        $ret .= $field->name;
                        break;
                    case 's':
                        $ret .= $this->quote(serialize($arg[$find]));
                        break;
                     case 'j':
                        $ret .= $this->quote(json_encode($arg[$find]));
                        break;
                    case 'f':
                        $ret .= sprintf('%F', $arg[$find]);
                        break;
                    case 'd':
                        $ret .= floor($arg[$find]);
                        break;
                    case 'i':
                        $ret .= $arg[$find];
                        break;
                    case 'b':
                        $ret .= $this->quote(base64_encode($arg[$find]));
                        break; 
                    case 'c':
                        $ret .= implode(',',$this->quote($arg[$find]));
                        break;
                    case 'a':
                        $ret .= implode(' AND ',$thisl->quote($arg[$find]));
                        break;
                    default:
                        $ret .= $this->quote($arg[$find]);
                        break;

                }

                $i++;
				$find++;
				
			} else {
				$ret .= $sql{$i};
			}
			$i++;
		}
		if ($i < $len) {
			$ret .= substr($sql, $i);
		}
		return $ret;
	}

    # 获取表的字段
    function columns($table){
        
        if(isset($this->columns[$table]))return $this->columns[$table];
        return $this->columns[$table] = $this->fetch_all('SHOW FULL COLUMNS FROM '.$table);
    }



}