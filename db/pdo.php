<?php
/**
 * Phatso - A PHP Micro Framework
 * Copyright (C) 2008, Judd Vinet <jvinet@zeroflux.org>
 * 
 * Permission is hereby granted, free of charge, to any person
 * obtaining a copy of this software and associated documentation
 * files (the "Software"), to deal in the Software without
 * restriction, including without limitation the rights to use,
 * copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the
 * Software is furnished to do so, subject to the following
 * conditions:
 * 
 * (1) The above copyright notice and this permission notice shall be
 *     included in all copies or substantial portions of the Software.
 * (2) Except as contained in this notice, the name(s) of the above
 *     copyright holders shall not be used in advertising or otherwise
 *     to promote the sale, use or other dealings in this Software
 *     without prior written authorization.
 * 
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND,
 * EXPRESS OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES
 * OF MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE AND
 * NONINFRINGEMENT. IN NO EVENT SHALL THE AUTHORS OR COPYRIGHT
 * HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY,
 * WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING
 * FROM, OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR
 * OTHER DEALINGS IN THE SOFTWARE.
 *
 * Description: DB driver for PDO.
 * NOTE: Requires PHP5.
 *
 **/

class Database extends DB_Base
{
	function Database($conn, $persistent) {
		$this->safesql =& new SafeSQL_ANSI;
		
		$att = array(PDO::ATTR_PERSISTENT => $persistent);
		try {
			$this->conn = new PDO($conn['dsn'], $conn['user'], $conn['pass'], $att);
		} catch(PDOException $e) {
			die("Unable to connect to database: ".$e->getMessage());
		}
		$this->driver = $this->conn->getAttribute(PDO::ATTR_DRIVER_NAME);

		if($this->driver == 'mysql') {
			// Put MySQL into ANSI mode (or at least closer to it)
			//   (this requires MySQL 4.1 or later)
			$this->run_query("SET SESSION sql_mode='ANSI'");
		}
	}

	function _catch($msg="") {
		$this->error = $this->conn->errorInfo();
		if(!$this->error) return true;
		$this->error = $this->error[2];
		die($msg."<br />\n{$this->query}: {$this->error}");
	}

	function get_table_defn($table) {
		if($this->driver == 'mysql') {
			return $this->get_all_pair("EXPLAIN \"$table\"");
		}
		if($this->driver == 'sqlite') {
			$defn = array();
			$a = $this->get_all("PRAGMA table_info(\"$table\")");
			foreach($a as $v) $defn[$v['name']] = $v['type'];
			return $defn;
		}

		$defn = array();
		// FIXME: this only works if there's already a row in the table
		$q = $this->run_query("SELECT * FROM \"$table\" LIMIT 1");
		for($i = 0; $i < $this->num_fields($q); $i++) {
			$col = $q->getColumnMeta($i);
			$defn[$col['name']] = $col['native_type'];
		}
		return $defn;
	}

	function run_query($sql) {
		return $this->conn->query($sql);
	}

	function fetch_row(&$q) {
		return $q->fetch(PDO::FETCH_NUM);
	}

	function fetch_array(&$q) {
		return $q->fetch(PDO::FETCH_ASSOC);
	}

	function fetch_field(&$q, $num) {
		return $q->fetchColumn($num);
	}

	function num_fields(&$q) {
		return $q->columnCount();
	}

	function num_rows(&$q) {
		return $q->rowCount();
	}

	function get_insert_id() {
		return $this->conn->lastInsertId();
	}
}

/**
 * Description: Base DB interface
 *
 **/
require_once("safesql.php");

class DB_Base
{
	var $conn;
	var $query;
	var $query_str;
	var $query_arg;
	var $result;
	var $error;
	var $insert_id;

	/**
	 * Generate a query string by substituting placeholders (eg, %i) with their
	 * real values
	 *
	 * @param string $query_str Query string
	 * @param array $query_arg Associative array of variables to substitute in
	 * @param bool $bypass If set, bypass variable substitution
	 * @return string The resulting SQL string
	 */
	function &query($query_str, $query_arg="", $bypass=false) {
		if($bypass == true) return($query_str);
		return $this->safesql->query($query_str, $query_arg);
	}

