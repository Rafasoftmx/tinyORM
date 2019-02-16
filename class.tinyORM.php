<?php
include_once "class.pdoDB.php"; //https://github.com/Rafasoftmx/pdoDB
include_once "class.fileDirHandler.php"; //https://github.com/Rafasoftmx/fileDirHandler

//cache system phpfastcache https://www.phpfastcache.com/
//for save some data about tables and objects and aboid to consult every time
//--------------------------------
require_once 'phpfastcache/lib/Phpfastcache/Autoload/autoload.php';
use Phpfastcache\CacheManager;
use Phpfastcache\Config\ConfigurationOption;
//set up system cache to save data about the classes and tables
CacheManager::setDefaultConfig(new ConfigurationOption(['path' => 'cache/']));
$cacheManager = CacheManager::getInstance('files');
//--------------------------------



/*
  _   _              ____  _____  __  __                       
 | | (_)            / __ \|  __ \|  \/  |                      
 | |_ _ _ __  _   _| |  | | |__) | \  / |                      
 | __| | '_ \| | | | |  | |  _  /| |\/| |                      
 | |_| | | | | |_| | |__| | | \ \| |  | |                      
  \__|_|_| |_|\__, |\____/|_|  \_\_|  |_|                      
               __/ |                                           
  _____       |___/               __ _     ___   ___  __  ___  
 |  __ \      / _|               / _| |   |__ \ / _ \/_ |/ _ \ 
 | |__) |__ _| |_ __ _ ___  ___ | |_| |_     ) | | | || | (_) |
 |  _  // _` |  _/ _` / __|/ _ \|  _| __|   / /| | | || |\__, |
 | | \ \ (_| | || (_| \__ \ (_) | | | |_   / /_| |_| || |  / / 
 |_|  \_\__,_|_| \__,_|___/\___/|_|  \__| |____|\___/ |_| /_/  
                                                               
  
  
  simple class to make MySql storage and object mapping, querying and return lists of objects mapped.
  
  -automatically map object and table
  -inyect data from table to object and vice versa
  -inyect _post _get to object
  -select and return a list of objects
  
  * see examples at the end of this file
*/



class tinyORM
{
	private $object = NULL; // instance of the class we going to mapping
	private $objectClass = ""; // the class of instance object
	private $objectProperties  = array(); //storage the names of the non-static properties accessibles and PHP data types. The name of the property is the key
	private  $propertiesAlias  = array(); //storage the relation between properties and column names if some or all not match exactly
	
	private $tableName = ""; //name of the table where we will store the data.		
	private $tableColumns = array(); //storage the names of all the column in the table and datata type. The name of the column is the key
	private $primaryKeys = array(); //storage the names of the columns that has PRIMARY KEY constraint
	private $autoIncrement = ""; //storage the name of the column that has auto-increment feature
	

	private $matchedProperties = array(); //Relation of property of object with the column that corresponds to it. The name of the property is the key

	private $matchedPropCols = array(); //the same that "$matchedProperties", but in this case the array has duplicate elements with column name as key. for easy key access to both, object property and column name	
	
	// templates for build sql statements
	private $updateTemplate = "UPDATE [[TABLE_NAME]] SET [[COLUMNS_VALUES]] [[WHERE]] [[ORDER_BY]] [[LIMIT]];";
	private $insertTemplate = "INSERT INTO [[TABLE_NAME]] ([[COLUMNS]]) VALUES([[VALUES]]);";
	private $selectTemplate = "SELECT [[COLUMNS]] FROM [[TABLE_NAME]] [[WHERE]] [[GROUP_BY]] [[HAVING]] [[ORDER_BY]] [[LIMIT]];";
	private $deleteTemplate = "DELETE FROM [[TABLE_NAME]] [[WHERE]] [[ORDER_BY]] [[LIMIT]];";
	private $replaceTemplate = "REPLACE INTO [[TABLE_NAME]] ([[COLUMNS]]) VALUES([[VALUES]]);";
	
	public $parameters = array(); // associative array of parameters to use in query statements, ":name" => value
	public $columns = ""; // comma separated string with the columns you wanna fill the query
	public $where = ""; //where clause string, with named parameters e.g. 'id > :parameter_id'
	public $group_by = ""; //group by clause string to use in query
	public $having = ""; //group by clause string to use in query
	public $order_by = ""; //order by clause to use in query
	public $limit = ""; //limit clause to use in query
	
	public $encloseFieldNames = true; // if true encloses fields names and table names in backticks in all statements 
	private $paramPrefix = "prmtr_"; // prefix added to all parameters that are created internally for the class
	
	public $typeCasting = true; // if true internally changes the type of data before is assigned to the object for types: integer, float and boolean
	
	// for boolean that comes as string variable converts to the corresponding value
	public $boolCastingList = 
	[
		"true"=>true,
		"yes"=>true,
		"ok"=>true,
		"si"=>true,
		"sí"=>true,
		"1"=>true,
		"false"=>false,
		"not"=>false,
		"no"=>false,
		"0"=>false
	];
	

	private $db = NULL; // database manager, instance of pdoDB class for manage the connection and consults for the database https://github.com/Rafasoftmx/pdoDB
	private $cacheExpiresSeconds = 86400; // time to save the cache before to rebuild, in seconds, 86400 = 1 day

	
	public $queryDebug = []; // store an array of the queries executed for the class, the keys are: time(string), query(string), parameters(array)
	public $queryDebugSize = 10; // max queries stored in the stack
	
	
	

	/*
	* __construct
	* assign the variables for the table and object, next automatically match them and initialise connection
	* 
	* @access public
	* @return void
	* @param object $object 
	* @param string $tableName
	* @param string $propertiesAlias
	*/
	function __construct(&$object = null,$tableName = "",$propertiesAlias = [])
	{		
		if($object !== null)
		{
			$this->object = &$object;
			$this->objectClass = get_class($this->object);

			if($tableName == "")
			{
				$this->tableName = $this->objectClass;
			}
			else
			{
				$this->tableName = trim($tableName);
			}

			$this->propertiesAlias = $propertiesAlias;


			$this->mapPropertiesAndColumns();
		}
		
		$this->db = new pdoDB(); 
	}
	
	/*
	* __debugInfo Magic Method
	* This method is called by var_dump() when dumping an object to get the properties that should be shown
	* 
	* @access public
	* @return array
	*/
    public function __debugInfo() {
        return ["tinyORM for table [".$this->tableName."] and object [".$this->objectClass."]"];
    }
	
	/*
	* __toString Magic Method
	* 
	* @access public
	* @return string
	*/
    public function __toString() {
        return "tinyORM for table [".$this->tableName."] and object [".$this->objectClass."]";
    }	
	
