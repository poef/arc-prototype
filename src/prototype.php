<?php
/*
 * This file is part of the Ariadne Component Library.
 *
 * (c) Muze <info@muze.nl>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
namespace arc;

/**
 * Class lambda
 * Experimental functionality, may be removed later, use at own risk.
 * @package arc
 */
final class prototype {

	private static $frozen = null;

	private static $notExtensible = null;

	private static $instances = null;

	private static $observers = null;

	public static function create($properties) {
		return new prototype\Prototype($properties);
	}

	public static function extend($prototype, $properties) {
		if ( self::isExtensible($prototype) ) {
			if (!isset(self::$instances)) {
				self::$instances = new \SplObjectStorage();
			};
			if (!isset(self::$instances[$prototype])) {
				self::$instances[$prototype] = [];
			}
			$properties['prototype'] = $prototype;
			$instance = new prototype\Prototype($properties);
			$list = self::$instances[$prototype];
			array_push($list,$instance);
			self::$instances[$prototype] = $list;
			return $instance;
		} else {
			return null;
		}
	}

	public static function _destroy($obj) {
		self::unfreeze($obj);
		if ( isset(self::$notExtensible[$obj]) ) {
			unset(self::$notExtensible[$obj]);
		}
		if ( isset(self::$observers[$obj]) ) {
			unset(self::$observers[$obj]);
		}
		if ( isset($obj->prototype) ) {
			unset(self::$instances[$obj->prototype]);
		}
	}

	public static function assign($prototype) {
		$objects = func_get_args();
		array_shift($objects);
		$properties = [];
		foreach ($objects as $obj) {
			$properties = $obj->properties + $properties;
		}
		return self::extend($prototype, $properties);
	}

	public static function freeze($prototype) {
		if (!isset(self::$frozen)) {
			self::$frozen = new \SplObjectStorage();
		}
		self::$frozen[$prototype] = function($name, $value) {
			return false;
		};
		return self::observe($prototype, self::$frozen[$prototype]);	
	}

	public static function unfreeze($prototype) {
		if ( isset(self::$frozen) && isset(self::$frozen[$prototype]) ) {
			self::unobserve($prototype, self::$frozen[$prototype]);
			unset(self::$frozen[$prototype]);
		}
	}

	public static function keys($prototype) {
		$entries = static::entries($prototype);
		return array_keys($entries);
	}

	public static function entries($prototype) {
		return $prototype->properties;
	}

	public static function values($prototype) {
		$entries = static::entries($prototype);
		return array_values($entries);
	}

	public static function hasProperty($prototype, $property) {
		$entries = static::entries($prototype);
		return array_key_exists($property, $entries);
	}

	public static function ownKeys($prototype) {
		$entries = static::ownEntries($prototype);
		return array_keys($entries);
	}

	public static function ownEntries($prototype) {
		$f = \Closure::bind(function() use ($prototype) {
			return $prototype->_ownProperties;
		}, $prototype, $prototype);
		return $f();
	}

	public static function ownValues($prototype) {
		$entries = static::ownEntries($prototype);
		return array_values($entries);
	}

	public static function hasOwnProperty($prototype, $property) {
		$entries = static::ownEntries($prototype);
		return array_key_exists($property, $entries);
	}

	public static function isFrozen($prototype) {
		return isset(self::$frozen[$prototype]);
	}

	public static function isExtensible($prototype) {
		return !isset(self::$notExtensible[$prototype]);
	}

	public static function observe($prototype, $callback) {
		if ( !isset(self::$observers) ) {
			self::$observers = new \SplObjectStorage();
		}
		if ( !isset(self::$observers[$prototype]) ) {
			self::$observers[$prototype] = new \SplObjectStorage();
		}
		self::$observers[$prototype][$callback] = true;
	}

	public static function getObservers($prototype) {
		return (isset(self::$observers[$prototype]) ? self::$observers[$prototype] : [] );
	}

	public static function preventExtensions($prototype) {
		if ( !isset(self::$notExtensible) ) {
			self::$notExtensible = new \SplObjectStorage();
		}
		self::$notExtensible[$prototype] = true;
	}

	public static function unobserve($prototype, $callback) {
		if ( isset(self::$observers) && isset(self::$observers[$prototype]) ) {
			unset(self::$observers[$prototype][$callback]);
		}
	}

	public static function hasPrototype($obj, $prototype) {
        if (!$obj->prototype) {
            return false;
        }
        if ($obj->prototype === $prototype) {
            return true;
        }

        return static::hasPrototype($obj->prototype, $prototype );
	}

	public static function getDescendants($prototype) {
		$instances = self::getInstances($prototype);
		$descendants = $instances;
		foreach ($instances as $instance) {
			$descendants += self::getDescendants($instance);
		}
		return $descendants;
	}

	public static function getInstances($prototype) {
		return (isset(self::$instances[$prototype]) ? self::$instances[$prototype] : [] );
	}

	public static function getPrototypes($obj) {
		$prototypes = [];
		while ( $prototype = $obj->prototype ) {
			$prototypes[] = $prototype;
			$obj = $prototype;
		}
		return $prototypes;
	}

	/**
	 * Returns a new function that calls the given function just once and then simply
	 * returns its result on each subsequent call.
	 * @param callable function to call just once and then remember the result
	 */
	public static function memoize($f) 
	{
		return memoize($f);
	}
}

/**
 * Helper function to make sure that the returned Closure is not defined in a static scope.
 * @param callable function to call just once and then remember the result
 */
function memoize($f) 
{
    return function () use ($f) {
        static $result;
        if (null === $result) {
            if ( $f instanceof \Closure && isset($this) ) {
                $f = \Closure::bind($f, $this);
            }
            $result = $f();
        }
        return $result;
    };
}