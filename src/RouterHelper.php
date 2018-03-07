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
use think\Exception;

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

    private function  checkDependency(){
        if(!class_exists(\Think\Route::class)){
            return false;
        }else{
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
        if(!$this->checkDependency()){
            throw  new \RuntimeException('sorry!!,this library can only be used with thinkphp5');
        }
        if (!is_array($config)) {
            throw  new \InvalidArgumentException('$config type  error');
        }
        $ret = [];
        foreach ($config as $item) {
            if (!is_array($item) || count($item) < 2) {
                throw  new \InvalidArgumentException('$config type  error');
            }
            list($module, $directory, $namespace) = $item;
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
            foreach ($files as $file) {
                $rules = [];
                if (empty($namespace)) {
                    $namespace = $this->baseNamespace . '\\' . $module . 'controller';
                }
                $this->handleController($module, $namespace, $file, $rules);
                $ret = array_merge($ret, $rules);
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
                $rulePart = $rule['alias'];
            }

            $params[] = $rulePart;
            $params[] = $rule['action'];
            $params[] = $rule['method'];
            $limits = array_filter(array_map(function ($value, $key) {
                if (in_array(['method', 'ext', 'deny_ext', 'https', 'domain', 'before_behavior', 'after_behavior', 'callback', 'merge_extra_vars', 'bind_model', 'cache', 'ajax', 'pjax'], $key)) {
                    return $value;
                } else {
                    return null;
                }
            }, array_values($rule), array_keys($rule)), function ($value) {
                return $value !== null ? true : false;
            });
            $params[]=$limits;

            $params[]=isset($rule['constraint'])?$rule['constraint']:[];

            //register to thinkphp5 router

            $this->registerToTp5Router($params);
        }
    }

    /**
     * @param  $params array
     * **/
    private function registerToTp5Router($params){
        call_user_func_array(\Think\Route::class."::rule",$params);
    }

    /**
     * @param $module string
     * @param  $file string
     * @param  $rules array
     * @return  array|null
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
            //check if class register automatic
            if (!$this->checkIfClassAutoRegister($classConfig)) {
                return null;
            }
            $refClass = new \ReflectionClass($realClassName);
            foreach ($refClass->getMethods() as $method) {
                if ($method->isPublic() && !$method->isStatic()) {
                    $methodConfig = Annotation::parseAnnotation(Annotation::getAnnotation($method));
                    $rules[] = $this->handleAnnotation($module, $realClassName, $method->getName(),
                        !empty($classConfig) ? $classConfig : [], !empty($methodConfig) ? $methodConfig : []);
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
            if ($reflClass instanceof \Reflection) {
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
     * @param $methodConfig array|null
     * @param  $methodName string
     * @param  $module string
     * @param  $controllerName string
     * @param  $methodName string
     * @return  array|null
     * **/
    private function handleAnnotation($module, $controllerName, $methodName, $classConfig = null, $methodConfig)
    {
        $action = $module . DIRECTORY_SEPARATOR . $controllerName . DIRECTORY_SEPARATOR . $methodName;
        $tempConfig = [];
        //skip it if rule is not set
        if (!isset($methodConfig['rule']) && !is_string($methodConfig['rule'])) {
            return null;
        }
        $tempConfig['rule'] = $methodConfig['rule'];

        //check if alias exist
        if (isset($methodConfig['alias'])) {
            $tempConfig['alias'] = $methodConfig['alias'];
        }
        $tempConfig['action'] = $module . '/' . $controllerName . '/' . $methodName;
        $exclude = ['alias'];
        array_map(function ($cond) use ($exclude, $classConfig, $methodConfig, $module, $controllerName, $methodName, &$tempConfig) {
            if (in_array($this->conditions, $cond)) {
                return null;
            } else {
                if (!isset($methodConfig[$cond])) {
                    if (isset($classConfig[$cond])) {
                        $config = $this->reviseAndGetDefaultValue($cond, $classConfig[$cond]);
                    } else {
                        $config = $this->reviseAndGetDefaultValue($cond);
                    }
                } else {
                    $config = $this->reviseAndGetDefaultValue($cond, $methodConfig[$cond]);
                }

                if ($cond === 'constraint') {
                    $tempConfig['constraint'][$config[0]] = $config[1];
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
                //there is something special,because we can call method of instance
                $pattern = '/\[([^,]+),(.+)\]/i';
                if (preg_match($pattern, $value, $match)) {
                    $className = $match[1];
                    $method = $match[2];
                    try {
                        return function () use ($className, $method) {
                            return (ReflHelper::callMethod($className, $method, null));
                        };
                    } catch (\Exception $e) {
                        throw  new \RuntimeException($e->getMessage());
                    }
                } else {
                    return $value;
                }
                break;
            case 'bind_model':
                $pattern = '/\[([^,]+),(.+)\]/i';
                if (preg_match($pattern, $value, $match)) {
                    $className = $match[1];
                    $key = $match[2];
                    return [$className, $key];
                } else {
                    throw new InvalidConfigException("$cond has invalid format");
                }
                break;
            case 'constraint':
                $parts = explode(' ', $value);
                return $parts;
                break;
            default:
                throw  new \InvalidArgumentException("$cond is not valid config key");
        }
    }

}