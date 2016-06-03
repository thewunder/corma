Corma
=====

[![Latest Version on Packagist][ico-version]][link-packagist]
[![Software License][ico-license]](LICENSE.txt)
[![Build Status](https://api.travis-ci.org/thewunder/corma.svg?branch=master)](https://travis-ci.org/thewunder/corma)
[![Coverage Status](https://coveralls.io/repos/github/thewunder/corma/badge.svg?branch=master)](https://coveralls.io/github/thewunder/corma?branch=master)
[![SensioLabsInsight](https://insight.sensiolabs.com/projects/3ab739ee-d54a-457d-9eec-43261102dfe4/mini.png)](https://insight.sensiolabs.com/projects/3ab739ee-d54a-457d-9eec-43261102dfe4)

Corma is a high-performance, convention-based ORM based on Doctrine DBAL.

Corma is great because:

* No complex and difficult to verify annotations or configuration files
* Promotes consistent code organization
* Loads and saves one-to-one, one-to-many, and many-to-many relationships with a method call
* Can save multiple objects in a single query (using an upsert)
* Makes it easy to cache and avoid database queries
* Supports soft deletes
* Allows for customization through symfony events

Corma doesn't:

* Autoload or lazy load relationships by default
* Have any Unit of Work concept, everything is executed right away
* Do migrations or code generation

Works in MySql and PostgreSQL.

Install via Composer
--------------------
Via the command line:

    composer.phar require thewunder/corma ~2.0

Or add the following to the require section your composer.json:

    "thewunder/corma": "~2.0"

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

$object = $orm->create(YourDataObject::class);
//Call setters...
$orm->save($object);
//Call more setters...
$orm->save($object);

//Call more setters on $object...
$objects = [$object];
$newObject = $orm->create(YourDataObject::class);
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

##Documentation

See [the wiki](https://github.com/thewunder/corma/wiki) for full documentation.

## Contributing

Please see [CONTRIBUTING](CONTRIBUTING.md) for details.


[ico-version]: https://img.shields.io/packagist/v/thewunder/corma.svg?style=flat-square
[ico-license]: https://img.shields.io/badge/license-MIT-brightgreen.svg?style=flat-square
[ico-downloads]: https://img.shields.io/packagist/dt/thewunder/corma.svg?style=flat-square

[link-packagist]: https://packagist.org/packages/thewunder/corma
[link-downloads]: https://packagist.org/packages/thewunder/corma