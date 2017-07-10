CiviCRM API Version 4
=====================

Design Principles
-----------------

* **TDD** - tests come first; writing the tests will inform design decisions.
* **Massively Scalable** - built from the outset with a view to support massive scales via asynchronous queues, efficient caching, job prioritisation etc.
* **Clean** - leave all the legacy cruft in v3 and start with a clean slate.
* **Consistent** - uniformity between all entities as much as possible, minimize oddities.
* **Strict** - ditch the aliases, unique names, camelCase conversions, and alternate syntaxes. Params will only be accepted in one format.
* **OOP** - use classes in the \Civi namespace - minimize boilerplate via class inheritance/traits.
* **Discoverable** - params are self-documenting through fluent style and api reflection; no undocumented params
* **Doable** - prioritize new features based on impact and keep scope proportionate to developer capacity.

Input
-----

$params array will be organized into categories, expanding on the "options" convention in v3:

Add complex limiting filters to `get` and various updating actions using `addWhere()` and `addClause()`.

The `addWhere()` method takes filter "triples" [$fieldName, $operator, $criteria].
Operators are one of the basic SQL operators:

* '=', '<=', '>=', '>', '<', 'LIKE', "<>", "!=",
* "NOT LIKE", 'IN', 'NOT IN', 'BETWEEN', 'NOT BETWEEN',
* 'IS NOT NULL', or 'IS NULL'.

```php
// fluent style
\Civi\Api4\Contact::get()
  ->setSelect(['id', 'sort_name'])
  ->addWhere('contact_type', '=', 'Individual')
  ->addOrderBy('sort_name', 'DESC')
  ->setCheckPermissions(TRUE)
  ->execute();

// traditional style
civicrm_api4('Contact', 'get', array(
  'select' => array('id', 'sort_name'),
  'where' => array('contact_type' => 'Individual'),
  'orderBy' => array('sort_name' => 'DESC'),
  'checkPermissions' => TRUE,
));
```

Using the `addClause()` method you can define complex logic trees.
Each call to `addClause()` adds another node to the filter.
A node may be in one of three forms, with the `branch` form allowing nesting:

* leaf: [$fieldName, $operator, $criteria]
* negated: ['NOT', $node]
* branch: ['OR|NOT', [$node, $node, ...]]


```php
// more complex Boolean operations
// (get participation records except for $contact_id at $event_id)
$not_first_participant_result = \Civi\Api4\Participant::get()
  ->setSelect(['id'])
  ->addClause(array('NOT',
    array('AND', array(
      array('event_id', '=', $event_id),
      array('contact_id', '=', $contact_id)))))
  ->execute();

// alternative presentation of above example:
$not_first_participant_result_via_or = \Civi\Api4\Participant::get()
  ->setSelect(['id'])
  ->addClause(array('OR', array(
      array('event_id', '!=', $event_id),
      array('contact_id', '!=', $contact_id))))
  ->execute();
```

Output
------

