<?php
/**
 * @package bbn\db
 */
namespace bbn\db;

use \bbn\str\text;
/**
 * Database Class
 *
 *
 * @author Thomas Nabet <thomas.nabet@gmail.com>
 * @copyright BBN Solutions
 * @since Apr 4, 2011, 23:23:55 +0000
 * @category  Database
 * @license   http://www.opensource.org/licenses/lgpl-license.php LGPL
 * @version 0.2r89
 */
class sqlite extends connection implements actions, api, engines
{

	/**
	 * @return void 
	 */
	public function __construct($cfg=array())
	{
		if ( isset($cfg['db']) )
		{
			$cfg['engine'] = isset($cfg['engine']) ? $cfg['engine'] : 'mysql';
			$cfg['host'] = isset($cfg['host']) ? $cfg['host'] : 'localhost';
		}
		else if ( isset($cfg['db'],$cfg['engine']) && $cfg['engine'] === 'sqlite' && strpos($cfg['db'],'/') === false ){
			if ( strpos($cfg['db'],'.') === false ){
				$cfg['db'] .= '.sqlite';
			}
			$cfg = array(
			'host' => '',
			'user' => '',
			'pass' => '',
			'db' => $cfg['db'],
			'engine' => 'sqlite'
			);
		}
		else if ( defined('BBN_DB_HOST') ){
			$cfg = array(
			'host' => BBN_DB_HOST,
			'user' => BBN_DB_USER,
			'pass' => BBN_DB_PASS,
			'db' => BBN_DATABASE,
			'engine' => BBN_DB_ENGINE
			);
		}
		if ( isset($cfg['host'],$cfg['user'],$cfg['pass'],$cfg['db'],$cfg['engine']) &&
		strpos($cfg['db'],'/') === false )
		{
			switch ( $cfg['engine'] )
			{
				case 'mysql':
					$params = array(
						\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
						\PDO::MYSQL_ATTR_USE_BUFFERED_QUERY => true,
						\PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8"
					);
					break;
				case 'mssql':
				case 'oracle':
				case 'postgre':
				case 'sqlite':
					$params = array();
					break;
			}
			if ( self::setTimeout() )
				$params[\PDO::ATTR_TIMEOUT] = self::$timeout;
			try
			{
				switch ( $cfg['engine'] )
				{
					case 'mysql':
						parent::__construct('mysql:host='.$cfg['host'].';dbname='.$cfg['db'], $cfg['user'], $cfg['pass'],$params);
						$this->host = $cfg['host'];
						break;
					case 'sqlite';
						if ( defined('BBN_DATA_PATH') && isset($cfg['db']) && is_file(BBN_DATA_PATH.'db/'.$cfg['db']) ){
							parent::__construct('sqlite:'.BBN_DATA_PATH.'db/'.$cfg['db']);
							$this->host = 'localhost';
						}
						else if ( is_file($cfg['db']) ){
							parent::__construct('sqlite:'.$cfg['db']);
							$this->host = 'localhost';
						}
						break;
				}
				if ( $this->host ){
					$this->current = $cfg['db'];
					$this->engine = $cfg['engine'];
				}
			}
			catch (\PDOException $e)
				{ self::error($e,"Connection"); }
		}
	}
	
	/**
	 * @return string | false
	 */
	public function change($db)
	{
		if ( $this->current !== $db && text::check_name($db) ){
			$this->query("USE $db");
			$this->current = $db;
		}
		return $this;
	}
	
	/**
	 * @return string | false
	 */
	public function get_full_name($table, $escaped=false)
	{
		$table = str_replace("`","",$table);
		$table = explode(".",$table);
		if ( count($table) === 2 ){
			$db = trim($table[0]);
			$table = trim($table[1]);
		}
		else{
			$db = $this->current;
			$table = trim($table[0]);
		}
		if ( text::check_name($db,$table) ){
			return $escaped ? "`".$db."`.`".$table."`" : $db.".".$table;
		}
		return false;
	}
	
	/**
	 * @return array | false
	 */
	public function get_databases()
	{
		$x = array_filter($this->get_rows("SHOW DATABASES"),function($a){
			return ( $a['Database'] === 'information_schema' ) || ( $a['Database'] === 'mysql' ) ? false : 1;
		});
		sort($x);
		return $x;
	}

	/**
	 * @return array | false
	 */
	public function get_tables($database='')
	{
		$arch = array();
		if ( empty($database) || !text::check_name($database) ){
			$database = $this->current;
		}
		$t2 = array();
		if ( $t1 = $this->get_irows("SHOW TABLES FROM `$database`") ){
			foreach ( $t1 as $t ){
				array_push($t2, $t[0]);
			}
		}
		return $t2;
	}

