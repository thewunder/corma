Version 3.4.0
=============

Updates
-----
* Require symfony event dispatcher 5.x
* Require php 7.2 and use object type hints where appropriate
* Stop using deprecated Doctrine Inflector class
* Various minor code cleanup type 

Version 3.3.4
=============

Fixes
-----
* Fix error on loading nullable one-to-one relationships (regression in 3.3.2)

Version 3.3.3
=============

Fixes
-----
* Further work on limiting memory usage

Version 3.3.2
=============

Fixes
-----
* Prevent excessive memory usage in long running scripts by having a cache lifetime in the repository identity map
* Add constants for paged query strategy, throw if an invalid strategy is provided

Version 3.3.1
=============

Fixes
-----
* Fix table aliases and explicit sort by id in seek paged query

Version 3.3.0
=============

New Features
------------
* Add a new Seek / Cursor based implementation of Paged Query

Updates
-------
* Update to phpunit 7
* Run tests against PHP 7.4

Version 3.2.5
=============

Fixes
-----
* Fix infinite loop when using of paged query as an iterator with empty results

Version 3.2.4
=============

Fixes
-----
* Move phpunit dependency to dev

Version 3.2.3
=============

Fixes
-----
* Fixed regression where shared state in identifier broke reading of custom identifier column

Version 3.2.2
=============

Fixes
-----
* Fix PagedQuery ignoring customized id column
* Optimize getting the id column name, speeds up relationship loading 5-10% when annotations are enabled

Updates
-------
* Update to phpunit 6
* Run tests on php 7.3
* Add phpstan

Version 3.2.1
=============

Fixes
-----
* Fix exception when saving one-to-many relationships in Postgres < 9.5
* Fix regression when inserting a new object in the middle of existing objects beings saved in a one-to-many relationship

Version 3.2.0
=============

New Features
------------

* Allow more than one condition on the same column
* Paged Query now implements Iterator for easier iteration over pages of data
* Added optional orderBy parameter for findOneBy

Fixes
-----
* Saving one-to-many relationships where a child moved from one parent to another would result in the moved object being deleted
* Fixed parameter type consistency for orderBy parameter to findBy
* Fixed handling of boolean values for PostgreSQL
* Fixed inflector dependency for newer doctrine DBAL versions

Version 3.1.1
=============

Fixes
-----
* Set an empty array when loading one-to-many relationship without any foreign objects

Version 3.1.0
=============

New Features
------------

* Allow Symfony 4 components
* Tested in PHP 7.2

Version 3.0.2
=============

Fixes
-----

* Fixes exception when using findByIds with a custom id column

Version 3.0.1
=============

Fixes
-----

* Fixed handling of empty paged result sets

Version 3.0
===========

New Features
------------
* PHP 7.1 compatibility
* Remove requirement for data objects to implement the DataObjectInterface, this makes it possible for your business
  objects to be free of dependencies on the ORM.
* Allow for custom object creation and hydration behavior
* Allow for custom table and identifier conventions
* Allow for customizing the table name via the @table annotation
* Allow for a custom id column via the @identifier annotation 
* Allow for custom query modifiers that change queries before they are executed 
* Added convenience method for paged queries to repository base class

Breaking changes
----------------
* Dropped Compatibility for PHP versions < 7.1
* Deleted the DataObject abstract class and DataObjectInterface
* Customizing the table name is done via the @table annotation rather than overriding getTableName method
* RelationshipSaver::saveOne method signature changed for consistency and additional flexibility
* Removed relationship loading methods on repositories
* Corma now requires the fully qualified class name of the object in all cases

Version 2.1.6
===========

Fixes
------
* Fix undefined offset when loadManyToMany fails to load the foreign object

Version 2.1.5
===========

New Features
------
* Support NOT LIKE, BETWEEN, and NOT BETWEEN queries

Version 2.1.4
===========

Fixes
------
* Fix loadOne with objects without an id


Version 2.1.3
===========

New Features
------
* Support NOT IN() queries

Version 2.1.2
===========

Fixes
------
* Fix potential data loss issues when saving a many-to-many relationship

Version 2.1.1
===========

Fixes
------
* Fix infinite loop when saveWith() or saveAllWith() was used in save() / saveAll()

Version 2.1
===========

New Features
------------
* Introduce Unit of Work class to make dealing with transactions easier
* New AggressiveCachingObjectRepository caches the entire table while allowing saves

Fixes
------
* When retrieving a single object null is returned instead of false

Version 2.0.2
=============

Fixes
------
* Fixed bug where columns from another Database / Schema could end up in the list of columns

Version 2.0.1
=============

Fixes
------
* Allow columns to be set from a non-null value to null
* Code is now PSR-2 Compliant
* Added a CONTRIBUTING.md, and other README updates

Version 2.0
===========

New Features
------------
* Introduction of RelationshipSaver to make save saving relationships as easy as loading
* Added saveWith() and saveAllWith() methods to ObjectRepository to wrap saving related objects in a transaction

Breaking changes
----------------
* Changed constructor parameter order for ObjectRepository
* Renamed ObjectMapper::create() to ObjectMapper::withDefaults() as it collided with the method to create data objects
* Renamed ObjectMapper::createObject() to ObjectMapper::create()
