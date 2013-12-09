Yii MongoDB Driver
==================

This is an extension is driver for MongoDB for Yii Framework 1.x. This extension was forked from somewhere long time ago and now actively developed and supported by me.

## Versions
Current stable release version is 1.0.0. You can download it from [here](https://github.com/fromYukki/Yii-MongoDB-Driver/archive/v1.0.0.zip).

## Installation
You need to clone or [download](https://github.com/fromYukki/Yii-MongoDB-Driver/archive/master.zip) this repo, then unpack it into your **extensions** folder (`protected/extensions/mongoDb`) and add some lines in your `protected/config/main.php` config file:
```php
        // Description of components
        'components' => array(
            // Database
            'mongoDb' => array(
                'class' => 'ext.mongoDb.YMongoClient',
                'server' => 'mongodb://localhost:27017',
                'dbName' => 'database_name',
            ),
        ),
```
Here is some variables that you can pass to the component:

* **server** - It is connection string. This parameter will be passed to the MongoClient  [constructor](http://www.php.net/manual/en/mongoclient.construct.php);
* **dbName** - Name of database inside MongoDb;
* **options** - Options for the MongoClient [constructor](http://www.php.net/manual/en/mongoclient.construct.php). By default you need not to change this options. The default value is `array('connect' => true)`;
* **readPreference** - Specifies the [read preference](http://www.php.net/manual/en/mongo.readpreferences.php) type. The default value is `MongoClient::RP_PRIMARY`;
* **readPreferenceTags** - Specifies the read [preference tags](http://www.php.net/manual/en/mongo.readpreferences.php) as an array of strings. The default value is `array()`;
* **w** - The w option specifies the Write Concern for the driver, which determines how long the driver blocks when writing. The default value is **1**;
* **j** - The write will be acknowledged by primary and the journal flushed to disk. The default is **false**;
* **enableProfiling** - The same functionality as `CDbConnection` driver.

## Basic features
* Working with Mongo document like with **ActiveRecord model**;
* **Nested documents** (sub documents) support;
* **Relations** support;
* **Validation** of root and nested documents;
* **Scopes** support;
* **DataProvider** support;
* **Command builder** with a lot of funtions like `CDbCommand` support;
* **HTTP Session** storage extended from `CHttpSession`;
* **Cache driver** extended from `CCache`;
* **Soft delete behaviour** support and 5+ other useful behaviours support.

## Documentation
Full documentation and a lot of examples you can find at the [Wiki pages](https://github.com/fromYukki/Yii-MongoDB-Driver/wiki/Yii-MongoDB-Driver-Documentation).

## Bug tracker
If you find any bugs, please create an issue at [Issue tracker for project](https://github.com/fromYukki/Yii-MongoDB-Driver/issues) GitHub repository.