	/**
	 * @return array | false
	 */
	public function get_columns($table)
	{
		//var_dump("I get the fields");
		if ( $table = $this->get_full_name($table, 1) ){
			$rows = $this->get_rows("SHOW COLUMNS FROM $table");
			$p = 1;
			foreach ( $rows as $row ){
				$f = $row['Field'];
				$r[$f] = array(
					'position' => $p++,
					'null' => $row['Null'] === 'NO' ? 0 : 1,
					'key' => in_array($row['Key'], array('PRI', 'UNI', 'MUL')) ? $row['Key'] : null,
					'default' => is_null($row['Default']) && $row['Null'] !== 'NO' ? 'NULL' : $row['Default'],
					'extra' => $row['Extra'],
					'maxlength' => 0
				);
				if ( strpos($row['Type'], 'enum') === 0 ){
					$r[$f]['type'] = 'enum';
					if ( preg_match_all('/\((.*?)\)/', $row['Type'], $matches) ){
						$r[$f]['extra'] = $matches[1][0];
					}
				}
				else{
					if ( strpos($row['Type'], 'unsigned') ){
						$r[$f]['signed'] = 0;
						$row['Type'] = trim(str_replace('unsigned','',$row['Type']));
					}
					else{
						$r[$f]['signed'] = 1;
					}
					if ( strpos($row['Type'],'text') !== false ){
						$r[$f]['type'] = 'text';
					}
					else if ( strpos($row['Type'],'blob') !== false ){
						$r[$f]['type'] = 'blob';
					}
					else if ( strpos($row['Type'],'int(') !== false ){
						$r[$f]['type'] = 'int';
					}
					else if ( strpos($row['Type'],'char(') !== false ){
						$r[$f]['type'] = 'varchar';
					}
					if ( preg_match_all('/\((.*?)\)/', $row['Type'], $matches) ){
						$r[$f]['maxlength'] = $matches[1][0];
					}
					if ( !isset($r[$f]['type']) ){
						$r[$f]['type'] = ( strpos($row['Type'], '(') ) ? substr($row['Type'],0,strpos($row['Type'], '(')) : $row['Type'];
					}
					
				}
				
			}
		}
		return $r;
	}
	
	/**
	 * @return string
	 */
	public function get_keys($table)
	{
		//var_dump("I get the keys");
		if ( $full = $this->get_full_name($table, 1) ){
			$t = explode(".", $table);
			$db = $t[0];
			$table = $t[1];
			$b = $this->get_rows("SHOW INDEX FROM `$db`.`$table`");
			$keys = array();
			$cols = array();
			foreach ( $b as $i => $d ){
				$a = $this->get_row("
				SELECT `ORDINAL_POSITION` as `position`,
				`REFERENCED_TABLE_SCHEMA` as `ref_db`, `REFERENCED_TABLE_NAME` as `ref_table`, `REFERENCED_COLUMN_NAME` as `ref_column`
				FROM `information_schema`.`KEY_COLUMN_USAGE`
				WHERE `TABLE_SCHEMA` LIKE ?
				AND `TABLE_NAME` LIKE ?
				AND `COLUMN_NAME` LIKE ?
				AND ( `CONSTRAINT_NAME` LIKE ? OR ORDINAL_POSITION = ? OR 1 )
				LIMIT 1",
				$db,
				$table,
				$d['Column_name'],
				$d['Key_name'],
				$d['Seq_in_index']);
				if ( !isset($keys[$d['Key_name']]) ){
					$keys[$d['Key_name']] = array(
					'columns' => array($d['Column_name']),
					'ref_db' => $a ? $a['ref_db'] : null,
					'ref_table' => $a ? $a['ref_table'] : null,
					'ref_column' => $a ? $a['ref_column'] : null,
					'unique' => $d['Non_unique'] == 0 ? 1 : 0
					);
				}
				else{
					array_push($keys[$d['Key_name']]['columns'], $d['Column_name']);
				}
				if ( !isset($cols[$d['Column_name']]) ){
					$cols[$d['Column_name']] = array($d['Key_name']);
				}
				else{
					array_push($cols[$d['Column_name']], $d['Key_name']);
				}
			}
			return array('keys'=>$keys, 'cols'=>$cols);
		}
	}
	
