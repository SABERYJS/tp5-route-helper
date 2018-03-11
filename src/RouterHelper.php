<?php
/**
 * Created by PhpStorm.
 * User: saberyjs
 * Date: 18-3-6
 * Time: 下午8:45
 */

namespace saberyjs\tp_route_helper;

use saberyjs\annotation\Annotation;
use saberyjs\annotation\Parser;
use saberyjs\annotation\StandardAnnotationParser;
use saberyjs\exception\ClassNotFoundException;
use saberyjs\exception\InvalidConfigException;
use \saberyjs\refl_helper\ReflHelper;
use think\Config;
use think\Route;

class  RouterHelper
{
    /**
     * @var  string
     * **/
    protected $baseNamespace;
    /**
     * @var  Parser
     * **/
    protected $parser;
    protected $cacheFile = RUNTIME_PATH . 'routes.json';

    protected $conditions = ['method', 'alias', 'ext', 'deny_ext', 'https', 'domain', 'before_behavior', 'after_behavior', 'callback', 'merge_extra_vars', 'bind_model', 'cache', 'ajax', 'pjax', 'constraint'];


    /**
     * @param  $namespace string
     * **/
    public function __construct($namespace = null, Parser $parser = null)
    {
        if (empty($namespace)) {
            $namespace = defined('APP_NAMESPACE') && !empty(APP_NAMESPACE) ? APP_NAMESPACE : 'app';
        }
        $this->baseNamespace = $namespace;

        if (empty($parser) || !is_object($parser) || !$parser instanceof Parser) {
            $this->parser = new StandardAnnotationParser();
        }
    }

    /**
     * @return  bool
     * **/
    private function checkDependency()
    {
        if (!class_exists(\Think\Route::class, true)) {
            if (is_file(CORE_PATH . 'Route.php')) {
                require_once CORE_PATH . 'Route.php';
                return true;
            } else {
                return false;
            }
        } else {
            return true;
        }
    }

    /**
     * @param  $config array
     * @return  mixed
     * @throws  \InvalidArgumentException|\RuntimeException
     * **/
    public function register(array $config)
    {
        if (!$this->checkDependency()) {
            throw  new \RuntimeException('sorry!!,this library can only be used with thinkphp5');
        }
        if (!is_array($config)) {
            throw  new \InvalidArgumentException('$config type  error');
        }
        $ret = [];
        if ($this->isCacheOpen() && ($cache = $this->readCache())) {
            $ret = $cache;
        } else {
            foreach ($config as $item) {
                if (!is_array($item)) {
                    throw  new \InvalidArgumentException('$config type  error');
                }
                list($module) = $item;
                $directory = APP_PATH . $module . '/controller';
                if (!is_dir($directory)) {
                    throw  new \RuntimeException("$directory is not a directory");
                }
                $files = scandir($directory);
                //exclude . and ..
                $files = array_filter($files, function ($value) {
                    if ($value === '.' || $value === '..') {
                        return false;
                    } else {
                        return true;
                    }
                });

                $namespace = $this->baseNamespace . '\\' . $module . '\\controller';
                foreach ($files as $file) {
                    $rules = [];
                    $this->handleController($module, $namespace, $directory . '/' . $file, $rules);
                    $ret = array_merge($ret, $rules);
                }
            }
        }
        return $this->castToTp5RouteRule($ret);
    }

    /**
     * @param  $rules array
     * @return  mixed
     * **/
    private function castToTp5RouteRule($rules)
    {
        if (empty($rules)) {
            return null;
        }
        foreach ($rules as $rule) {
            $params = [];
            if (isset($rule['alias'])) {
                $rulePart = [$rule['alias'], $rule['rule']];
            } else {
                $rulePart = $rule['rule'];
            }

            $params[] = $rulePart;
            $params[] = $rule['action'];
            if (isset($rule['method'])) {
                $params[] = $rule['method'];
            } else {
                $params[] = '*';
            }

            $limits = array_filter($rule, function ($key) {
                if (in_array($key, ['method', 'ext', 'deny_ext', 'https', 'domain', 'before_behavior', 'after_behavior', 'callback', 'merge_extra_vars', 'bind_model', 'cache', 'ajax', 'pjax'])) {
                    return true;
                } else {
                    return false;
                }
            });

            $params[] = $limits;

            $params[] = isset($rule['constraint']) ? $rule['constraint'] : [];

            //register to thinkphp5 router

            $this->registerToTp5Router($params);
        }
    }

