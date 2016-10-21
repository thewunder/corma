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