	/**
	 * Execute the query, first calling Query() to finalize the query string
	 *
	 * @param string $query_str Query string
	 * @param array $query_arg Associative array of variables to substitute in
	 * @param bool $bypass If set, bypass variable substitution
	 * @return mixed The result identifier
	 */
	function &execute($query_str, $query_arg="", $bypass=false) {
		$this->query = $bypass ? $query_str : $this->query($query_str, $query_arg);
		$this->result = $this->run_query($this->query) or $this->_catch();
		$this->insert_id = $this->get_insert_id();
		return $this->result;
	}
	
	/**
	 * Return a single row from a query string, false if not found
	 *
	 * @param string $query_str Query string
	 * @param array $query_arg Query arguments
	 * @return mixed
	 */
	function &get_item($query_str, $query_arg="") {
		if(($item = $this->fetch_array($this->execute($query_str, $query_arg)))) {
			return $item;
		} else {
			$ret = false;
			return $ret;
		}
	}

	/**
	 * Return a single row from a query string using PK for lookup, false if not found
	 *
	 * @param string $table
	 * @param string $pk Value of ID/PK field
	 * @param string $pk_col Name of PK column in this table
	 * @return mixed
	 */
	function get_item_by_pk($table, $pk, $pk_col='id') {
		return $this->get_item("SELECT * FROM $table WHERE \"$pk_col\"=%i LIMIT 1", array($pk));
	}

	/**
	 * Return all rows from a query
	 *
	 * @param string $query_str Query string
	 * @param array $query_arg Query arguments
	 * @param string $key If set, return only this column instead of the entire row
	 * @return mixed
	 */
	function &get_all($query_str, $query_arg="", $key="") {
		$result = $this->execute($query_str, $query_arg);
		$list = array();
		while($row = @$this->fetch_array($result)) {
			if(empty($key)) {
				$list[] = $row;
			} else {
				$list[$row[$key]] = $row;
			}
		}
		return $list;
	}

	/**
	 * Return a single value from a query string, false if not found
	 *
	 * @param string $query_str Query string
	 * @param array $query_arg Query arguments
	 * @param string $value If non-empty, use this value from the resulting row,
	 *                      otherwise use the first one
	 * @return mixed
	 */
	function &get_value($query_str, $query_arg="", $value="") {
		if(empty($value)) {
			if($item = $this->fetch_row($this->execute($query_str, $query_arg))) { 
				return $item[0];
			} else {
				$ret = false;
				return $ret;
			}
		} else {
			if($item = $this->fetch_array($this->execute($query_str, $query_arg))) { 
				return $item[$value];
			} else {
				$ret = false;
				return $ret;
			}
		}
	}

	/**
	 * Return an array of value from a query string
	 *
	 * @param string $query_str Query string
	 * @param array $query_arg Query arguments
	 * @param string $value If non-empty, use this value from the resulting row,
	 *                      otherwise use the first one
	 * @return array
	 */
	function &get_values($query_str, $query_arg="", $value="") {
		$result = $this->execute($query_str, $query_arg);
		$values = array();
		if(empty($value)) {
			while($item = $this->fetch_row($result)) {
				$values[] = $item[0];
			}
		} else {
			while($item = $this->fetch_array($result)) {
				$values[] = $item[$value];
			}
		}
		return $values;
	}

	/**
	 * Return an associate array of a value from a query string, false if
	 * not found.
	 *
	 * @param string $query_str Query string
	 * @param array $query_arg Query arguments
	 * @param array $pair If set, uses the first element as the key and the
	 *                    second as the value.  Uses the first two values if
	 *                    not set.
	 * @return mixed
	 */
	function &get_item_pair($query_str, $query_arg="", $pair="") {
		if($item = $this->fetch_array($this->execute($query_str, $query_arg))) {
			if(!empty($pair) && is_array($pair)) {
				$key = next($pair);
				$value = next($pair);
				return array($item[$key] => $item[$value]);
			} else {
				return array($item[0] => $item[1]);
			}
		} else {
			$ret = false;
			return $ret;
		}
	}

