Corma
=====

[![Latest Version on Packagist][ico-version]][link-packagist]
[![Software License][ico-license]](LICENSE.txt)
[![Coverage Status](https://coveralls.io/repos/github/thewunder/corma/badge.svg?branch=master)](https://coveralls.io/github/thewunder/corma?branch=master)

Corma is a high-performance, convention-based ORM based on Doctrine DBAL.

Corma is great because:

* No complex and difficult to verify annotations or configuration files
* Promotes consistent code organization
* Loads and saves one-to-one, one-to-many, and many-to-many relationships with a method call
* Can save multiple objects in a single query (using an upsert)
* Makes it easy to cache and avoid database queries
* Supports soft deletes
* Makes it easy to handle transactions in a Unit of Work
* Highly customizable

Corma doesn't:

* Autoload or lazy load relationships by default
* Do migrations or code generation

Works in MySql and PostgreSQL.

Install via Composer
--------------------

Via the command line:

    composer.phar require thewunder/corma ~4.0

Or add the following to the require section your composer.json:

    "thewunder/corma": "~4.0"

For PHP versions < 8.0 use Corma version ~3.0 

Basic Usage
-----------
Create a DataObject

```php
namespace YourNamespace\Dataobjects;

use Corma\Relationship\ManyToMany;
use Corma\Relationship\OneToMany;
use Corma\Relationship\OneToOne;

class YourDataObject {
    protected $id;

    //If the property name == column name on the table your_data_objects it will be saved
    protected $myColumn;

    protected ?int $otherObjectId = null;
    
    #[OneToOne]
    protected ?OtherObject $otherObject = null;
    
    #[OneToMany(AnotherObject::class)]
    protected ?array $anotherObjects = null;
    
    #[ManyToMany(DifferentObject::class, 'your_data_object_different_link_table')]
    protected ?array $differentObjects = null;
    //Getters and setters..
}
```

And a Repository (optional)
```php
namespace YourNamespace\Dataobjects\Repository;

class YourDataObjectRepository extends ObjectRepository {
    //Override default behavior and add custom methods...
}
```

Create the orm and use it
```php
$db = DriverManager::getConnection(...); //see Doctrine DBAL docs
$orm = ObjectMapper::withDefaults($db, $container); //uses any PSR-11 compatible DI container

$object = $orm->create(YourDataObject::class);
//Call setters...
$orm->save($object);
//Call more setters...
$orm->save($object);

//Call more setters on $object...
$objects = [$object];
$newObject = $orm->create(YourDataObject::class);
//call setters on $newObject...
$objects[] = $newObject;

$orm->saveAll($objects);

//find existing object by id
$existingObject = $orm->find(YourDataObject::class, 5);

//find existing objects with myColumn >= 42 AND otherColumn = 1
$existingObjects = $orm->findBy(YourDataObject::class, ['myColumn >='=>42, 'otherColumn'=>1], ['sortColumn'=>'ASC']);

//load relationships
$orm->load($existingObjects, 'otherObject');
$orm->load($existingObjects, 'anotherObjects');
$orm->load($existingObjects, 'differentObjects');

//delete those
$orm->deleteAll($existingObjects);
```

Documentation
-------------

See [the wiki](https://github.com/thewunder/corma/wiki) for full documentation.

Contributing
------------

Please see [CONTRIBUTING](CONTRIBUTING.md) for details.


[ico-version]: https://img.shields.io/packagist/v/thewunder/corma.svg?style=flat-square
[ico-license]: https://img.shields.io/badge/license-MIT-brightgreen.svg?style=flat-square
[link-packagist]: https://packagist.org/packages/thewunder/corma
