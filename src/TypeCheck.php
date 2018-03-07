<?php
/**
 * Created by PhpStorm.
 * User: saberyjs
 * Date: 18-3-7
 * Time: 上午9:44
 */
namespace saberyjs\tp_route_helper;

class TypeCheck {
    /**
     * @param  $value mixed
     * @return  bool
     * @throws  \InvalidArgumentException
     * **/
    public static function castToBoolean($value){
        if (is_numeric($value)) {
            $value = (int)$value;
            $value = $value !== 0 ? true : false;
        } else {
            if ($value !== 'true' && $value != 'false') {
                throw  new  \InvalidArgumentException("$value  is not valid value");
            }
            $value = $value === 'true' ? true : false;
        }
        return $value;
    }
}