	/**
	 * Return an associate array of a values from a query string, false if
	 * not found.
	 *
	 * @param string $query_str Query string
	 * @param array $query_arg Query arguments
	 * @param array $pair If set, uses the first element as the key and the
	 *                    second as the value.  Uses the first two values if
	 *                    not set.
	 * @return mixed
	 */
	function &get_all_pair($query_str, $query_arg="", $pair="") {
		$result = $this->execute($query_str, $query_arg);
		$list = array();
		
		if(!empty($pair) && is_array($pair)) {
			$key = current($pair);
			$value = next($pair);
			while($row = @$this->fetch_array($result)) {
				$list[$row[$key]] = $row[$value];
			}
		} else {
			while($row = @$this->fetch_row($result)) $list[$row[0]] = $row[1];
		}
		return $list;
	}

	/**
	 * Insert multiple records.
	 *
	 * @param string $table
	 * @param array $data
	 * @param mixed $aFields
	 */
	function insert_all($table, $data, $aFields="", $mode='insert') {
		if(!$data) return;
		$fields = array();
		$values = array();
		$arg = array();
		foreach($data as $k=>$row) {
			$val = array();
			if(is_array($aFields)) {
				$new_row = array();
				foreach($aFields as $f=>$v) {
					$new_row[$f] = isset($row[$f]) ? $row[$f] : $v['value'];
				}
				$row = $new_row;
			}
			foreach($row as $f=>$v) {
				if(!$k) $fields[] = "\"$f\"";
				if(is_array($v)) {
					$val[] = $v[0];
				} else {
					$val[] = "'%s'";
					$arg[] = $v;
				}
			}
			$values[] = "(".join(",", $val).")";
		}
		$this->execute("$mode INTO ".$table." (".join(",",$fields).") VALUES ".join(",",$values), $arg);
		return $this->insert_id;
	}

	/**
	 * Replace multiple records.
	 *
	 * @param string $table
	 * @param array $data
	 * @param mixed $aFields
	 */
	function replace_all($table, $data, $aFields="") {
		return $this->insert_all($table, $data, $aFields, 'replace');
	}

	/**
	 * Update multiple records.
	 *
	 * @param string $table
	 * @param array $data
	 * @param string $where
	 * @param string $where_arg
	 * @param mixed $aUpdateFields
	 */
	function update_all($table, $data, $where="", $where_arg="", $aUpdateFields="") {
		if(!$data) return;
		if(!$where_arg) $where_arg = array();
		if($where) $aWhere[] = $where;
		if($aUpdateFields) {
			foreach($aUpdateFields as $f) $aWhere[] = $f."='%s'";
		}
		if($aWhere) $sWhere = " WHERE (".join(") AND (", $aWhere).")";
		foreach($data as $k=>$row) {
			$aFields = array();
			$aArg = array();
			foreach($row as $f=>$v) {
				if(!is_numeric($f)) {
					if(is_array($v)) {
						$val = $v[0];
					} else {
						$val = "'%s'";
						$aArg[] = $v;
					}
				}
				$aFields[] = "\"$f\"=$val";
			}
			$aArg = array_merge($aArg, $where_arg);
			if($aUpdateFields) {
				foreach($aUpdateFields as $f) {
					$aArg[] = $row[$f];
				}
			}
			$this->execute("UPDATE ".$table." SET ".join(",",$aFields).$sWhere, $aArg);
		}
	}

