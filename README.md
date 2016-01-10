arc/prototype
=============

This component adds prototypes to PHP, with all the javascript features like 
- extend
- assign
- freeze
- observe
etc.


Simple example:

```php5
<?php
    $page = \arc\prototype::create( [
        'title' => 'A page',
        'content' => '<p>Some content</p>',
        'view' => function($args) {
            return '<!doctype html><html><head>' . $this->head($args) 
                 . '</head><body>' . $this->body($args) . '</body></html>';
        },
        'head' => function($args) {
            return '<title>' . $this->title . '</title>';
        },
        'body' => function($args) {
            return '<h1>' . $this->title . '</h1>' . $this->content;
        }
    ] );

    $menuPage = \arc\prototype::extend($page, [
        'body' => function($args) {
            return $this->menu($args) . $this->prototype->body($args);
        },
        'menu' => function($args) {
            $result = '';
            if ( is_array($args['menu']) ) {
                foreach( $args['menu'] as $index => $title ) {
                    $result .= $this->menuItem($index, $title);
                }
            }
            if ( $result ) {
                return \arc\html::ul( ['class' => 'news'], $result );
            }
        },
        'menuItem' => function($index, $title) {
            return \arc\html::li( [], \arc\html::a( [ 'href' => $index ], $title ) );
        }
    ] );
```

Or use a prototype as a dependency injection container:

```php5
<?php
    $di = \arc\prototype::create([
         'dsn'      => 'mysql:dbname=testdb;host=127.0.0.1';
         'user'     => 'dbuser',
         'password' => 'dbpassword',
         'database' => \arc\prototype::memoize( function() {
             // this generates a single PDO object once and then returns it for each subsequent call
             return new PDO( $this->dsn, $this->user, $this->password );
         } ),
         'session'  => function() {
             // this returns a new mySession object for each call
             return new mySession();
         }
    ] );

    $diCookieSession = \arc\prototype::extend( $di, [ 
         'session'  => function() {
             return new myCookieSession();
         }
    ] );
```

Note: PHP has a limitation in that you can never bind a static function to an object. This will result in an uncatchable fatal error. To work around this, you must tell the prototype that a Closure is static, by prefixing the name with a ":". In that case the first argument to that method will always be the current object:

```php5
<?php
    class foo {
        public static function myFactory() {
            return \arc\prototype::create([
                'foo'  => 'Foo',
                ':bar' => function($self) {
                    return $self->foo;
                }
            ]);
        }
    }

    $f = foo::myFactory();
    echo $f->bar(); // outputs: "Foo";
```
Static closure are all closures defined within a static function, or explicitly defined as static. Closures defined outside of a class scope can be bound and don't need this workaround.


methods
-------

###\arc\prototype::create
    (object) \arc\prototype::create( (array) $properties )

Returns a new \arc\prototype\Prototype object with the given properties. The properties array may contain closures, these will be available as methods on the new Prototype object.


###\arc\prototype::extend
    (object) \arc\prototype::extend( (object) $prototype, (array) $properties )

This returns a new Prototype object with the given properties, just like \arc\prototype::create(). But in addition the new object has a prototype property linking it to the original object from which it was extended.
Any methods or properties on the original object will also be accessible in the new object through its prototype chain.

You can check an objects prototype by getting the prototype property of a \arc\prototype\Prototype object. You cannot change this property - it is readonly. You can only set the prototype property by using the extend method.


###\arc\prototype::assign
    (object) \arc\prototype::extend( (object) $prototype, (object) ...$objects )

This returns a new Prototype object with the given prototype set. In addition all properties on the extra objects passed to this method, will be copied to the new Prototype object. For any property that is set on multiple objects, the value of the property in the later object overwrites values from other objects.


###\arc\prototype::freeze
    (void) \arc\prototype::freeze( (object) $prototype )

This makes changes to the given Prototype object impossible, untill you call \arc\prototype::unfreeze($prototype). The object becomes immutable. Any attempt to change the object will silently fail. If you would rather have an exception, use \arc\prototyp::observe() instead and throw an exception there.


###\arc\prototype::unfreeze
    (void) \arc\prototype::unfreeze( (object) $prototype )

This makes the given Prototype object mutable again.


###\arc\prototype::observe
    (void) \arc\prototype::observe( (object) $prototype, (Closure) $f )

This calls the Closure $f each time a property of $prototype is changed. The Closure is called with the prototype object, the name of the property and the new value.
If the closure returns false exactly (no other 'falsy' values will work), the change will be cancelled. 

```php5
<?php
    \arc\prototype::observe($object, function($object, $name, $value) {
        if ( $name === 'youcanttouchthis' ) {
            return false;
        }
    });

```


###\arc\prototype::unobserve
    (void) \arc\prototype::unobserve( (object) $prototype, (Closure) $f )

This removes a specific observer function from a Prototype object. You must pass the exact same closure for this to work.


###\arc\prototype::hasProperty
    (bool) \arc\prototype::hasProperty( (string) $propertyName )

Returns true if the requested property is available on the current Prototype object itself or any of its prototypes.


###\arc\prototype::hasOwnProperty
    (bool) \arc\prototype::hasOwnProperty( (string) $propertyName )

Returns true if the requested property is available on the current Prototype object itself without checking its prototype chain.



###\arc\prototype::hasPrototype
    (bool) \arc\prototype::hasPrototype( (string) $prototypeObject )

Returns true if the given object is part of the prototype chain of the current Prototype object.


###\arc\prototype::memoize
    (Closure) \arc\prototype::memoize( (callable) $f )

Returns a function that will only be run once. After the first run it will then return the value that run returned, unless that value is null. This makes it possible to create lazy loading functions that only run when used. You can also create shared objects in a dependency injection container.

This method doesn't guarantee that the given function is never run more than once - unless you only ever call it indirectly through the resulting closure.