	/**
	 * @return string | false
	 */
	public function get_create($table)
	{
		if ( ( $table = $this->get_full_name($table, 1) ) && $r = $this->get_row("SHOW CREATE TABLE $table") ){
			return $r['Create Table'];
		}
		return false;
	}
	
	/**
	 * @return string | false
	 */
	public function get_delete($table, array $where)
	{
		if ( ( $table = $this->get_full_name($table, 1) ) && ( $m = $this->modelize($table) ) && count($m['fields']) > 0 && count($where) > 0 ){
			$r = "DELETE FROM $table WHERE 1 ";

			foreach ( $where as $f ){
				if ( !isset($m['fields'][$f]) ){
					die("The fields to search for in get_delete don't correspond to the table");
				}
				$r .= "\nAND `$f` ";
				if ( stripos($m['fields'][$f]['type'],'int') !== false ){
					$r .= "= %u ";
				}
				else{
					$r .= "= %s ";
				}
			}
			return $r;
		}
		return false;
	}

	/**
	 * @return string
	 */
	public function get_select($table, array $fields = array(), array $where = array(), $order = array(), $limit = false, $start = 0, $php = false)
	{
		if ( ( $table = $this->get_full_name($table, 1) )  && ( $m = $this->modelize($table) ) && count($m['fields']) > 0 )
		{
			$r = '';
			if ( $php ){
				$r .= '$db->query("';
			}
			$r .= "SELECT \n";
			if ( count($fields) > 0 ){
				foreach ( $fields as $k ){
					if ( !isset($m['fields'][$k]) ){
						die("The column $k doesn't exist in $table");
					}
					else{
						$r .= "`$k`,\n";
					}
				}
			}
			else{
				foreach ( array_keys($m['fields']) as $k ){
					$r .= "`$k`,\n";
				}
			}
			$r = substr($r,0,strrpos($r,','))."\nFROM $table";
			if ( count($where) > 0 ){
				$r .= "\nWHERE 1 ";
				foreach ( $where as $f ){
					if ( !isset($m['fields'][$f]) ){
						die("The field $f to search for in get_select don't correspond to the table");
					}
					$r .= "\nAND `$f` ";
					if ( stripos($m['fields'][$f]['type'],'int') !== false ){
						$r .= "= %u";
					}
					else{
						$r .= "= %s";
					}
				}
			}
			$directions = ['desc', 'asc'];
			if ( is_string($order) ){
				$order = [$order];
			}
			if ( is_array($order) && count($order) > 0 ){
				$r .= "\nORDER BY ";
				foreach ( $order as $col => $direction ){
					if ( is_numeric($col) && isset($m['fields'][$direction]) ){
						$r .= "`$direction` ".( stripos($m['fields'][$direction]['type'],'date') !== false ? 'DESC' : 'ASC' ).",\n";
					}
					else if ( isset($m['fields'][$col])  ){
						$r .= "`$col` ".( strtolower($direction) === 'desc' ? 'DESC' : 'ASC' ).",\n";
					}
				}
				$r = substr($r,0,strrpos($r,','));
			}
			if ( $limit && is_numeric($limit) && is_numeric($start) ){
				$r .= "\nLIMIT $start, $limit";
			}
			if ( $php ){
				$r .= '")';
			}
			return $r;
		}
		return false;
	}
	
	/**
	 * @return string
	 */
	public function get_insert($table, array $fields = array(), $ignore = false, $php = false)
	{
		$r = '';
		if ( $php ){
			$r .= '$db->query("';
		}
		if ( ( $table = $this->get_full_name($table, 1) )  && ( $m = $this->modelize($table) ) && count($m['fields']) > 0 )
		{
			$r .= "INSERT ";
			if ( $ignore ){
				$r .= "IGNORE ";
			}
			$r .= "INTO $table (\n";
			$i = 0;
			
			if ( count($fields) > 0 ){
				foreach ( $fields as $k ){
					if ( !isset($m['fields'][$k]) ){
						die("The column $k doesn't exist in $table");
					}
					else{
						$r .= "`$k`, ";
						$i++;
						if ( $i % 4 === 0 ){
							$r .= "\n";
						}
					}
				}
			}
			else{
				foreach ( array_keys($m['fields']) as $k ){
					$r .= "`$k`, ";
					$i++;
					if ( $i % 4 === 0 ){
						$r .= "\n";
					}
				}
			}
			$r = substr($r,0,strrpos($r,',')).")\nVALUES (\n";
			$i = 0;
			if ( count($fields) > 0 ){
				foreach ( $fields as $k ){
					if ( stripos($m['fields'][$k]['type'],'INT') !== false ){
						$r .= "%u, ";
					}
					else{
						$r .= "%s, ";
					}
					$i++;
					if ( $i % 4 === 0 ){
						$r .= "\n";
					}
				}
			}
			else{
				foreach ( $m['fields'] as $k => $f ){
					if ( stripos($f['type'],'INT') !== false ){
						$r .= "%u, ";
					}
					else{
						$r .= "%s, ";
					}
					$i++;
					if ( $i % 4 === 0 ){
						$r .= "\n";
					}
				}
			}
			$r = substr($r,0,strrpos($r,',')).')';
			if ( $php ){
				$r .= "\",\n";
				$i = 0;
				foreach ( array_keys($m['fields']) as $k ){
					$r .= "\$d['$k'], ";
					$i++;
					if ( $i % 4 === 0 ){
						$r .= "\n";
					}
				}
				$r = substr($r,0,strrpos($r,',')).');';
			}
			return $r;
		}
		return false;
	}
	