    /**
     * @param  $params array
     * **/
    private function registerToTp5Router($params)
    {
        call_user_func_array(\Think\Route::class . "::rule", $params);
    }

    public function setCache($params)
    {
        if ($this->isCacheOpen()) {
            if (!is_file($this->cacheFile)) {
                file_put_contents($this->cacheFile, json_encode($params));
            }
        }
    }

    private function isCacheOpen()
    {
        return empty(Config::get('app_debug')) ? true : false;
    }

    private function readCache()
    {
        if ($this->isCacheOpen() && is_file($this->cacheFile)) {
            return json_decode(file_get_contents($this->cacheFile), true, 512);
        } else {
            null;
        }
    }

    private function normalAnnotation($annotation)
    {
        if (empty($annotation)) {
            return [];
        } else {
            $clsConfig = [];
            array_map(function ($value) use (&$clsConfig) {
                $clsConfig[$value['name']] = $value['value'];
            }, array_values($annotation));
            return $clsConfig;
        }
    }

    /**
     * @param $module string
     * @param  $file string
     * @param  $rules array
     * @return  array|null
     * @throws  \RuntimeException
     * **/
    private function handleController($module, $namespace, $file, &$rules)
    {
        if (!is_file($file)) {
            throw new  \RuntimeException("$file is not a real file");
        }
        //get class name from file path
        $fileName = pathinfo($file, PATHINFO_FILENAME);
        //upper first character and concat namespace
        $realClassName = $namespace . '\\' . ucfirst($fileName);
        $errorMsg = '';
        try {
            $classConfig = Annotation::parseAnnotation(Annotation::getAnnotation($realClassName));
            if (empty($classConfig)) {
                $classConfig = [];
            }

            $classConfig = $this->normalAnnotation($classConfig);

            //check if class register automatic
            if (!$this->checkIfClassAutoRegister($classConfig)) {
                //not auto,skip it
                return null;
            }
            $refClass = new \ReflectionClass($realClassName);
            foreach ($refClass->getMethods() as $method) {
                if ($method->isPublic() && !$method->isStatic()) {
                    $methodConfig = $this->normalAnnotation(Annotation::parseAnnotation(Annotation::getAnnotation($method)));
                    if (empty($methodConfig)) {
                        continue;
                    }
                    if (!empty(($rt = $this->handleAnnotation($module, ucfirst($fileName), $method->getName(),
                        !empty($classConfig) ? $classConfig : [], !empty($methodConfig) ? $methodConfig : [])))) {
                        $rules[] = $rt;
                    }
                }
            }
            return $rules;
        } catch (\InvalidArgumentException $iae) {
            $errorMsg = $iae->getMessage();
        } catch (ClassNotFoundException $cnfe) {
            $errorMsg = $cnfe->getMessage();
        } catch (\ReflectionException $re) {
            $errorMsg = $re->getMessage();
        } catch (\RuntimeException $re) {
            $errorMsg = $re->getMessage();
        } catch (\Exception $e) {
            $errorMsg = $e->getMessage();
        } finally {
            if (!empty($errorMsg)) {
                throw  new \RuntimeException($errorMsg);
            }
        }
    }

    /**
     * @param  $reflClass  \ReflectionClass|string|array
     * @return  bool
     * @throws  \RuntimeException|\Exception
     * **/
    private function checkIfClassAutoRegister($reflClass)
    {
        try {
            if (is_string($reflClass)) {
                $reflClass = new \ReflectionClass($reflClass);
            }
            if ($reflClass instanceof \ReflectionClass) {
                $config = Annotation::parseAnnotation(Annotation::getAnnotation($reflClass));
            }

            if (is_array($reflClass)) {
                $config = $reflClass;
            }
            if (!empty($config)) {
                return isset($config['auto']) && !empty($config['auto']) ? (bool)$config['auto'] : true;
            } else {
                //default all controller class register automatic
                return true;
            }
        } catch (\Exception $exception) {
            throw  new \RuntimeException($exception->getMessage());
        }
    }