The php binding returns an [arrayObject](http://php.net/manual/en/class.arrayobject.php). This gives immediate access to the results, plus allows returning additional metadata properties.


```php
$result = \Civi\Api4\Contact::get()->execute();

// you can loop through the results directly
foreach ($result as $contact) {}

// you can just grab the first one
$contact1 = $result->first();

// reindex results on-the-fly (replacement for sequential=1 in v3)
$result->indexBy('id');

// or fetch some metadata about the call
$entity = $result->entity; // "Contact"
$fields = $result->fields; // contact getfields
```

We can do the something very similar in javascript thanks to js arrays also being objects:

```javascript
CRM.api4('Contact', 'get', params).done(function(result) {
  // you can loop through the results
  result.forEach(function(contact, n) {});

  // you can just grab the first one
  var contact1 = result[0];

  // or fetch some metadata about the call
  var entity = result.entity; // "Contact"
});
```

Creating a new action
---------------------

For update operations extend the get class to allow batch operation (paging)

Upgrading from Version 3:
-------------------------

API4 will be a while before it is ready to take over.

These are notable changes:

* Use `$result->indexBy('id');` rather than `sequential => 0`.
* `getSingle` is gone, use `$result->first()`

Feature Wishlist
----------------

### Get Action
* Joins across all FKs and pseudo FKs.

### Error Handling
* Ability to simulate an api call
* Report on all errors, not just the first one to be thrown

The JIRA issue number is [CRM-17867](https://issues.civicrm.org/jira/browse/CRM-17867)

Requirements
------------

CiviCRM version 4.7.13+

Discussion
----------

Use the mailing list http://lists.civicrm.org/lists/info/civicrm-api

Contributing
------------

Create a pull-request, or, for frequent contributors, we can give you direct push access to this repo.

Architecture
------------

The API use embedded magic functions to extend generic PHP OOP approaches and provide easy to use naming, autoloading and self-documentation.
In order for the magic to work, coders extending the API need to use consistent paths, class names and class name-spacing.

API V4 **entities** have both general and specific single class actions.
Specific single-class action class are named `\Civi\Api4\Entity\[$entity]\[ucfirst($action)]`
and generic actions `\Civi\Api4\Action\[ucfirst($action)]`.
Although called as static entity class methods, each action is implemented as its own class courtesy of some magic in
[`Civi\Api4\Entity::__callStatic()`](Civi\Api4\Entity.php).

A series of **action classes** inherit from the base
[`Action`](Civi/Api4/Action.php) class
([`GetActions`](Civi/Api4/Action/GetActions.php),
[`GetFields`](Civi/Api4/Action/GetFields.php),
[`Create`](Civi/Api4/Action/Create.php),
[`Get`](Civi/Api4/Action/Get.php),
[`Update`](Civi/Api4/Action/Update.php),
[`Delete`](Civi/Api4/Action/Delete.php)).

Update actions extend the `Get` class allowing them to perform bulk operations.

The `Action` class uses the magic [__call()](http://php.net/manual/en/language.oop5.overloading.php#object.call) method to `set`, `add` and `get` parameters.
The base action `execute()` method calls the core [`civi_api_kernel`](https://github.com/civicrm/civicrm-core/blob/master/Civi/API/Kernel.php)
service `runRequest()` method. Action objects find their business access objects via [V3 API code](https://github.com/civicrm/civicrm-core/blob/master/api/v3/utils.php#L381).

Each action object has a `_run()` method that accepts a decorated [arrayobject](http://php.net/manual/en/class.arrayobject.php) ([`Result`](Civi/API/Result.php)) as a parameter and is accessed by the action's `execute()` method.

All `action` classes accept an entity with their constructor and use the standard PHP [ReflectionClass](http://php.net/manual/en/class.reflectionclass.php)
for metadata tracking with a custom
[`ReflectionUtils`](Civi/Api4/ReflectionUtils.php) class to extract PHP comments. The metadata is available via `getParams()` and `getParamInfo()` methods. Each action object is able to report its entitiy class name (`getEntity()`) and action verb (`getAction()`).

Each `action` object also has an `$options` property and a set of methods (`offsetExists()`, `offsetGet()`,  `offsetSet()` and `offsetUnset()`) that act as interface to a `thisArrayStorage` property.

The **get** action class uses a [`Api4SelectQuery`](Civi/API/Api4SelectQuery.php) object
(based on the core
[SelectQuery](https://github.com/civicrm/civicrm-core/blob/master/Civi/API/SelectQuery.php)
object which uses
[the V3 API utilities](https://github.com/civicrm/civicrm-core/blob/master/api/v3/utils.php)
and the
[CRM_Utils_SQL_Select](https://github.com/civicrm/civicrm-core/blob/master/CRM/Utils/SQL/Select.php) class)
to execute the query based on the action's `select`, `where`, `orderBy`, `limit` and `offset` parameters.

The **[`GetActions`](Civi/Api4/Action/GetActions.php) action** globs the
`Civi/Api4/Entity/[ENTITY_NAME]` subdirectories of the
`[get_include_path()](http://php.net/manual/en/function.get-include-path.php)`
then the `Civi/Api4/Action` subdirectories for generic actions. In the event
of duplicate actions, only the first is reported.

The **[`GetFields`](Civi/Api4/Action/GetFields.php) action** uses the `[BAO]->fields()` method.

todo: [ActionObjectProvider](Civi/API/Provider/ActionObjectProvider.php),
  implements the
Symfony [EventSubscriberInterface](http://symfony.com/doc/current/components/event_dispatcher.html#using-event-subscribers)
(the single `getSubscribedEvents()` method) and
the CiviCRM [ProviderInterface](https://github.com/civicrm/civicrm-core/blob/master/Civi/API/Provider/ProviderInterface.php) interfaces
(`invoke($apiRequest)`, `getEntityNames($version)` and `getActionNames($version, $entity)`).

The
  [`API kernel`](https://github.com/civicrm/civicrm-core/blob/master/Civi/API/Kernel.php)
, shared with V3 of the API, is constructed with a [Symfony event dispatcher](http://api.symfony.com/3.1/Symfony/Component/EventDispatcher.html)
and a collection of `apiProviders`.

Security
--------

Each `action` object has a `$checkPermissions` property. This is set to `FALSE` for calls from PHP but `TRUE` for calls from REST.


Tests
-----

Tests are located in the `tests` directory (surprise!)
To run the entire Api4 test suite go to the api4 extension directory and type `phpunit4` from the command line.