	/**
	 * function to get names of the non-static properties accessibles of 'object' and PHP data types. 
	 * saved in array 'objectProperties' key[property name] => value[PHP data type]
	 * 
	 * @access private
	 * @return bool
	 * 
	 */
	private function mapObjectProperties()
	{
		if ( $this->object != NULL)
		{			
			foreach (get_object_vars( $this->object) as $property => $value)
			{	
				$this->objectProperties[$property] = gettype($value);
			}	
			return true;
		}
		return  false;
	}
	
	
	/**
	 * function to get names of the columns in table defined to store the information
	 * saved in array 'tableColumns' key[column name] => value[mysql data type]
	 * 
	 * @access private
	 * @return bool
	 * 
	 */
	private function mapTableColumns()
	{
		$this->db->directQuery("SHOW COLUMNS FROM ".$this->formatIdentifier($this->tableName));

		if($this->db->stmt != false)
		{
			$columnsInformation  = $this->db->stmt->fetchAll(PDO::FETCH_ASSOC);			

			foreach($columnsInformation as $column )
			{	
				if($column["Key"] == "PRI")
				{
					$this->primaryKeys[$column["Field"]] = $column['Type'];
				}
				
				if($column["Extra"] == "auto_increment")
				{
					$this->autoIncrement = $column['Field'];
				}

				$this->tableColumns[$column["Field"]] = $column["Type"];
			}

			return true;        
		}
		
		return false;		

	}
	
	/**
	 * searches every column in properties list, and if is found generate a list of columns and properties matched.
	 * It also uses the list of aliases to define which column they match with which property
	 * saved in array 'matchedProperties' key[property name] => value[column name]
	 * saved in array 'matchedPropCols' key[property name] => value[column name] and key[column name] => value[property name]
	 * 
	 * @access private
	 * @return void
	 * 
	 */	
	private function mapMatchedProperties()
	{		
		//we create the list of values that match between the object and the columns in table		
		foreach ($this->tableColumns as $columnName => $mysqlType)
		{	
			$objectProperty = $this->getMatchProperty($columnName, $this->tableColumns, $this->propertiesAlias);// array [0]=>propertyName, [1]=>columnName
			
			if ($objectProperty !== false)
			{				 
				$this->matchedProperties[$objectProperty[0]] = $objectProperty[1]; //propertyName => columnName
				
				// matchedPropCols is used to access the relation of column-property or property-column by key
				// by these reason we define in both directions
				$this->matchedPropCols[$objectProperty[0]] = $objectProperty[1];
				$this->matchedPropCols[$objectProperty[1]] = $objectProperty[0];
			}
		}		
	}
	
	
	/**
	 * searches 'name' in 'arrToMatch' list, if is found return an array with two elements, first 'property name'
	 * and second the key matched in the array to compare.
	 * It also uses 'alias' to define which field they match with which property.
	 * 
	 * @access private
	 * @return bool
	 * @param string $name
	 * @param array $arrToMatch
	 * @param array $alias
	 */
	private function getMatchProperty($name = "", $arrToMatch = null, $alias = [])
	{
		if($name === "" || $arrToMatch === null)
		{
			return false;
		}
		
		
		$matchedProperty = array();

		//search in alias by key
		if (array_key_exists($name, $alias))
		{
			//if $name is Property
			if (array_key_exists($name, $this->objectProperties))
			{
				$matchedProperty[0] = $name;//property name
				$matchedProperty[1] = $alias[$name];//alias value
			}
			
			//if $name is in arrToMatch
			if (array_key_exists($name, $arrToMatch))
			{
				$matchedProperty[0] = $alias[$name];//property name
				$matchedProperty[1] = $name;//alias value
			}
			
			if(count($matchedProperty)> 0)// if found valid Property or  Columns
			{
				return $matchedProperty;
			}
		}
		
		//search in alias by value
		if (in_array($name, $alias))
		{
			foreach($alias as $key => $value )
			{
				if($name == $value)
				{	
					//if $name is Property
					if (array_key_exists($name, $this->objectProperties))
					{
						$matchedProperty[0] = $name;//property name
						$matchedProperty[1] = $key;//alias value
					}

					//if $name in arrToMatch
					if (array_key_exists($name, $arrToMatch))
					{
						$matchedProperty[0] = $key;//property name
						$matchedProperty[1] = $name;//alias value
					}

					if(count($matchedProperty)> 0)// if found valid Property or Columns
					{
						return $matchedProperty;
					}					
				}
			}
		}
		
		// if the name match exactly with a property name
		if (array_key_exists($name, $this->objectProperties))
		{
			$matchedProperty = array();
			$matchedProperty[0] = $name;//propertyName
			$matchedProperty[1] =  $name;//match name
			return $matchedProperty;
		}
		
		return false;
	}
			
	/**
	 * builds the list of properties, columns and matched elements, also save this data in cache to avoid recalculate every time
	 * 
	 * @access private
	 * @return void
	 * 
	 */
	private function mapPropertiesAndColumns()
	{
		if( is_null( $this->getCache("tableColumns") ) )// verify if exist data, otherwise stores them
		{	
			//first get the data, and create the arrays
			$this->mapObjectProperties();
			$this->mapTableColumns();
			$this->mapMatchedProperties();			
			
			$this->saveCache($this->objectProperties,"objectProperties");		
			$this->saveCache($this->tableColumns,"tableColumns");
			$this->saveCache($this->primaryKeys,"primaryKeys");
			$this->saveCache($this->autoIncrement,"autoIncrement");
			$this->saveCache($this->matchedProperties,"matchedProperties");
			$this->saveCache($this->matchedPropCols,"matchedPropCols");
		}
		else // get data from cache
		{	
			$this->objectProperties = $this->getCache("objectProperties");
			$this->tableColumns = $this->getCache("tableColumns");
			$this->primaryKeys = $this->getCache("primaryKeys");
			$this->autoIncrement = $this->getCache("autoIncrement");
			$this->matchedProperties = $this->getCache("matchedProperties");
			$this->matchedPropCols = $this->getCache("matchedPropCols");
		}

		
	}
	
	/**
	 * uses the phpfastcache (https://www.phpfastcache.com/) to dave data from class to avoid recalculate them
	 * 
	 * @access private
	 * @return void
	 * @param mixed &$data
	 * @param string $cacheKey
	 */
	private function saveCache(&$data,$cacheKey)
	{
		global $cacheManager;
		$cache = $cacheManager->getItem($this->objectClass.$this->tableName.$cacheKey);
		$cache->set( $data )->expiresAfter( $this->cacheExpiresSeconds );
		$cacheManager->save($cache);
	}
	
	/**
	 * uses the phpfastcache to obtain cache data from a key and return them
	 * 
	 * @access private
	 * @return mixed
	 * @param string $cacheKey
	 */
	private function getCache($cacheKey)
	{
		global $cacheManager;
		$cache = $cacheManager->getItem($this->objectClass.$this->tableName.$cacheKey);
		return $cache->get();
	}	
	
	/**
	 * builds and execute a insert query with the matched values of the object
	 * 
	 * @access public
	 * @return bool
	 * 
	 */
	public function insert()
	{	
		$parts = ["[[TABLE_NAME]]"=>"","[[COLUMNS]]"=>"","[[VALUES]]"=>""];	
		$params = [];
		$query = $this->setSentenceParts($this->insertTemplate,$parts,$params);		

		if ($this->db->preparedQuery($query,$params) == true) // build and execute query,
		{	
			if ($this->autoIncrement != "")
			{	
				$autoIncrement = $this->autoIncrement;
				$this->object->$autoIncrement = intval($this->db->pdo->lastInsertId($autoIncrement));	// set 	auto Increment value			
			}		
			return true;			
		}	

		return false;
	}
	
