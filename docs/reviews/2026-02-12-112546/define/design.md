# Consolidate Per-Service Logic into Invalidator Classes

## `modules/CacheInvalidation/CacheCondition.php:24`

> This feels like it's blurring the responsibility of the enum. I don't see anywhere where this is actually used as an enum, and the invalidators also have the `isAvailable()` methods that wrap this `check()` function. I suggest either moving this to a basic utility class with methods like `isRocketActive()` or creating an abstract base class for `Invalidator` with the filter, and each implementation implementing a static method to do the checkout for just that plugin/service. I'd prefer the later, so all the behavior around CloudFront and the CloudFront plugin, for example, is contained within one class, so that if something needs to change related to CloudFront, you only need to change in one place.

# Use the DI Container for Invalidator Instantiation

## `modules/CacheInvalidation/CacheInvalidation.php:46`

> We should be instantiating these through the DI container. These look like singletons, so they can be added via container get. By using the container, we allow ourselves the option to test out different implementations, or even use a different version of one for a given site.

## `modules/CacheInvalidation/CacheInvalidation.php:130`

> In addition to pulling these from the container, it might be clearer to have constant lists of class names at the top of this class, and then in these methods, just loop through the list of class names and map to container get. Might be easier for a reader to understand the different categorizations of invalidators without having to dig through these convenience methods at the bottom.

## `modules/CacheInvalidation/CacheQueue.php:47`

> Seems un-necessary to store the class name in the values of this array, only to re-instantiate it again later in `resolveInvalidator`, especially because these are singletons.

# Introduce a Value Object for Queue Items

## `modules/CacheInvalidation/CacheQueue.php:79`

> We might get a little more type safety here by making a value object called `PendingWriteItem` or something that takes an Invalidator in its constructor. Things like `array_column` will still work on value objects.

## `modules/CacheInvalidation/CacheQueue.php:124`

> If the queue item is a value object, this condition could be a method on it.

## `modules/CacheInvalidation/CacheQueue.php:144`

> If this is a value object, you could add a factory constructor called `refresh()` that does this change and returns a new value object (remember value objects should be immutable). Then you just replace the entire item reference with the new object.
