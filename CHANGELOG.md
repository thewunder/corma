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
