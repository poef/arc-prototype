<?php
/*
 * This file is part of the Ariadne Component Library.
 *
 * (c) Muze <info@muze.nl>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
namespace arc\prototype;

final class Prototype
{
    /** 
     * @var array cache for prototype properties
     */
    private static $properties = [];

    /**
     * @var array store for all properties of this instance. Must be private to always trigger __set and observers
     */
    private $_ownProperties = [];

    /**
     * @var array contains a list of local methods that have a static scope, such methods must be prefixed with a ':' when defined.
     */
    private $_staticMethods = [];

    /**
    * @var Object prototype Readonly reference to a prototype object. Can only be set in the constructor.
    */
    private $prototype = null;

    /**
     * @param array $properties
     */
    public function __construct($properties = [])
    {
        foreach ($properties as $property => $value) {
            if ( !is_numeric( $property ) && $property!='properties' ) {
                 if ( $property[0] == ':' ) {
                    $property = substr($property, 1);
                    $this->_staticMethods[$property] = true;
                    $this->_ownProperties[$property] = $value;
                } else if ($property == 'prototype') {
                    $this->prototype = $value;
                } else {
                    $this->_ownProperties[$property] = $this->_bind( $value );
                }
            }
        }
    }

    /**
     * @param $name
     * @param $args
     * @return mixed
     * @throws \arc\ExceptionMethodNotFound
     */
    public function __call($name, $args)
    {
        if (array_key_exists( $name, $this->_ownProperties ) && is_callable( $this->_ownProperties[$name] )) {
            if ( array_key_exists($name, $this->_staticMethods) ) {
                array_unshift($args, $this);
            }
            return call_user_func_array( $this->_ownProperties[$name], $args );
        } elseif (is_object( $this->prototype)) {
            $method = $this->_getPrototypeProperty( $name );
            if (is_callable( $method )) {
                if ( array_key_exists($name, $this->_staticMethods) ) {
                    array_unshift($args, $this);
                }
                return call_user_func_array( $method, $args );
            }
        }
        throw new \arc\ExceptionMethodNotFound( $name.' is not a method on this Object', \arc\exceptions::OBJECT_NOT_FOUND );
    }

    /**
     * @param $name
     * @return array|null|Object
     */
    public function __get($name)
    {
        switch ($name) {
            case 'prototype':
                return $this->prototype;
            break;
            case 'properties':
                return $this->_getPublicProperties();
            break;
            default:
                if ( array_key_exists($name, $this->_ownProperties) ) {
                    return $this->_ownProperties[$name];
                }
                return $this->_getPrototypeProperty( $name );
            break;
        }
    }

    /**
     * Returns a list of publically accessible properties of this object and its prototypes.
     * @return array
     */
    private function _getPublicProperties()
    {
        // get public properties only, so use closure to escape local scope.
        // the anonymous function / closure is needed to make sure that get_object_vars
        // only returns public properties.
        return ( is_object( $this->prototype )
            ? array_merge( $this->prototype->properties, $this->_getLocalProperties() )
            : $this->_getLocalProperties() );
    }

    /**
     * Returns a list of publically accessible properties of this object only, disregarding its prototypes.
     * @return array
     */
    private function _getLocalProperties()
    {
        //$getLocalProperties = \Closure::bind(function ($o) {
        //    return get_object_vars($o);
        //}, new dummy(), new dummy());
        return [ 'prototype' => $this->prototype ] + $this->_ownProperties;
    }

    /**
     * Get a property from the prototype chain and caches it.
     * @param $name
     * @return null
     */
    private function _getPrototypeProperty($name)
    {
        if (is_object( $this->prototype )) {
            // cache prototype access per property - allows fast but partial cache purging
            if (!array_key_exists( $name, self::$properties )) {
                self::$properties[ $name ] = new \SplObjectStorage();
            }
            if (!self::$properties[$name]->contains( $this->prototype )) {
                $property = $this->prototype->{$name};
                if ( $property instanceof \Closure ) {
                    if ( !array_key_exists($name, $this->prototype->_staticMethods)) {
                        $property = $this->_bind( $property );
                    } else {
                        $this->_staticMethods[$name] = true;
                    }
                }
                self::$properties[$name][ $this->prototype ] = $property;
            }
            return self::$properties[$name][ $this->prototype ];
        } else {
            return null;
        }
    }

    /**
     * @param $name
     * @param $value
     */
    public function __set($name, $value)
    {
        if (!in_array( $name, [ 'prototype', 'properties' ] )) {
            $observers = \arc\prototype::getObservers($this);
            $continue = true;
            foreach($observers as $observer) {
                $result = $observer($this, $name, $value);
                if ($result === false) {
                    $continue = false;
                }
            }
            if ($continue) {
                if (!array_key_exists($name, $this->_staticMethods)) {
                    $this->_ownProperties[$name] = $this->_bind( $value );
                } else {
                    $this->_ownProperties[$name] = $value;
                }
                // purge prototype cache for this property - this will clear too much but cache will be filled again
                // clearing exactly the right entries from the cache will generally cost more performance than this
                unset( self::$properties[ $name ] );
            }
        }
    }

    /**
     * @param $name
     * @return bool
     */
    public function __isset($name)
    {
        $val = $this->_getPrototypeProperty( $name );

        return isset( $val );
    }

    /**
     * @param $name
     */
    public function __unset($name) {
        if (!in_array( $name, [ 'prototype', 'properties' ] )) {
            $observers = \arc\prototype::getObservers($this);
            $continue = true;
            foreach($observers as $observer) {
                $result = $observer($this, $name, null);
                if ($result === false) {
                    $continue = false;
                }
            }
            if ($continue) {
                if (array_key_exists($name, $this->_staticMethods)) {
                    unset($this->_staticMethods[$name]);
                }
                unset($this->_ownProperties[$name]);
                // purge prototype cache for this property - this will clear too much but cache will be filled again
                // clearing exactly the right entries from the cache will generally cost more performance than this
                unset( self::$properties[ $name ] );
            }
        }
    }

    /**
     *
     */
    public function __destruct()
    {
    	\arc\prototype::_destroy($this);
        return $this->_tryToCall( $this->__destruct );
    }

    /**
     * @return mixed
     */
    public function __toString()
    {
        return $this->_tryToCall( $this->__toString );
    }

    /**
     * @return mixed
     * @throws \arc\ExceptionMethodNotFound
     */
    public function __invoke()
    {
        if (is_callable( $this->__invoke )) {
            return call_user_func_array( $this->__invoke, func_get_args() );
        } else {
            throw new \arc\ExceptionMethodNotFound( 'No __invoke method found in this Object', \arc\exceptions::OBJECT_NOT_FOUND );
        }
    }

    /**
     *
     */
    public function __clone()
    {
        // make sure all methods are bound to $this - the new clone.
        foreach (get_object_vars( $this ) as $property) {
            $this->{$property} = $this->_bind( $property );
        }
        $this->_tryToCall( $this->__clone );
    }

    /**
     * Binds the property to this object
     * @param $property
     * @return mixed
     */
    private function _bind($property)
    {
        if ($property instanceof \Closure ) {
            // make sure any internal $this references point to this object and not the prototype or undefined
            return \Closure::bind( $property, $this );
        }

        return $property;
    }

    /**
     * Only call $f if it is a callable.
     * @param $f
     * @param array $args
     * @return mixed
     */
    private function _tryToCall($f, $args = [])
    {
        if (is_callable( $f )) {
            return call_user_func_array( $f, $args );
        }
    }
}

/**
 * Class dummy
 * This class is needed because in PHP7 you can no longer bind to \stdClass
 * And anonymous classes are syntax errors in PHP5.6, so there.
 * @package arc\lambda
 */
class dummy {
}