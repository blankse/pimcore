<?php

/**
 * Pimcore
 *
 * This source file is available under two different licenses:
 * - GNU General Public License version 3 (GPLv3)
 * - Pimcore Commercial License (PCL)
 * Full copyright and license information is available in
 * LICENSE.md which is distributed with this source code.
 *
 *  @copyright  Copyright (c) Pimcore GmbH (http://www.pimcore.org)
 *  @license    http://www.pimcore.org/license     GPLv3 and PCL
 */

namespace Pimcore\Model;

use Pimcore\Logger;
use Pimcore\Model\Dao\AbstractDao;
use Pimcore\Model\Dao\DaoInterface;
use Pimcore\Model\DataObject\Traits\ObjectVarTrait;

/**
 * @method void beginTransaction()
 * @method void commit()
 * @method void rollBack()
 * @method void configure()
 * @method array getValidTableColumns(string $table, bool $cache)
 * @method void resetValidTableColumnsCache(string $table)
 */
abstract class AbstractModel implements ModelInterface
{
    use ObjectVarTrait;

    /**
     * @var \Pimcore\Model\Dao\AbstractDao|null
     */
    protected $dao;

    private static array $daoClassCache = [];

    private static ?array $daoClassMap = null;

    public function getDao(): DaoInterface
    {
        if (!$this->dao) {
            $this->initDao();
        }

        return $this->dao;
    }

    /**
     * @return $this
     */
    public function setDao(?AbstractDao $dao): static
    {
        $this->dao = $dao;

        return $this;
    }

    /**
     * @param string|null $key
     * @param bool $forceDetection
     *
     * @throws \Exception
     */
    public function initDao(string $key = null, bool $forceDetection = false)
    {
        $myClass = get_class($this);
        $cacheKey = $myClass . ($key ? ('-' . $key) : '');
        $dao = null;
        $myClass = $key ? $key : $myClass;

        if (null === self::$daoClassMap) {
            // static classmap is generated by command: ./bin/console internal:model-dao-mapping-generator
            $map = include(__DIR__ . '/../../config/dao-classmap.php');
            if (is_array($map)) {
                self::$daoClassMap = $map;
            }
        }

        if (!$forceDetection && array_key_exists($cacheKey, self::$daoClassCache)) {
            $dao = self::$daoClassCache[$cacheKey];
        } elseif (!$key || $forceDetection) {
            if (isset(self::$daoClassMap[$myClass])) {
                $dao = self::$daoClassMap[$myClass];
            } else {
                $dao = self::locateDaoClass($myClass);
            }
        } else {
            $delimiter = '_'; // old prefixed class style
            if (str_contains($key, '\\') !== false) {
                $delimiter = '\\'; // that's the new with namespaces
            }

            $dao = $key . $delimiter . 'Dao';
        }

        if (!$dao) {
            Logger::critical('No dao implementation found for: ' . $myClass);

            throw new \Exception('No dao implementation found for: ' . $myClass);
        }

        self::$daoClassCache[$cacheKey] = $dao;

        $dao = '\\' . ltrim($dao, '\\');

        $this->dao = new $dao();
        $this->dao->setModel($this);

        $this->dao->configure();

        if (method_exists($this->dao, 'init')) {
            $this->dao->init();
        }
    }

    public static function locateDaoClass(string $modelClass): ?string
    {
        $forbiddenClassNames = ['Pimcore\\Resource'];

        $classes = class_parents($modelClass);
        array_unshift($classes, $modelClass);

        foreach ($classes as $class) {
            $delimiter = '_'; // old prefixed class style
            if (strpos($class, '\\')) {
                $delimiter = '\\'; // that's the new with namespaces
            }

            $classParts = explode($delimiter, $class);
            $length = count($classParts);
            $daoClass = null;

            for ($i = 0; $i < $length; $i++) {
                $classNames = [
                    implode($delimiter, $classParts) . $delimiter . 'Dao',
                    implode($delimiter, $classParts) . $delimiter . 'Resource',
                ];

                foreach ($classNames as $tmpClassName) {
                    if (class_exists($tmpClassName) && !in_array($tmpClassName, $forbiddenClassNames)) {
                        $daoClass = $tmpClassName;

                        break;
                    }
                }

                if ($daoClass) {
                    break;
                }

                array_pop($classParts);
            }

            if ($daoClass) {
                return $daoClass;
            }
        }

        return null;
    }

    /**
     * @param array<string, mixed>
     *
     * @return $this
     */
    public function setValues(array $data = []): static
    {
        foreach ($data as $key => $value) {
            $this->setValue($key, $value);
        }

        return $this;
    }

    /**
     * @return $this
     */
    public function setValue(string $key, mixed $value): static
    {
        $method = 'set' . $key;
        if (strcasecmp($method, __FUNCTION__) !== 0
            && isset($value)) {
            if (method_exists($this, $method)) {
                $this->$method($value);
            } elseif (method_exists($this, 'set' . preg_replace('/^o_/', '', $key))) {
                // compatibility mode for objects (they do not have any set_oXyz() methods anymore)
                $this->$method($value);
            }
        }

        return $this;
    }

    /**
     * @return array
     */
    public function __sleep()
    {
        $blockedVars = ['dao', 'dirtyFields', 'activeDispatchingEvents'];

        $vars = get_object_vars($this);

        return array_diff(array_keys($vars), $blockedVars);
    }

    /**
     * @param string $method
     * @param array $args
     *
     * @return mixed
     *
     * @throws \Exception
     */
    public function __call(string $method, array $args)
    {
        // protected / private methods shouldn't be delegated to the dao -> this can have dangerous effects
        if (!is_callable([$this, $method])) {
            throw new \Exception("Unable to call private/protected method '" . $method . "' on object " . get_class($this));
        }

        // check if the method is defined in ´dao
        if (method_exists($this->getDao(), $method)) {
            try {
                $r = call_user_func_array([$this->getDao(), $method], $args);

                return $r;
            } catch (\Exception $e) {
                Logger::emergency((string) $e);

                throw $e;
            }
        } else {
            Logger::error('Class: ' . get_class($this) . ' => call to undefined method ' . $method);

            throw new \Exception('Call to undefined method ' . $method . ' in class ' . get_class($this));
        }
    }

    public function __clone()
    {
        $this->dao = null;
    }

    /**
     * @return array
     */
    public function __debugInfo()
    {
        $result = get_object_vars($this);
        unset($result['dao']);

        return $result;
    }

    protected static function getModelFactory(): Factory
    {
        return \Pimcore::getContainer()->get('pimcore.model.factory');
    }

    /**
     * @internal
     *
     * @param array $data
     *
     * @throws \Exception
     */
    protected static function checkCreateData(array $data)
    {
        if (isset($data['id'])) {
            throw new \Exception(sprintf('Calling %s including `id` key in the data-array is not supported, use setId() instead.', __METHOD__));
        }
    }
}