	/**
	 * build and execute a update query whit the matched values of the object
	 * 
	 * @access public
	 * @return bool
	 * @param bool $addPrimaryKeys
	 */
	public function update($addPrimaryKeys = true)
	{	
		$parts= array("[[TABLE_NAME]]"=>"","[[COLUMNS_VALUES]]"=>"","[[WHERE]]"=>"","[[ORDER_BY]]"=>"","[[LIMIT]]"=>"");
		$params = [];
		$query = $this->setSentenceParts($this->updateTemplate,$parts,$params,$addPrimaryKeys);

		if ($this->db->preparedQuery($query,$params) == true) // build and execute query,
		{
			return true;			
		}	

		return false;
	}	
	
	/**
	 * build and execute a delete query whit the matched Primary Keys
	 * 
	 * @access public
	 * @return bool
	 * @param bool $addPrimaryKeys
	 */
	public function delete($addPrimaryKeys = true)
	{	
		$parts= array("[[TABLE_NAME]]"=>"","[[WHERE]]"=>"","[[ORDER_BY]]"=>"","[[LIMIT]]"=>"");
		$params = [];
		$query = $this->setSentenceParts($this->deleteTemplate,$parts,$params,$addPrimaryKeys);

		if ($this->db->preparedQuery($query,$params) == true) // build and execute query,
		{
			return true;			
		}

		return false;
	}

	/**
	 * build and execute a select query, returns a list of objects of the class defined
	 * also return a nested list of objects grouped by a field
	 * also return a single field Value
	 * also return a Array of the values of a Column defined
	 * also return a Array key Value Pairs
	 * 
	 * @access public
	 * @return bool
	 * @param string $type
	 * @param string $option
	 * @param bool $addPrimaryKeysInWhere
	 * @param bool
	 */
	public function select($type = "objectList",$option = null,$addPrimaryKeysInWhere = false)
	{	
		$parts= array("[[COLUMNS]]"=>"","[[TABLE_NAME]]"=>"","[[WHERE]]"=>"","[[GROUP_BY]]"=>"","[[HAVING]]"=>"","[[ORDER_BY]]"=>"","[[LIMIT]]"=>"");
		$params = [];
		$query = $this->setSentenceParts($this->selectTemplate,$parts,$params,$addPrimaryKeysInWhere,false);

			switch ($type)
			{
				case "objectList":
					
						if ($this->db->preparedQuery($query,$params) == true) // build and execute query,
						{
							$objectslist = [];

							while ($row = $this->db->stmt->fetch(PDO::FETCH_ASSOC))
							{
								$newObject = new $this->objectClass();
								$objectslist[] = $this->fillObject($newObject,$row);

							}

							return $objectslist;			
						}
					break;
					
				case "objectListGroupedByField":
					if($option !== null)
					{
						if ($this->db->preparedQuery($query,$params) == true) // build and execute query,
						{
							$objectslist = [];

							while ($row = $this->db->stmt->fetch(PDO::FETCH_ASSOC))
							{
								if(array_key_exists($option,$row))
								{
									$newObject = new $this->objectClass();
									$objectslist[$row[$option]][] = $this->fillObject($newObject,$row);									
								}
								else
								{
									return false;
								}
							}

							return $objectslist;			
						}
					}
					break;
					
				case "singleValue": // returns the value of the columnNumber or columnName of returned row
					
						if($option !== null)
						{
							return $this->db->getSingleValue($query, $params,$option);	// query,params,columnNumber or columnName
						}
					break;
					
					
				case "ArrayColumn": // returns all values of a particular column in one-dimensional array.
					
						if($option !== null)
						{
							return $this->db->getArrayColumn($query, $params,$option);	// query,params,columnNumber or columnName
						}
					break;
					
				case "keyValuePairs": // returns an array key-value pairs indexed by the first field, requires select extactly 2 columns					

							return $this->db->getKeyValuePairs($query, $params);	
					break;
					
				case "indexedUnique": // ame as keyValuePairs, but getting not one column but full row indexed by unique field					

							return $this->db->getIndexedUnique($query, $params);
					break;
					
				case "groupedByFirstField": // ame as keyValuePairs, but getting not one column but full row indexed by unique field					

							return $this->db->getIndexedUnique($query, $params);
					break;

			}//EO switch



		return false;
	}
	
	/**
	 * builds and execute a replace query with the matched values of the object,
	 * is an insert, except that if an old row in the table has the same value 
	 * as a new row for a PRIMARY KEY or a UNIQUE index, the old row is DELETED before the new row is inserted.
	 *
	 * @access public
	 * @return bool
	 * 
	 */
	public function replace()
	{	
		$parts = ["[[TABLE_NAME]]"=>"","[[COLUMNS]]"=>"","[[VALUES]]"=>""];	
		$params = [];
		$query = $this->setSentenceParts($this->replaceTemplate,$parts,$params,false,false);		

		if ($this->db->preparedQuery($query,$params) == true) // build and execute query,
		{	
			if ($this->autoIncrement != "")
			{	
				$autoIncrement = $this->autoIncrement;
				$this->object->$autoIncrement = intval($this->db->pdo->lastInsertId($autoIncrement));	// set 	auto Increment value			
			}		
			return true;			
		}	

		return false;
	}

	/**
	 * check the existence of a record in table using PRIMARY KEYs, if exist, update else insert
	 *
	 * @access public
	 * @return bool
	 * 
	 */
	public function upsert()
	{
		if($this->isIntable())
		{
			return $this->update();
		}
		else
		{
			return $this->insert();
		}

		return false;
	}
	
	/**
	 * load the object from the database
	 * build and execute a select query whit the matched Primary Keys, and set the returned data to the object.
	 *
	 * @access public
	 * @return bool
	 * 
	 */
	public function load()
	{	//select parts
		$parts= array("[[COLUMNS]]"=>"","[[TABLE_NAME]]"=>"","[[WHERE]]"=>"","[[GROUP_BY]]"=>"","[[HAVING]]"=>"","[[ORDER_BY]]"=>"","[[LIMIT]]"=>"");
		$params = [];
		$query = $this->setSentenceParts($this->selectTemplate,$parts,$params,true,false);

		if ($this->db->preparedQuery($query,$params) == true) // build and execute query,
		{
			return $this->fillObject($this->object, $this->db->stmt->fetch(PDO::FETCH_ASSOC));			
		}

		return false;
	}


	/**
	 * populates the object with a row returned from the data base
	 * 
	 * @access public
	 * @return mixed
	 * @param object $object
	 * @param array $row
	 */
	private function fillObject(&$object,$row)
	{	
		if ($row)
		{	
			foreach ($this->matchedProperties as $propertyName => $columnName)
			{				
				if(array_key_exists($columnName ,$row))
				{
					if($this->typeCasting == true)
					{
						$object->$propertyName = $this->typeCastingVar($row[$columnName], $this->objectProperties[$propertyName]);// 'var', 'property class type'
					}
					else
					{
						$object->$propertyName = $row[$columnName];
					}
					
				}				
			}
			
			return $object;
		}
		return false;
	}
	
