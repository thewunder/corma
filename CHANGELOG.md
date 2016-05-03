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
