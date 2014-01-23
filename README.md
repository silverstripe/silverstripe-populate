# Populate Module

This module provides a way to populate a database from YAML fixtures and custom
classes. For instance, when a building a web application the pages and default
objects can be defined in YAML and shared around developers. This extends the
`requireDefaultRecords` concept in SilverStripe's DataModel.

## Maintainer Contact

 * Will Rossiter (wilr, will.rossiter@dna.co.nz)

## Requirements

 * SilverStripe 3+ [framework](https://github.com/silverstripe/silverstripe-framework)

## Installation Instructions

We normally just use Populate during the development phase of the project. After
the project is live you may wish to remove the module from the repo.

```
composer require "dnadesign/silverstripe-popluate:dev-master"
```

## Setup

In your application `mysite/_config.yml` file, specify the YAML fixtures you 
want to load.
	
	Populate:
	  include_yaml_fixtures:
	    - 'app/fixtures/populate.yml'

If you're sharing test setup with populate, you can specify any number of paths
to load fixtures from.

An example populate.yml might look like the following:

	Page:
		home:
			Title: "Home"
			Content: "My Home Page"
			ParentID: 0

Out of the box, the records will be created on when you run the `PopulateTask` 
through `/dev/tasks/PopulateTask/`. To make it completely transparent to 
developers during the application build, you can also include this to hook in on
`requireDefaultRecords` as part of `dev/build` by including the following in 
one of your application models `requireDefaultRecords` methods:

	public function requireDefaultRecords() {
		parent::requireDefaultRecords();

		Populate::requireRecords();
	}

## Configuration options

*include_yaml_fixtures*

An array of YAML files to parse.
	
	Populate:
	  include_yaml_fixtures:
	    - 'app/fixtures/populate.yml'

*truncate_objects*

An array of ClassName's whose instances are to be removed from the Database 
prior to importing. If you do not want to truncate the entire table, simply want
to update existing records then see Updating Records under YAML Format

## YAML Format

Populate uses the same `FixtureFactory` setup as SilverStripe's unit testing 
framework. The basic structure of which is:

	ClassName:
	  somereference:
	  	FieldName: "Value"

Relations are handled by referring to them by their reference value:

	Member:
	  admin:
	    Email: "admin@site.com"

	Page:
	  homepage:
	    Author: =>Member.admin

Any object which implements the `Versioned` extension will be automatically
published. 

Basic PHP operations can also be included in the YAML file. Any line that is
wrapped in a ` character and ends with a semi colon will be evaled in the 
current scope of the importer.

	Page:
	  mythankyoupage:
	    ThankYouText: `Page::config()->thank_you_text`;
	    LinkedPage: `sprintf("[Page](%s)", HelpPage::get()->first()->Link())`;

### Updating Records

If you do not truncate the entire table, the module will attempt to first look
up an existing record and update that existing record. For this to happen the
YAML must declare the fields to match in the look up. You can use several 
options for this.

#### `PopulateMergeWhen` 

Contains a WHERE clause to match e.g `"URLSegment = 'home' AND ParentID = 0"`.

	HomePage:
	  home:
	    Title: "My awesome homepage"
	    PopulateMergeWhen: "URLSegment = 'home' AND ParentID = 0"

### `PopulateMergeMatch` 

Takes a list of fields defined in the YAML and matches them based on the 
database to avoid repeating content
	
	HomePage:
	  home:
	  	Title: "My awesome homepage"
	  	URLSegment: 'home'
	  	ParentID: 0
	  	PopulateMergeMatch:
	  	  - URLSegment
	  	  - ParentID

### `PopulateMergeAny`

Takes the first record in the database and merges with that. This option is
suitable for things like `SiteConfig` where you normally only contain a single
record.

	SiteConfig:
	  mysiteconfig:
	  	Tagline: "SilverStripe is awesome"
	  	PopulateMergeAny: true

If the criteria meets more than 1 instance, all instances bar the first are 
removed from the database so ensure you criteria is specific enough to get the
unique field value.