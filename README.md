[![Build Status](https://api.travis-ci.org/thewunder/corma.svg?branch=master)](https://travis-ci.org/thewunder/corma)
[![Coverage Status](https://coveralls.io/repos/github/thewunder/corma/badge.svg?branch=master)](https://coveralls.io/github/thewunder/corma?branch=master)
[![SensioLabsInsight](https://insight.sensiolabs.com/projects/3ab739ee-d54a-457d-9eec-43261102dfe4/mini.png)](https://insight.sensiolabs.com/projects/3ab739ee-d54a-457d-9eec-43261102dfe4)

Corma
=====

Corma is a high-performance, convention-based ORM based on Doctrine DBAL.

Croute is great because:

* No complex and difficult to verify annotations or configuration files
* Promotes consistent code organization
* Allows for customization through symfony events
* Supports soft deletes
* Can save multiple objects in a single query (using an upsert)
* Makes it easy to cache and avoid database queries
* Loads one-to-many and many-to-many relationships with a method call

Corma doesn't:

* Autoload or lazy load anything
* Have any knowledge of relationships between objects
* Have any Unit of Work concept, everything is executed right away
* Do migrations or code generation

Works in MySql and PostgreSQL.

Install via Composer
--------------------
Via the command line:

    composer.phar require thewunder/corma ~1.0

Or add the following to the require section your composer.json:

    "thewunder/corma": "~1.0"

Basic Usage
-----------
Create a DataObject
```php
namespace YourNamespace\Dataobjects;

class YourDataObject extends DataObject {
    //If the property name == column name on the table your_data_objects it will be saved
    protected $myColumn;

    //Getters and setters..
}
```

And a Repository (optional)
```php
namespace YourNamespace\Dataobjects\Repository;

class YourDataObjectRepository extends DataObjectRepository {
    //Override default behavior and add custom methods...
}
```

Create the orm and use it
```php
$db = DriverManager::getConnection(...); //see Doctrine DBAL docs
$orm = ObjectMapper::withDefaults($db, ['YourNamespace\\Dataobjects']);

$object = $orm->createObject(YourDataObject::class);
//Call setters...
$orm->save($object);
//Call more setters...
$orm->save($object);

//Call more setters on $object...
$objects = [$object];
$newObject = $orm->createObject(YourDataObject::class);
//call setters on $newObject..
$objects[] = $newObject;

$orm->saveAll($objects);

//find existing object by id
$existingObject = $orm->find(YourDataObject::class, 5);

//find existing objects with myColumn >= 42 AND otherColumn = 1
$existingObjects = $orm->findBy(YourDataObject::class, ['myColumn >='=>42, 'otherColumn'=>1], ['sortColumn'=>'ASC']);

//load relationships
$orm->loadOne($existingObjects, OtherObject::class, 'otherObjectId');
$orm->loadMany($existingObjects, AnotherObject::class, 'yourObjectId');
$orm->loadManyToMany($existingObjects, DifferentObject::class, 'link_table');

//delete those
$orm->deleteAll($existingObjects);
```

See [the wiki](https://github.com/thewunder/corma/wiki) for full documentation.