	/**
	 * Insert a record
	 *
	 * @param string $sTable
	 * @param array $aRow
	 * @param string $mode Set to "replace" to use REPLACE INTO instead of INSERT INTO
	 * @return int Insert ID of new row
	 */
	function insert_row($sTable, $aRow, $mode='insert') {
		$mode = $mode == 'replace' ? 'REPLACE' : 'INSERT';
		$fs = $this->get_table_defn($sTable);

		$aFields = array();
		$aValues = array();
		$aArgs = array();
		foreach($aRow as $k=>$v) {
			// if this RDBMS does not support self introspection (ie, examining the
			// table schema) then we can't know which fields in this array map to
			// actual column names.
			if(!$fs || isset($fs[$k])) {
				$aFields[] = "\"$k\"";
				$aValues[] = "'%s'";
				$aArgs[] = $v;
			}
		}
		$this->execute("$mode INTO ".$sTable." (".join(",",$aFields).") VALUES (".join(",",$aValues).")", $aArgs);

		return $this->insert_id;
	}

	/**
	 * Replace a record (REPLACE INTO)
	 *
	 * @param string $sTable
	 * @param array $aRow
	 * @return int Insert ID of new row
	 */
	function replace_row($sTable, $aRow) {
		return $this->insert_row($sTable, $aRow, 'replace');
	}

	/**
	 * Update a record
	 *
	 * @param string $sTable
	 * @param array $aRow
	 * @param string $sWhere WHERE clause to use for update
	 * @param array $aWhereArgs Arguments to substitute into WHERE clause
	 */
	function update_row($sTable, $aRow, $sWhere, $aWhereArgs=array()) {
		$fs = $this->get_table_defn($sTable);

		$aFields = array();
		$aArgs = array();
		foreach($aRow as $k=>$v) {
			// if this RDBMS does not support self introspection (ie, examining the
			// table schema) then we can't know which fields in this array map to
			// actual column names.
			if(!$fs || isset($fs[$k])) {
				$aFields[] = "\"$k\"='%s'";
				$aArgs[] = $v;
			}
		}
		if(is_array($aWhereArgs) && !empty($aWhereArgs)) {
			foreach ($aWhereArgs as $v) $aArgs[] = $v;
		}
		$this->execute("UPDATE \"".$sTable."\" SET ".join(",", $aFields)." WHERE ".$sWhere, $aArgs);
	}

	/**
	 * Insert or Update a record
	 *
	 * @param string $sTable
	 * @param array $aRow
	 * @param array $aKey Array of key/value pairs.  If matching record is found,
	 *                    then an update_row() is called, otherwise insert_row()
	 *                    is called.
	 */
	function insert_update_row($sTable, $aRow, $aKey) {
		$aKeyWhere = array();
		$aKeyArgs = array();
		foreach($aKey as $k=>$v) {
			$aKeyWhere[] = "\"$k\"='%s'";
			$aKeyArgs[] = $v;
		}
		$sKeyWhere = join(' AND ', $aKeyWhere);

		if($this->get_item('SELECT * FROM '.$sTable.' WHERE ('.$sKeyWhere.')', $aKeyArgs)) {
			$this->update_row($sTable, $aRow, $sKeyWhere, $aKeyArgs);
		} else {
			$this->insert_row($sTable, array_merge($aKey, $aRow));
		}
	}

	/**
	 * Increment a column within a row specified by the key column(s).  If
	 * the row does not exist, create it, setting the counter to 1.  Useful
	 * for updating statistical counters.
	 *
	 * @param string $sTable
	 * @param string $sField Name of the column that will be incremented or inserted.
	 * @param array $aKey Array of key/value pairs.  If matching record is found,
	 *                    then $sField will be incremented by one.  Otherwise, a row
	 *                    will be inserted and $sField will be set to one.
	 */
	function increment_row($sTable, $sField, $aKey) {
		$aKeyWhere = array();
		$aKeyArgs = array();
		foreach($aKey as $k=>$v) {
			$aKeyWhere[] = "\"$k\"='%s'";
			$aKeyArgs[] = $v;
		}
		$sKeyWhere = join(' AND ', $aKeyWhere);

		if($this->get_item('SELECT * FROM '.$sTable.' WHERE ('.$sKeyWhere.')', $aKeyArgs)) {
			$this->execute("UPDATE $sTable SET $sField=$sField+1 WHERE ($sKeyWhere)", $aKeyArgs);
		} else {
			$this->insert_row($sTable, array_merge($aKey, array($sField=>'1')));
		}
	}
}

?>
