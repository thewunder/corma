[![Build Status](https://api.travis-ci.org/thewunder/corma.svg?branch=master)](https://travis-ci.org/thewunder/corma)
[![Coverage Status](https://coveralls.io/repos/github/thewunder/corma/badge.svg?branch=master)](https://coveralls.io/github/thewunder/corma?branch=master)

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

Corma doesn't:

* Autoload or lazy load anything
* Have any knowledge of relationships between objects
* Have any Unit of Work concept, everything is executed right away

Don't use this in production, things will change.

Only tested on MySQL.

Install via Composer
--------------------
Via the command line:

    composer.phar require thewunder/corma *

Or add the following to the require section your composer.json:

    "thewunder/corma": "*"

Basic Usage
-----------
Create a DataObject

    namespace YourNamespace\Dataobjects;

    class YourDataObject extends DataObject {
        //If the property name == column name on the table your_data_objects it will be saved
        protected $myColumn;

        //Getters and setters..
    }

And a Repository

    namespace YourNamespace\Dataobjects\Repository;

    class YourDataObjectRepository extends DataObjectRepository {
        //...
    }

Create the orm and use it

    $db = DriverManager::getConnection(...); //see Doctrine DBAL docs
    $orm = ObjectMapper::create($db, new EventDispatcher(), new ArrayCache(), ['YourNamespace\\Dataobjects']);

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

    //find existing objects
    $existingObjects = $orm->findBy(YourDataObject::class, ['myColumn'=>42], ['sortColumn'=>'ASC']);

    //delete those
    $orm->deleteAll($existingObjects);