	/**
	 * @return string
	 */
	public function get_update($table, array $fields = array(), array $where = array(), $php = false)
	{
		$r = '';
		if ( $php ){
			$r .= '$db->query("';
		}
		if ( ( $table = $this->get_full_name($table, 1) ) && ( $m = $this->modelize($table) ) && count($m['fields']) > 0 )
		{
			if ( is_string($where) ){
				$where = array($where);
			}
			$r .= "UPDATE $table SET ";
			$i = 0;

			if ( count($fields) > 0 ){
				foreach ( $fields as $k ){
					if ( !isset($m['fields'][$k]) ){
						die("The column $k doesn't exist in $table");
					}
					else{
						$r .= "`$k` = ";
						if ( stripos($m['fields'][$k]['type'],'int') !== false ){
							$r .= "%u";
						}
						else{
							$r .= "%s";
						}
						$r .= ",\n";
					}
				}
			}
			else{
				foreach ( array_keys($m['fields']) as $k ){
					$r .= "`$k` = ";
					if ( stripos($m['fields'][$k]['type'],'int') !== false ){
						$r .= "%u";
					}
					else{
						$r .= "%s";
					}
					$r .= ",\n";
				}
			}

			$r = substr($r,0,strrpos($r,','))."\nWHERE 1 ";
			foreach ( $where as $f ){
				if ( !isset($m['fields'][$f]) ){
					die("The fields to search for in get_update don't correspond to the table");
				}
				$r .= "\nAND `$f` ";
				if ( stripos($m['fields'][$f]['type'],'int') !== false ){
					$r .= "= %u ";
				}
				else{
					$r .= "= %s ";
				}
			}

			if ( $php ){
				$r .= "\",\n";
				$i = 0;
				foreach ( array_keys($m['fields']) as $k ){
					if ( !in_array($k, $where) && ( count($fields) === 0 || in_array($k,$fields) ) ){
						$r .= "\$d['$k'],\n";
					}
				}
				foreach ( $where as $f ){
					$r .= "\$d['$f'],\n";
				}
				$r = substr($r,0,strrpos($r,',')).');';
			}
			return $r;
		}
		return false;
	}
	
	/**
	 * @return void 
	 */
	public function create_db_index($table, $column, $sys='mysql')
	{
		if ( ( $table = $this->get_full_name($table, 1) ) && text::check_name($column) ){
			$this->query("
			ALTER TABLE $table
			ADD INDEX `$column`");
		}
		return $this;
	}
	
	/**
	 * @return void 
	 */
	public function delete_db_index($table, $column, $sys='mysql')
	{
		if ( ( $table = $this->get_full_name($table, 1) ) && text::check_name($column) ){
			$this->query("
				ALTER TABLE $table
				DROP INDEX `$column`");
		}
		return $this;
	}
	
	/**
	 * @return void 
	 */
	public function create_db_user($user, $pass, $db, $sys='mysql', $host='localhost')
	{
		if ( text::check_name($user, $db) && strpos($pass, "'") === false ){
			$this->query("
				GRANT SELECT,INSERT,UPDATE,DELETE,CREATE,DROP,INDEX,ALTER
				ON `$db` . *
				TO '$user'@'$host'
				IDENTIFIED BY '$pass'");
		}
	}
	
	/**
	 * @return void 
	 */
	public function delete_db_user($user, $sys='mysql')
	{
		if ( text::check_name($user) ){
			$this->query("
			REVOKE ALL PRIVILEGES ON *.* 
			FROM $user");
			$this->query("DROP USER $user");
		}
		return $this;
	}
	
}
?>