    /**
     * @param  $classConfig array|null
     * @param  $methodConfig array|null
     * @param  $methodName string
     * @param  $module string
     * @param  $controllerName string
     * @return array|null
     * **/
    private function handleAnnotation($module, $controllerName, $methodName, $classConfig = null, $methodConfig)
    {
        $action = $module . DIRECTORY_SEPARATOR . $controllerName . DIRECTORY_SEPARATOR . $methodName;
        $tempConfig = [];

        //skip it if rule is not set
        if (!isset($methodConfig['rule'])) {
            return null;
        }
        $tempConfig['rule'] = $methodConfig['rule'];
        unset($methodConfig['rule']);
        //check if alias exist
        if (isset($methodConfig['alias'])) {
            $tempConfig['alias'] = $methodConfig['alias'];
            unset($methodConfig['alias']);
        }
        $tempConfig['action'] = $module . '/' . $controllerName . '/' . $methodName;
        $exclude = ['alias'];
        array_map(function ($cond) use ($exclude, $classConfig, $methodConfig, $module, $controllerName, $methodName, &$tempConfig) {
            //filter extra
            if (!in_array($cond, $this->conditions)) {
                return null;
            } else {
                if ($cond === 'alias' || $cond === 'rule' || $cond === 'action') {
                    return;
                }
                if (!isset($methodConfig[$cond])) {
                    if (isset($classConfig[$cond])) {
                        $config = $this->reviseAndGetDefaultValue($cond, $classConfig[$cond]);
                    } else {
                        $config = $this->reviseAndGetDefaultValue($cond);
                    }
                } else {
                    $config = $this->reviseAndGetDefaultValue($cond, $methodConfig[$cond]);
                }
                if ($config === null) {
                    return;
                }

                if ($cond === 'constraint') {
                    for ($i = 0; $i < count($config); $i += 2) {
                        $tempConfig['constraint'][$config[$i]] = $config[$i + 1];
                    }
                } else {
                    $tempConfig[$cond] = $config;
                }
            }
        }, array_values($this->conditions));
        return $tempConfig;
    }

    /**
     * @param  $cond string
     * @param  $value string|null
     * @return  mixed
     * @throws  InvalidConfigException|\InvalidArgumentException
     * **/
    private function reviseAndGetDefaultValue($cond, $value = null)
    {
        $revise = $value !== null;
        switch ($cond) {
            case 'cache':
            case 'before_behavior':
            case 'after_behavior':
            case 'domain':
            case 'deny_ext':
            case 'method':
            case 'ext':
                if (!$revise) {
                    return null;
                } else {
                    return $value;
                }
                break;
            case 'ajax':
            case 'pjax':
            case 'merge_extra_vars':
            case 'https':
                if ($revise) {
                    return TypeCheck::castToBoolean($value);
                } else {
                    return false;
                }
                break;
            case 'callback':
                if (!$revise) {
                    return null;
                }
                //there is something special,because we can call method of instance
                $pattern = '/\[([^,]+),(.+)\]/i';
                if (preg_match($pattern, $value, $match)) {
                    $className = $match[1];
                    $method = $match[2];
                    try {
                        return function () use ($className, $method) {
                            $reflHelper = ReflHelper::getInstance();
                            return $reflHelper->callMethod($reflHelper->get($className), $method);
                        };
                    } catch (\Exception $e) {
                        throw  new \RuntimeException($e->getMessage());
                    }
                } else {
                    return $value;
                }
                break;
            case 'bind_model':
                if (!$revise) {
                    return null;
                }
                $pattern = '/\[([^,]+),(.+)\]/i';
                if (preg_match($pattern, $value, $match)) {
                    $className = $match[1];
                    $key = $match[2];
                    return [$className, $key];
                } else {
                    return $value;
                }
                break;
            case 'constraint':
                if (!$revise) {
                    return null;
                } else {
                    $parts = explode(' ', $value);
                    array_filter($parts, function ($value) use (&$ret) {
                        if (strlen($value) === 0) {
                        } else {
                            $ret[] = $value;
                        }
                    });
                    return $ret;
                }
                break;
            default:
                throw  new \InvalidArgumentException("$cond is not valid config key");
        }
    }

}