	/**
	 *  populates the object with an array, match the elements by the key in array and optional uses an alias array for match
	 * 
	 * @access public
	 * @return bool
	 * @param array $array
	 * @param array propertiesAlias
	 */
	public function fillObjectFromArray($array,$propertiesAlias = [])
	{
		if(!is_array($array))
		{
			return false;
		}
		
		//browse every key that match with object property	
		foreach ($array as $key => $value)
		{	
			$objectProperty = $this->getMatchProperty($key, $array, $propertiesAlias);// returns array [0]=>propertyName, [1]=>MatchedKey
			
			if ($objectProperty !== false)//occur a match
			{				
				$propertyName = $objectProperty[1];
				$propertyType = $this->objectProperties[$propertyName];
				
				if($this->typeCasting == true)
				{
					$this->object->$propertyName = $this->typeCastingVar( $value, $propertyType);// 'var', 'property class type'
				}
				else
				{
					$this->object->$propertyName = $value;
				}				
			}
		}
		
		return true;
	}	
	
	/**
	 * builds a query statement using the template and array with the different parts of query
	 * replaces all occurrences in  a string "$sentence" with associative array where key is the "search" and value is "replace" 
	 * 
	 * @access private
	 * @return void
	 * @param string $sentence 
	 * @param array $parts
	 *
	 */
	private function replaceSentenceParts(&$sentence, $parts)
	{		
		foreach ($parts as $search => $replace)
		{
			$sentence = str_replace($search, $replace, $sentence);
		}	
		$this->resetQueryParts();
		return $sentence;
	}	
	
	
	/**
	 *  builds the diferents parts of query
	 * 
	 * @access private
	 * @return string
	 *
	 * @param array template
	 * @param array &$parts
	 * @param array &$params
	 * @param array $addPrimaryKeysInWhere
	 * @param array $skipAutoIncrementColumn
	 */
	private function setSentenceParts($template,&$parts,&$params,$addPrimaryKeysInWhere = false,$skipAutoIncrementColumn = true)
	{
		//---------------------------------------------------- TABLE_NAME		
		$this->setPartTableName($parts);
		//---------------------------------------------------- ORDER_BY
		$this->setPartOrderBy($parts);
		//---------------------------------------------------- LIMIT
		$this->setPartLimit($parts);
		//---------------------------------------------------- GROUP_BY
		$this->setPartGroupBy($parts);		
		//---------------------------------------------------- HAVING
		$this->setPartHaving($parts);
		//---------------------------------------------------- WHERE
		$this->setPartWhere($parts,$params,$addPrimaryKeysInWhere);		
		//---------------------------------------------------- COLUMNS, VALUES, COLUMNS_VALUES
		$this->setPartColumns($parts,$params,$skipAutoIncrementColumn);
		
		//add external parameters
		$params = array_merge($params,$this->parameters);
		
		//build full sentence
		$query = $this->replaceSentenceParts($template, $parts);
		
		//adds Query Debuging
		if(count($this->queryDebug) >= $this->queryDebugSize)
		{
			array_shift($this->queryDebug);
		}
		$this->queryDebug[] = ["time"=>date("H:i:s Y-m-d "),"query"=>$query,"parameters"=>$params];
			
		d($this->queryDebug);
		return $query;//return the armed full sentence

	}
	
	
	/**
	 *  sets TABLE_NAME fragment
	 * 
	 * @access private
	 * @return void
	 * @param array &$parts
	 */
	private function setPartTableName(&$parts)
	{		
		if(array_key_exists("[[TABLE_NAME]]", $parts))
		{			
			$parts["[[TABLE_NAME]]"] = $this->formatIdentifier($this->tableName);
		}
	}
	
	/**
	 *  builds and sets ORDER BY fragment of query
	 * 
	 * @access private
	 * @return void
	 * @param array &$parts
	 *
	 */
	private function setPartOrderBy(&$parts)
	{		
		if(array_key_exists("[[ORDER_BY]]", $parts))
		{
			if($this->order_by != "")
			{
				if($this->encloseFieldNames == true )
				{
					$arrOrder = explode(",", $this->order_by);				

					$comma = "";
					$order_by = "";				

					foreach($arrOrder as $columnName)
					{
						$order = "";
						if(preg_match('/\s(ASC)/i', $columnName))
						{
							$columnName = preg_replace('/\s(ASC)/i', '', $columnName);
							$order = " ASC ";
						}
						elseif(preg_match('/\s(DESC)/i', $columnName))
						{
							$columnName = preg_replace('/\s(DESC)/i', '', $columnName);
							$order = " DESC ";
						}

						$columnName = trim($columnName);						
	
						$columnName = $this->formatIdentifier($columnName);
						
						
						$order_by .= $comma . $columnName . $order;
						$comma = ", ";
					}

					$parts["[[ORDER_BY]]"] = " ORDER BY " . $order_by;
				}
				else
				{
					$parts["[[ORDER_BY]]"] = " ORDER BY " . $this->order_by;
				}
			}			
		}
	}
	
	
	/**
	 *  builds and sets LIMIT fragment of query
	 * 
	 * @access private
	 * @return void
	 * @param array &$parts
	 *
	 */
	private function setPartLimit(&$parts)
	{	
		if(array_key_exists("[[LIMIT]]", $parts))
		{
			if($this->limit != "")
			{
				$parts["[[LIMIT]]"] = " LIMIT " . $this->limit;
			}			
		}
	}
	
	
	/**
	 *  builds and sets GROUP BY fragment of query
	 * 
	 * @access private
	 * @return void
	 * @param array &$parts
	 *
	 */
	private function setPartGroupBy(&$parts)
	{	
		if(array_key_exists("[[GROUP_BY]]", $parts))
		{
			if($this->group_by != "")
			{
				if($this->encloseFieldNames == true )
				{
					$arrGroup = explode(",", $this->group_by);

					$comma = "";
					$group_by = "";				
					foreach($arrGroup as $columnName)
					{
						$columnName = trim($columnName);				

						$columnName = $this->formatIdentifier($columnName);


						$group_by .= $comma . $columnName;
						
						$comma = ", ";					
					}

					$parts["[[GROUP_BY]]"] = " GROUP BY " . $group_by;
				}
				else
				{
					$parts["[[GROUP_BY]]"] = " GROUP BY " . $this->group_by;
				}
				
			}			
		}		
	}	
	
	/**
	 *  builds and sets HAVING fragment of query
	 * 
	 * @access private
	 * @return void
	 * @param array &$parts
	 *
	 */
	private function setPartHaving(&$parts)
	{	
		if(array_key_exists("[[HAVING]]", $parts))
		{
			if($this->having != "")
			{
				$parts["[[HAVING]]"] = " HAVING " . $this->having;
			}			
		}				
	}
	
