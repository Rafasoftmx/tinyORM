# tinyORM
Simple class to make MySql storage and object mapping, querying and return lists of objects mapped.

  - automatically map object and table
  - inyect data from table to object and vice versa
  - inyect array \__post \__get to object
  - select and return a list of objects

## properties

- **encloseFieldNames**: boolean, if true encloses fields and table names in backticks(\`) in all statements
- **typeCasting**: boolean, if true internally changes the type of data before is assigned to the object for types: integer, float and boolean
- **boolCastingList**: array used for casting boolean that comes as string for example if value comes from database like "yes" or "YES" is casting to TRUE. default values are;

```
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
```

- **queryDebug**: an stack of the queries and parameters executed for the class for debug what is sended to the data base. by default stores 10 queries.
- **queryDebugSize**:  determines the max queries stored in the stack queryDebug. default is 10

### for queries building
- **columns**: comma separated string with the columns you wanna fill the query
- **parameters**: associative array of parameters to use in query statements, [":name" => value]
- **order_by**: order by clause string to use in query
- **limit**: limit clause string to use in query
- **group_by**: group by clause string to use in query
- **having**: group by clause string to use in query

## examples

**set database parameters**
in the file class.pdoDB.php you can configure yor conection:

    class pdoDB
    {

     	var  $db_type="mysql";
    	var  $db_host= "localhost"; //Server host and port if any
    	var  $db_name= "usrs"; //Database name
    	var  $db_usr= "me"; //User name
    	var  $db_pss= "pasword"; //Password

    ...

SQL to create simple table for example:
```
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
```

**note** the id of the table is auto increment and primary key
and the enabled is bit because i want a boolean field, also can be TINYINT(1).
the tables names is 'users'.

the class that represents the tables is:
```
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
```

**note**  i define the default values for the object, because the class remembers it for casting the varible for `integer`, `float` and `boolean`
for a basic example i create an instance of the class 'usr' and use the 'tinyORM' for manage the information in the object.
the constructor neds:

1. object instance
2. table name (if not set take 'object instance' class)
3. array alias for properties names and table column (optional)

basic example:
```
    $usr = new usr();
    $tinyORM = new tinyORM($usr,"users"); //yeah!! we map the object, table  and initialize the class for use them
```
for example if the properties in our object not match with the column names in the table, we can define an array with the relation of properties and column names:
```
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
```
### insert our object to the table
```
    $usr = new usr();
    $tinyORM = new tinyORM($usr,"users");

    //populate the object
    $usr->date = date("Y-m-d H:i:s");
    $usr->mail = "example@some.com";
    $usr->name = "jane doe";
    $usr->rol = 1;
    $usr->enabled = false;

    //insert all data from the object in the DB
    $tinyORM->insert(); // and that's all
```

in the next example we can add all the functionality inside the object including a tinyORM instance in the class  and initialize in constructor function.

```
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
```

### Update our object to the table
```
    $usr = new usr();

    $usr->id = 1; //set the id of the element we want to update
    $usr->date = date("Y-m-d H:i:s");
    $usr->mail = "example@some.com";
    $usr->name = "jane doe";
    $usr->rol = 1;
    $usr->enabled = false;

    $usr->ORM->update();
```
### Delete our object in the table
```
    $usr = new usr();

    $usr->id = 1; //set the ID of the element we want delete
    $usr->ORM->delete();
```
### replace record on the table
this Mysql functionality inserts a record if not exist, but if exist `DELETES` previous and inserts new one with the same PRIMARY KEY or a UNIQUE index.
```
    $usr = new usr();
    $tinyORM = new tinyORM($usr,"users");

    $usr->id = 1;
    $usr->date = date("Y-m-d H:i:s");
    $usr->mail = "example@some.com";
    $usr->name = "jane doe";
    $usr->rol = 1;
    $usr->enabled = true;

    $tinyORM->replace();
```
### upsert record on the table
my version of replace or insert on duplicate key update. Basically checks the existence of a record in table using PRIMARY KEYs, if exist update else insert.
```
    $usr = new usr();
    $tinyORM = new tinyORM($usr,"users");

    $usr->id = 1;
    $usr->date = date("Y-m-d H:i:s");
    $usr->mail = "example@some.com";
    $usr->name = "jane doe";
    $usr->rol = 1;
    $usr->enabled = true;

    $tinyORM->upsert();
```
### load record to the object
```
$usr = new usr();

    $usr->id= 7;// id we want to load
    $usr->ORM->load();

    //show data loaded
    print "<pre>";
    var_dump($usr);
    print "</pre>";
```
### Select records in the database and return a list of objects and useful data structures

- build and execute a select query, returns a list of objects of the class defined
- also return a nested list of objects grouped by a field
- also return a single field Value
- also return a Array of the values of a Column defined
- also return a Array key Value Pairs

parameters:

 1. **type**: tipe of elemet to return;
 ```
"objectList"(default): return a nested list of objects of the class defined
"objectListGroupedByField": return a nested list of objects grouped by a field
"singleValue": return a single field Value
"ArrayColumn": return array of values of a Column defined
"keyValuePairs": Array key-Value Pairs, neets select exactly 2 columns
"indexedUnique": Array key-[row array]
"groupedByFirstField": Array key-[row group array], where key is the first colum you defined
```

2. **option**: in the case of "objectListGroupedByField", "singleValue" and  "ArrayColumn" is used to specify the column name.
3. **addPrimaryKeysInWhere**: ads automatically the primary keys in where clause, is used in load method to make the select.

### make a list of objects
```
    $usr = new usr();
    $list = $usr->ORM->select(); // be careful, selects all table

    print "<pre>";
    var_dump($list);
    print "</pre>";
```
for make a selection we can use different part of statement and send extra parameters to make a query and affects all methods that makes a query.

- where:
- order_by
- limit
- group_by
- having
- columns: for just affect or push in object only the defined columns
- parameters: for parametrise the diferent parts of statements

all of them works exactly like in Mysql, just need avoid put the name of clause. and internally are escaped.

### where example
```
    $usr->ORM->where = "id > 7 AND mail = 'example@some.com'";
```
becomes:
```
    SELECT `id`, `date`, `mail`, `name`, `rol`, `enabled` FROM `users`  WHERE id > 10 AND mail = 'example@some.com';
```
**important note**:
by default automatically all colums and table names are scaped using 'formatIdentifier()' method, by this reason are between backtick (\`),
to avoid this, set encloseFieldNames = false; its can be useful wen uses complex colums.

### parameterized query: (recommended to try avoiding sql injection)
```
    $usr->ORM->where = "id > :id AND mail = :mail";
    $usr->ORM->parameters = [":id" => 7, ":mail" => "example@some.com" ];
```
becomes:
```
    SELECT `id`, `date`, `mail`, `name`, `rol`, `enabled` FROM `users`  WHERE id > :id AND mail = :mail;
```
### columns example

it's useful for select modes that not return an object or when you want populate partially a object. affects all methods that makes a query.
```
    $usr->ORM->columns = "id, mail";
    $usr->ORM->where = "id > :id AND mail = :mail";
    $usr->ORM->parameters = [":id" => 7, ":mail" => "example@some.com"];
```
### order_by example
```
    $usr->ORM->where = "id > :id AND mail = :mail";
    $usr->ORM->parameters = [":id" => 7, ":mail" => "example@some.com" ];

    $usr->ORM->order_by = "id DESC,name ASC,mail";
```
becomes:
```
    SELECT `id`, `date`, `mail`, `name`, `rol`, `enabled` FROM `users`  WHERE id > :id AND mail = :mail ORDER BY `id` DESC , `name` ASC , `mail`;
```
### limit example
```
    $usr->ORM->where = "id > :id AND mail = :mail";
    $usr->ORM->order_by = "id DESC,name ASC,mail";

    $usr->ORM->limit = ":limit";

    $usr->ORM->parameters = [":id" => 7, ":mail" => "example@some.com",  ":limit" => 2 ];
```
**note** that i add ':limit' parameter in the list and the order not matter, nor for parameters neither clauses.

becomes:
```
    SELECT `id`, `date`, `mail`, `name`, `rol`, `enabled` FROM `users`  WHERE id > :id AND mail = :mail    ORDER BY `id` DESC , `name` ASC , `mail`  LIMIT :limit;
```
### group_by example
```
    $usr->ORM->where = "id > :id AND mail = :mail";
    $usr->ORM->order_by = "id DESC";
    $usr->ORM->limit = ":limit";

    $usr->ORM->group_by = "rol,mail";

    $usr->ORM->parameters = [":id" => 7, ":mail" => "example@some.com",  ":limit" => 10 ];
```
becomes:
```
    SELECT `id`, `date`, `mail`, `name`, `rol`, `enabled` FROM `users`  WHERE id > :id AND mail = :mail  GROUP BY `rol`, `mail`   ORDER BY `id` DESC   LIMIT :limit;
```
### having example
```
    $usr->ORM->columns = "*,COUNT(id)";
    $usr->ORM->where = "id > :id";
    $usr->ORM->group_by = "mail";

    $usr->ORM->having = "COUNT(id) > 2";

    $usr->ORM->parameters = [":id" => 7];
```
becomes:
```
    SELECT *, COUNT(id) FROM `users` WHERE id > :id  GROUP BY `mail` HAVING COUNT(id) > 2;
```
**Other Select option for useful data structures**

### "objectListGroupedByField" example
return a nested list of objects grouped by a field.
```
    $usr = new usr();

    $list = $usr->ORM->select("objectListGroupedByField","rol");

    print "<pre>";
    var_dump($list);
    print "</pre>";
```
returns:
```
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
```
### "singleValue" example
return a single field Value.
```
    $usr = new usr();
    $usr->ORM->where = "id = :id";
    $usr->ORM->parameters = [":id" => 7];

    $list = $usr->ORM->select("singleValue","mail");

    print "<pre>";
    var_dump($list);
    print "</pre>";
```
    returns:
```
    string(16) "example@some.com"
```
### "ArrayColumn" example
return an array of all values of a Column defined.
```
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
```
### "keyValuePairs" example
return an array key-Value Pairs, neets select exactly 2 columns.
```
    $usr = new usr();
    $usr->ORM->columns = "name,mail";
    $usr->ORM->limit = ":limit";
    $usr->ORM->parameters = [":limit" => 2];

    $list = $usr->ORM->select("keyValuePairs");

    print "<pre>";
    var_dump($list);
    print "</pre>";
```
returns:
```
    array(2) {
      ["jane doe"]=>  string(16) "example@some.com"
      ["Dr. Seuss"]=>  string(9) "Dr@Seuss"
    }
```
### "indexedUnique" example
return an array key-[row array]
```
    $usr = new usr();
    $usr->ORM->columns = "name,mail";
    $usr->ORM->limit = ":limit";
    $usr->ORM->parameters = [":limit" => 2];

    $list = $usr->ORM->select("indexedUnique");

    print "<pre>";
    var_dump($list);
    print "</pre>";
```
returns:
```
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
```

### "groupedByFirstField" example
return an array key-[row group array], where key is the first colum you defined.
```
    $usr = new usr();
    $usr->ORM->columns = "name,id,mail,rol,enabled"; // sets 'name' like first row
    $usr->ORM->limit = ":limit";
    $usr->ORM->parameters = [":limit" => 10];

    $list = $usr->ORM->select("groupedByFirstField");

    print "<pre>";
    var_dump($list);
    print "</pre>";
```
returns:
```
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
```

### fill Object From an Array
populates the object with an array, match the elements by the key in array and optional uses an alias array for match.
its useful for example when te data becomes from $\_POST array, in this case we can push all form data to the object.

for do it use the method fillObjectFromArray()

parameters:
1. **array**: associative array with the data.
2. **alias array**: storage the relation between object properties and keys in array if some or all not match exactly.

**example**

html form:
```
    <form action="postExample.php" method="post">
      name:<br>  <input type="text" name="name" value="Mickey"><br>
      mail:<br> <input type="text" name="mail" value="mouse@mickey.com"><br>
      rol:<br> <input type="text" name="rol" value="3"><br>
      enabled:<br> <input type="text" name="enabled" value="false"><br>
      <br><br>
      <input type="submit" value="Submit">
    </form>
```
**note** the value of **'enabled'**, it going to be cast to bool using this array:
```
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
```
and **'rol'** is going to be cast to int.

it happens because since the begin the class was defined with initial values that determines the casting, is only for `integer`, `float` and `boolean`

postExample.php
```
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
```
returns:
```
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
```
### using alias array and fill Object From an Array
suppose that the names in form are differences that the object properties.
```
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
```
### create the class object automatically from table
if we have created the table in database and sets the connection we can create the class that represents that data for use with this class.
using teh method createClass(), which creates a file of the class:  'class.[tableName]\_[timestamp].php'.

parameters:
1. tableName: the table we going to use to read columns for create the class
2. prefix: string added to the beginning of the name of every property (optional)
3. sufix: string added to the end of the name of every property (optional)

example:
```
    $tinyORM = new tinyORM();
    $tinyORM->createClass("users");
```
resul file 'class.Users_1550283641.php':
```
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
    ?>
```
### cache
the class uses **phpfastcache** for manage cache, for avoid excesive consults in table maping,
**is important** to note that **once the tables is mapped the cache stores all data**, so if you make a change in the table the class will ignore it untill you clean cache.
in other words delete the folder "/cache" or change the value of "**cacheExpiresSeconds**" to expire cache.
for example if you are working in a project use 60 seconds, and wen it is in production you can increase the time to expire the cache one day or more..

### Query debugging

because the class make the queries internally and manages the data, some times you dont know what is sended to the data base.
by these reason the queries and parameters are pushes in a stack that you can consult for debugging.

the stack is in the property "queryDebug" it can store 10 queryes for default, but you can increse the number with the property "queryDebugSize".

example:
```
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
```
