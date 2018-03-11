# thinkphp5-route-helper
this library help register route auto, write less,make develop better
## introduce
if you have ever use thinkphp5 route ,you will feel exhausted ,because if you want register route rule,you must provide it in a file called route.php (maybe call some other name),
so i write this tool,its his role like JAVA web route.
## how to use
it is very easy to use this library.
-  create  object of **RouterHelper**
-  call method called **register**

code is here:
```php
$routeHelper = new \saberyjs\tp_route_helper\RouterHelper();
$routeHelper->register([
    ['index',APP_PATH]
]);
```
what you need is create a instance of *RouterHelper*,and call *register* method on it

## how to define class or method
to use this library ,you must provide some annotation when you write your controller,
assuming  we want write a controller class called *User*:
```php
/**
 * @auto true
 * @https false
 * **/
class Index
{
    /**
     * @rule /home/:id/:token
     * @alias home
     * @https false
     * @method get|post
     * @ext html|shtml
     * @deny_ext  htm
     * @constraint  id  \d{1,5} token  \w+
     * **/
    public function index($id)
    {
        echo  Route::class;
    }
}
```
code is very simple,the annotation of *getOrder* explain that this method should request by GET or POST,
and http protocal should be https,last,param id should only be number(max length is 4),if you do not remember thinkphp5 route ,you should read it first.

## some tips
when you create RouteHelper,you can provide two args,*$namespace* and *Parser*,$namespace specify base namespace,but you may not provide because library provide a default value(app,it`s value is equal *APP_NAMESPACE* constant).
$parser is a obejct that implement Parser interface,more detail,please look for [AnnotationHeper](https://github.com/SABERYJS/php-annotation-helper),it  is responsible for parse annotation for php code.

## cache routes
in production environment,route rules is cached,actually,it depends on a config item called **app_debug**,if it`s value is equal to false,rules will  be cached in the path(RUNTIME_PATH/routes.json).

## contact
email:saberyjs@gmail.com QQ:1174332406