	/**
	 *  builds and sets WHERE fragment of query
	 * 
	 * @access private
	 * @return void
	 * @param array &$parts
	 * @param array &$params
	 * @param bool $addPrimaryKeysInWhere
	 *
	 */
	private function setPartWhere(&$parts,&$params,$addPrimaryKeysInWhere = false)
	{		
		$wherePrimaryKeys = "";
		if($addPrimaryKeysInWhere)
		{
			$and = "";
			foreach($this->primaryKeys as $columnName => $mysqlType )
			{
				$paramColumnName = $this->paramPrefix . $columnName;
				
				$propertyName = $this->matchedPropCols[$columnName];
				$wherePrimaryKeys .= $and . $this->formatIdentifier( $columnName ) . " = :" . $paramColumnName;
				$and = " AND ";
				
				$propertyName = $this->matchedPropCols[$columnName];
				$params[":" . $paramColumnName] = $this->object->$propertyName; // set string parameter key and value from object
			}
		}
		
		//---------------------------------------------------- WHERE	
		if(array_key_exists("[[WHERE]]", $parts))
		{
			$and = "";
			
			if($wherePrimaryKeys != "" && $this->where != "")
			{
				$and = " AND ";
			}
			
			$parts["[[WHERE]]"] =  $wherePrimaryKeys . $and . $this->where;
			
			if($parts["[[WHERE]]"] != "")
			{
				$parts["[[WHERE]]"] = " WHERE " . $parts["[[WHERE]]"];
			}
		}
	}
	
	
	/**
	 *  builds and sets COLUMNS and VALUES fragment of query
	 * 
	 * @access private
	 * @return void
	 * @param array &$parts
	 * @param array &$params
	 * @param array $skipAutoIncrementColumn
	 *
	 */
	private function setPartColumns(&$parts,&$params,$skipAutoIncrementColumn = true)
	{		
		if($this->columns != '')// if there is a list of columns
		{			
			$arrcolumns = explode(",", $this->columns);
			
			$comma = "";
			foreach($arrcolumns as $columnName)
			{
				$columnName = trim($columnName);

				if($skipAutoIncrementColumn == true && $columnName == $this->autoIncrement)
				{
					continue;
				}


				$paramColumnName = $this->paramPrefix . $columnName;


				$formatColumnName = $this->formatIdentifier($columnName);




				$addParam = false;
				if(array_key_exists("[[COLUMNS]]", $parts))
				{
					$parts["[[COLUMNS]]"] .=  $comma . $formatColumnName;
				}

				if(array_key_exists("[[VALUES]]", $parts))
				{
					$parts["[[VALUES]]"] .= $comma . ":" . $paramColumnName;
					$addParam = true;
				}

				if(array_key_exists("[[COLUMNS_VALUES]]", $parts))
				{
					$parts["[[COLUMNS_VALUES]]"] .=  $comma . $formatColumnName . " = :" . $paramColumnName;
					$addParam = true;
				}					

				$comma = ", ";

				if($addParam)
				{
					$propertyName = $this->matchedPropCols[$columnName];
					$params[":" . $paramColumnName] = $this->object->$propertyName; // set string parameter key and value from object
				}
			
			}

		}
		else //set the columns from the matched Properties
		{
			$comma = "";
			foreach ($this->matchedProperties as $propertyName => $columnName)
			{	
				if($skipAutoIncrementColumn == true && $columnName == $this->autoIncrement)
				{
					continue;
				}
				
				$paramColumnName = $this->paramPrefix .$columnName;


				$formatColumnName = $this->formatIdentifier($columnName);
	
				
				
				$addParam = false;
				if(array_key_exists("[[COLUMNS]]", $parts))
				{
					$parts["[[COLUMNS]]"] .=  $comma . $formatColumnName;
				}

				if(array_key_exists("[[VALUES]]", $parts))
				{
					$parts["[[VALUES]]"] .= $comma . ":" . $paramColumnName;
					$addParam = true;
				}

				if(array_key_exists("[[COLUMNS_VALUES]]", $parts))
				{
					$parts["[[COLUMNS_VALUES]]"] .=  $comma . $formatColumnName . " = :" . $paramColumnName;
					$addParam = true;
				}					

				$comma = ", ";					
				
				if($addParam)
				{
					$propertyName = $this->matchedPropCols[$columnName];
					$params[":" . $paramColumnName] = $this->object->$propertyName; // set string parameter key and value from object
				}

				
			}
		}
	}
		
	/**
	 *  reset the values to the originals, for clean if a new query is executed
	 * 
	 * @access private
	 * @return void
	 *
	 */
	private function resetQueryParts()
	{		
		$this->parameters = array(); // list of columns to use in query
		$this->columns = ""; // list of columns to use in query
		$this->where = ""; //where clause to use in query
		$this->group_by = ""; //group by clause to use in query
		$this->having = ""; //having clause to use in query
		$this->order_by = ""; //order by clause to use in query
		$this->limit = ""; //limit clause to use in query
	}	
	
	/**
	 *  cast a value integer, double and boolean, depending of the PHP type specified
	 * 
	 * @access private
	 * @return string
	 * @param mixed $val
	 * @param string $type
	 */
	private function typeCastingVar($val,$type)
	{		
		switch ($type) {
			case "integer":
				return (integer)$val;//applies for  (int), (integer)
				break;
			case "double":
				return (double)$val;//applies for (float), (double), (real) 
				break;
			case "boolean":
				
				if(gettype($val) == "string")
				{
					$val = trim($val);
					if(array_key_exists($val,$this->boolCastingList))
					{
						return $this->boolCastingList[$val];
					}
				}				
				
				return (boolean)$val;//applies for (bool), (boolean)
				break;
		}
		
		return $val;
	}
	
	/**
	 * try to enclose automatically identifier in backtick (`) if has basic Latin letters, digits 0-9, dollar and underscore characters.
	 * Escape backticks inside by doubling them
	 * 
	 * @access private
	 * @return string
	 *
	 */
	private function formatIdentifier($identifier)
	{		
		if($this->encloseFieldNames == true )
		{
			if( preg_match('/^[0-9,a-z,A-Z$_]+$/', $identifier) == 1 )
			{
				$identifier = $this->db->formatIdentifier($identifier);	
			}
			
		}
		return $identifier;
	}	
	
	/**
	 * makes a select with the primary keys on the object, if exist in table return true, else false
	 * 
	 * @access public
	 * @return bool
	 *
	 */
	public function isIntable()
	{	
		$this->columns = "trim('ok')";// just to add some generic column in query, and avoid to get all data in columns
		$this->limit = "1";
		
		if($this->select("singleValue",0,true) === false)
		{
			return false;
		}
		else
		{
			return true;
		}
	}	
	
	/**
	 * create a PHP class automatically from table in the data base.
	 * 
	 * @access public
	 * @return string
	 * @param array $tableName
	 * @param array $prefix
	 * @param array $sufix
	 *
	 */
	public function createClass($tableName ="name",$prefix ="",$sufix = "")
	{
		$this->tableName = $tableName;
		$this->mapTableColumns();
		
$classTemplate = 
'
<?php

//Adequate the class, delete unnecessary properties or change the names.
class [[TABLE_NAME]]
{[[PROPERTIES]]
	public $ORM = null;	

   function __construct()
   {
   	//delete "$alias" if the properties are the same than the colums in table
	//or add the properties that not match, put the name of property with corresponding column name, delete the sobrants
	$alias = [[[ALIAS]]
	];
	
	$this->ORM = new tinyORM($this,"[[TABLE_NAME]]",$alias);
   }
}

?>
';
		
		$parts = ["[[TABLE_NAME]]"=>"","[[PROPERTIES]]"=>"","[[ALIAS]]"=>""];	
		
		$mysqlTypes = [
			"TINYINT" => 0,
			"SMALLINT" => 0,
			"MEDIUMINT" => 0,
			"INTEGER" => 0,
			"BIGINT" => 0,
			"INT" => 0,
			"REAL" => 0.0,
			"DOUBLE" => 0.0,
			"FLOAT" => 0.0,
			"DECIMAL" => 0.0,
			"NUMERIC" => 0.0,
			"PRECISION" => 0.0,
			"FIXED" => 0.0,
			"DEC" => 0.0,
			"BIT(1)" => 'false',
			"TINYINT(1)" => 'false'
		];
		
		$parts["[[TABLE_NAME]]"] = $this->tableName;
		
		$alias = false;
		if($prefix != "" || $sufix != "")
		{
			$alias = true;
		}
		
		
		
		$coma ="";
		foreach($this->tableColumns as $ColumnName => $ColumnType )		
		{
			$ColumnType = strtolower($ColumnType);
			$found = false;
			foreach($mysqlTypes as $type => $value )		
			{
				$type = strtolower($type);
				
				if(strpos($ColumnType ,$type) !== false)
				{
$parts["[[PROPERTIES]]"] .= "
	public  $".$prefix.$ColumnName.$sufix." = ".$value.";";
$found =true;
				}
				
			}
			
			
			
			if($found == false)
			{
$parts["[[PROPERTIES]]"] .= "
	public  $".$prefix.$ColumnName.$sufix." = '';";
			}
			
			
			
			
			if($alias)
			{
$parts["[[ALIAS]]"] .= $coma." 
		'".$prefix.$ColumnName.$sufix."' => '".$ColumnName."'";
			}
			else
			{
$parts["[[ALIAS]]"] .= $coma." 
		'' => '".$ColumnName."'";				
			}

			
			$coma =",";
		}
		
		
		$class = $this->replaceSentenceParts($classTemplate,$parts);
		
		
		$fdh = new fileDirHandler("class.".ucfirst($this->tableName)."_".time().".php");
		$fdh->Write($class);// create if not exist
		
		return $class;
		
	}
	

}//EOC


/*
										Examples
----------------------------------------------------------------------------------------

set database parameters

in the file class.pdoDB.php you can configure yor conection:

class pdoDB
{

 	var  $db_type="mysql";
	var  $db_host= "localhost"; //Server host and port if any
	var  $db_name= "usrs"; //Database name
	var  $db_usr= "me"; //User name
	var  $db_pss= "pasword"; //Password

...


SQL to create simple table for example 

CREATE TABLE `NewTable` (
`id`  int NOT NULL AUTO_INCREMENT ,
`date`  datetime NULL ,
`mail`  varchar(255) NULL ,
`name`  varchar(255) NULL ,
`rol`  int NULL ,
`enabled`  bit NULL ,
PRIMARY KEY (`id`)
)
;

note the id of the table is auto increment and primary key
and the enabled is bit because i want a boolean field, also can be TINYINT(1)
the tables names is 'users'

the class that represents the tables is:

class usr
{
	public  $id = 0;
	public  $date = 'sec string';
	public  $mail = '';
	public  $name = '';
	public  $rol = 0;
	public  $enabled = false;
	
   function __construct() {	 
   }//EOF

}//EOC

note i define the default values for the object, because the class remembers it for casting the variable for integer, float and boolean


for a basic example i create an instance of the class 'usr' and use the 'tinyORM' for manage the information in the object.
the constructor neds:

1. object instance
2. table name (if not set take 'object instance' class)
3. array alias for properties names and table column (optional)

$usr = new usr();
$tinyORM = new tinyORM($usr,"users"); //yeah!! we map the object, table  and initialize the class for use them

for example if the properties in our object not match with the column names in the table,
we can define an array with the relation of properties and column names:

// in this case the class not match with the table columns
class usr
{
	public  $id_user = 0;
	public  $date_insert = 'sec string';
	public  $mail_user = '';
	public  $name_user = '';
	public  $rol_user = 0;
	public  $enabled = false; // except this, for these reason is nos in the array $usr_alias
	
   function __construct() {	 
   }//EOF

}//EOC

$usr = new usr();
$usr_alias = [
	"id_user" => "id",
	"date_insert" => "date",
	"mail_user" => "mail",
	"name_user" => "name",
	"rol_user" => "rol"
];

//the alias array can be in key value reverse, and mixed
$usr_alias = [
	"id" => "id_user",
	"date" => "date_insert",
	"mail" => "mail_user",
	"name" => "name_user",
	"rol" => "rol_user"
];

$tinyORM = new tinyORM($usr,"users",$usr_alias);







insert our object to the table:


$usr = new usr();
$tinyORM = new tinyORM($usr,"users");

$usr->date = date("Y-m-d H:i:s");
$usr->mail = "example@some.com";
$usr->name = "jane doe";
$usr->rol = 1;
$usr->enabled = false;

$tinyORM->insert(); // and that's all



in this example we can add all the functionality inside the object including a tinyORM instance in the class
and initialize in constructor function.


class usr
{
	public  $id = 0;
	public  $date = '';
	public  $mail = '';
	public  $name = '';
	public  $rol = 0;
	public  $enabled = false;
	
	public $ORM = null;	// just be careful to not name like a column in the table
	
   function __construct()
   {	   
	   $this->ORM = new tinyORM($this,"users");
   }

}//EOC

$usr = new usr();

$usr->date = date("Y-m-d H:i:s");
$usr->mail = "example@some.com";
$usr->name = "jane doe";
$usr->rol = 1;
$usr->enabled = false;

$usr->ORM->insert();


Update our object to the table:


$usr = new usr();

$usr->id = 1; //set the id of the element we want to update
$usr->date = date("Y-m-d H:i:s");
$usr->mail = "example@some.com";
$usr->name = "jane doe";
$usr->rol = 1;
$usr->enabled = false;

$usr->ORM->update();




Delete our object in the table:

$usr = new usr();

$usr->id = 1; //set the ID of the element we want delete
$usr->ORM->delete();




replace record on the table:

this Mysql functionality inserts a record if not exist, but if exist DELETES previous and inserts new one with the same PRIMARY KEY or a UNIQUE index

$usr = new usr();
$tinyORM = new tinyORM($usr,"users");

$usr->id = 1;
$usr->date = date("Y-m-d H:i:s");
$usr->mail = "example@some.com";
$usr->name = "jane doe";
$usr->rol = 1;
$usr->enabled = true;

$tinyORM->replace();



upsert:  my version of replace or insert on duplicate key update
basically checks the existence of a record in table using PRIMARY KEYs, if exist update else insert


$usr = new usr();
$tinyORM = new tinyORM($usr,"users");

$usr->id = 1;
$usr->date = date("Y-m-d H:i:s");
$usr->mail = "example@some.com";
$usr->name = "jane doe";
$usr->rol = 1;
$usr->enabled = true;

$tinyORM->upsert();



load record to the object

$usr = new usr();

$usr->id= 7;// id we want to load
$usr->ORM->load();

//show data loaded
print "<pre>";
var_dump($usr);
print "</pre>";






Select records in the database and return a list of objects and useful data structures.

- build and execute a select query, returns a list of objects of the class defined
- also return a nested list of objects grouped by a field
- also return a single field Value
- also return a Array of the values of a Column defined
- also return a Array key Value Pairs

parameters:


- type: tipe of elemet to return;

- "objectList"(default):
- "objectListGroupedByField": return a nested list of objects grouped by a field
- "singleValue": return a single field Value
- "ArrayColumn": return array of values of a Column defined
- "keyValuePairs": Array key-Value Pairs, neets select exactly 2 columns
- "indexedUnique": Array key-[row array]
- "groupedByFirstField": Array key-[row group array], where key is the first colum you defined
	
	
- option: in the case of "objectListGroupedByField", "singleValue" and  "ArrayColumn" is used to specify the column name.
- addPrimaryKeysInWhere: ads automatically the primary keys in where clause, is used in load method to make the select.



to make a list of objects:

$usr = new usr();
$list = $usr->ORM->select(); // be careful, selects all table

print "<pre>";
var_dump($list);
print "</pre>";

for make a selection we can use different part of statement and send extra parameters to make a query:

- where
- order_by
- limit
- group_by
- having
- columns: for just affect or push in object only the defined columns
- parameters: for parametrise the diferent parts of statements

all of them works exactly like in Mysql, just need avoid put the name of clause. and internally are escaped.


where example:

$usr->ORM->where = "id > 7 AND mail = 'example@some.com'";

becomes:

SELECT `id`, `date`, `mail`, `name`, `rol`, `enabled` FROM `users`  WHERE id > 10 AND mail = 'example@some.com';

important note:
by default automatically all colums and table names are scaped using 'formatIdentifier()' method, by this reason are between backtick (`), 
to avoid this, set encloseFieldNames = false; its can be useful wen uses complex colums.


parameterized query: (recommended to try avoiding sql injection)

$usr->ORM->where = "id > :id AND mail = :mail";
$usr->ORM->parameters = [":id" => 7, ":mail" => "example@some.com" ];

becomes:

SELECT `id`, `date`, `mail`, `name`, `rol`, `enabled` FROM `users`  WHERE id > :id AND mail = :mail;



columns example:

it's useful for select modes that not return an object or when you want populate partially a object. afects all methods that makes a query.


$usr->ORM->columns = "id, mail";
$usr->ORM->where = "id > :id AND mail = :mail";
$usr->ORM->parameters = [":id" => 7, ":mail" => "example@some.com"];


order_by example:


$usr->ORM->where = "id > :id AND mail = :mail";
$usr->ORM->parameters = [":id" => 7, ":mail" => "example@some.com" ];

$usr->ORM->order_by = "id DESC,name ASC,mail";

becomes:

SELECT `id`, `date`, `mail`, `name`, `rol`, `enabled` FROM `users`  WHERE id > :id AND mail = :mail ORDER BY `id` DESC , `name` ASC , `mail`;




limit example:


$usr->ORM->where = "id > :id AND mail = :mail";
$usr->ORM->order_by = "id DESC,name ASC,mail";

$usr->ORM->limit = ":limit";

$usr->ORM->parameters = [":id" => 7, ":mail" => "example@some.com",  ":limit" => 2 ];

note that i add ':limit' parameter in the list and the order not matter, nor for parameters neither clauses

becomes:

SELECT `id`, `date`, `mail`, `name`, `rol`, `enabled` FROM `users`  WHERE id > :id AND mail = :mail    ORDER BY `id` DESC , `name` ASC , `mail`  LIMIT :limit;




group_by example:

$usr->ORM->where = "id > :id AND mail = :mail";
$usr->ORM->order_by = "id DESC";
$usr->ORM->limit = ":limit";

$usr->ORM->group_by = "rol,mail";

$usr->ORM->parameters = [":id" => 7, ":mail" => "example@some.com",  ":limit" => 10 ];


becomes:

SELECT `id`, `date`, `mail`, `name`, `rol`, `enabled` FROM `users`  WHERE id > :id AND mail = :mail  GROUP BY `rol`, `mail`   ORDER BY `id` DESC   LIMIT :limit;



having example:

$usr->ORM->columns = "*,COUNT(id)";
$usr->ORM->where = "id > :id";
$usr->ORM->group_by = "mail";

$usr->ORM->having = "COUNT(id) > 2";

$usr->ORM->parameters = [":id" => 7];


becomes:

SELECT *, COUNT(id) FROM `users` WHERE id > :id  GROUP BY `mail` HAVING COUNT(id) > 2;




Other Select option for useful data structures.


"objectListGroupedByField" example:
return a nested list of objects grouped by a field

$usr = new usr();

$list = $usr->ORM->select("objectListGroupedByField","rol");

print "<pre>";
var_dump($list);
print "</pre>";

returns:

array(2) {
  ["1"]=>	  array(5) { 
				OBJECT
				OBJECT
				OBJECT
				...
	  }
  ["2"]=>	  array(5) { 
				OBJECT
				OBJECT
				OBJECT
				...
	  }
...

in this example.. 
["1"] is a rol
["2"] is another role





"singleValue" example:
return a single field Value

$usr = new usr();
$usr->ORM->where = "id = :id";
$usr->ORM->parameters = [":id" => 7];

$list = $usr->ORM->select("singleValue","mail");

print "<pre>";
var_dump($list);
print "</pre>";

returns:

string(16) "example@some.com"




"ArrayColumn" example:
return an array of all values of a Column defined

$usr = new usr();
$usr->ORM->limit = ":limit";
$usr->ORM->parameters = [":limit" => 10];

$list = $usr->ORM->select("ArrayColumn","mail");

print "<pre>";
var_dump($list);
print "</pre>";

returns:

array(10) {
  [0]=>  string(16) "example@some.com"
  [1]=>  string(9) "Dr@Seuss"
}




"keyValuePairs" example:
return an array key-Value Pairs, neets select exactly 2 columns

$usr = new usr();
$usr->ORM->columns = "name,mail";
$usr->ORM->limit = ":limit";
$usr->ORM->parameters = [":limit" => 2];

$list = $usr->ORM->select("keyValuePairs");

print "<pre>";
var_dump($list);
print "</pre>";

returns:


array(2) {
  ["jane doe"]=>  string(16) "example@some.com"
  ["Dr. Seuss"]=>  string(9) "Dr@Seuss"
}





"indexedUnique" example:
return an array key-[row array]

$usr = new usr();
$usr->ORM->columns = "name,mail";
$usr->ORM->limit = ":limit";
$usr->ORM->parameters = [":limit" => 2];

$list = $usr->ORM->select("indexedUnique");

print "<pre>";
var_dump($list);
print "</pre>";

returns:


array(2) {
  [1]=>
  array(10) {
    ["date"]=>    string(19) "2019-02-14 01:47:11"
    [0]=>    string(19) "2019-02-14 01:47:11"
    ["mail"]=>    string(16) "example@some.com"
    [1]=>    string(16) "example@some.com"
    ["name"]=>    string(8) "jane doe"
    [2]=>    string(8) "jane doe"
    ["rol"]=>    string(1) "5"
    [3]=>    string(1) "5"
    ["enabled"]=>    string(1) "1"
    [4]=>    string(1) "1"
  }
  [2]=>
  array(10) {
    ["date"]=>    string(19) "2019-02-13 20:49:04"
    [0]=>    string(19) "2019-02-13 20:49:04"
    ["mail"]=>    string(9) "Dr. Seuss"
    [1]=>    string(9) "Dr. Seuss"
    ["name"]=>    string(9) "Dr. Seuss"
    [2]=>    string(9) "Dr. Seuss"
    ["rol"]=>    string(1) "1"
    [3]=>    string(1) "1"
    ["enabled"]=>    string(1) "1"
    [4]=>    string(1) "1"
  }
}



"groupedByFirstField" example:
return an array key-[row group array], where key is the first colum you defined

$usr = new usr();
$usr->ORM->columns = "name,id,mail,rol,enabled"; // sets 'name' like first row 
$usr->ORM->limit = ":limit";
$usr->ORM->parameters = [":limit" => 10];

$list = $usr->ORM->select("groupedByFirstField");

print "<pre>";
var_dump($list);
print "</pre>";

returns:

array(2) {
  ["jane doe"]=>
				  array(8) {
					["id"]=>    string(1) "1"
					[0]=>    string(1) "1"
					["mail"]=>    string(16) "example@some.com"
					[1]=>    string(16) "example@some.com"
					["rol"]=>    string(1) "5"
					[2]=>    string(1) "5"
					["enabled"]=>    string(1) "1"
					[3]=>    string(1) "1"
				  }
  ["Dr. Seuss"]=>
				  array(8) {
					["id"]=>    string(1) "2"
					[0]=>    string(1) "2"
					["mail"]=>    string(9) "Dr. Seuss"
					[1]=>    string(9) "Dr. Seuss"
					["rol"]=>    string(1) "1"
					[2]=>    string(1) "1"
					["enabled"]=>    string(1) "1"
					[3]=>    string(1) "1"
				  }
}




fill Object From an Array

populates the object with an array, match the elements by the key in array and optional uses an alias array for match
its useful for example when te data becomes from $_POST array, in this case we can push all form data to the object

for do it use the method fillObjectFromArray()

parameters:
- array: associative array with the data.
- alias array: storage the relation between object properties and keys in array if some or all not match exactly.


example:


html form:

<form action="postExample.php" method="post">
  name:<br>  <input type="text" name="name" value="Mickey"><br>
  mail:<br> <input type="text" name="mail" value="mouse@mickey.com"><br>
  rol:<br> <input type="text" name="rol" value="3"><br>
  enabled:<br> <input type="text" name="enabled" value="false"><br>
  <br><br>
  <input type="submit" value="Submit">
</form> 


note the value of 'enabled', it going to be cast to bool using this array:

$tinyORM->boolCastingList = 
[
	"true"=>true,
	"yes"=>true,
	"ok"=>true,
	"si"=>true,
	"sí"=>true,
	"1"=>true,
	"false"=>false,
	"not"=>false,
	"no"=>false,
	"0"=>false
];

and rol is going to be cast to int


it happens because since the begin the class was defined with initial values that determines the casting, is only for integer, float and boolean





postExample.php


class usr
{
	public  $id = 1;
	public  $date = '';
	public  $mail = '';
	public  $name = '';
	public  $rol = 0;
	public  $enabled = false;
	
	public $ORM = null;	
	
   function __construct()
   {	   
	   $this->ORM = new tinyORM($this,"users");
   }

}//EOC


$usr = new usr();
$usr->date = date("Y-m-d H:i:s");
$usr->ORM->fillObjectFromArray($_POST);

print "<pre>";
var_dump($usr);
var_dump($_POST);
print "</pre>";


returns:

usr:
object(usr)#7 (7) {
  ["id"]=>  int(1)
  ["date"]=>  string(19) "2019-02-15 19:53:13"
  ["mail"]=>  string(5) "Mouse"
  ["name"]=>  string(6) "Mickey"
  ["rol"]=>  int(3)
  ["enabled"]=>  bool(false)
  
  ["ORM"]=>  object(tinyORM)#8 (1) {
    [0]=>    string(42) "tinyORM for table [users] and object [usr]"
  }
}

post:
array(4) {
  ["name"]=>  string(6) "Mickey"
  ["mail"]=>  string(5) "Mouse"
  ["rol"]=>  string(1) "3"
  ["enabled"]=>  string(0) ""
}





using alias array:

suppose that the names in form are differences that the object properties


$form_alias = [
	"id_user" => "id",
	"mail_user" => "mail",
	"name_user" => "name",
	"rol_user" => "rol",
	"enabled_user" => "enabled"
];



$usr = new usr();
$usr->date = date("Y-m-d H:i:s");
$usr->ORM->fillObjectFromArray($_POST,$form_alias);

print "<pre>";
var_dump($usr);
var_dump($_POST);
print "</pre>";




create the class object automatically from table

if we have created the table in database and sets the connection we can create the class that represents that data for use with this class

using teh method createClass(), which creates a file of the class:  'class.[tableName]_[timestamp].php'

parameters:

- tableName: the table we going to use to read columns for create the class
- prefix: string added to the beginning of the name of every property (optional)
- sufix: string added to the end of the name of every property (optional)


example:


$tinyORM = new tinyORM();
$tinyORM->createClass("users");

resul file 'class.Users_1550283641.php':


<?php

//Adequate the class, delete unnecessary properties or change the names.
class users
{
	public  $id = 0;
	public  $date = '';
	public  $mail = '';
	public  $name = '';
	public  $rol = 0;
	public  $enabled = false;
	public $ORM = null;	

   function __construct()
   {
   	//delete "$alias" if the properties are the same than the colums in table
	//or add the properties that not match, put the name of property with corresponding column name, delete the sobrants
	$alias = [ 
		'' => 'id', 
		'' => 'date', 
		'' => 'mail', 
		'' => 'name', 
		'' => 'rol', 
		'' => 'enabled'
	];
	
	$this->ORM = new tinyORM($this,"users",$alias);
   }
}


cache

the class uses phpfastcache for manage cache, for avoid excesive consults in table maping, 
is important to note that once the tables is mapped the cache stores all data, 
so if you make a change in the table the class will ignore it untill you clean cache. 
in other words delete the folder "/cache" or change the value of "cacheExpiresSeconds" to expire cache.
for example if you are working in a project use 60 seconds, and wen it is in production you can increase the time to expire the cache one day or more..



Query debugging

because the class make the queries internally and manages the data, some times you dont know what is sended to the data base.
by these reason the queries and parameters are pushes in a stack that you can consult for debugging.

the stack is in the property "queryDebug" it can store 10 queryes for default, but you can increse the number with the property "queryDebugSize"

example:

$usr = new usr();

$usr->ORM->select();// be careful, selects all table

print "<pre>";
var_dump($usr->ORM->queryDebug);
print "</pre>";

result:

array(1) {
  [0]=>  array(3) {
		["time"]=>    string(20) "20:49:51 2019-02-15 "
		["query"]=>    string(72) "SELECT `id`, `date`, `mail`, `name`, `rol`, `enabled` FROM `users`;"
		["parameters"]=>    array(0) { }
  }
}


?>


*/


?>