# thinkphp5-route-helper
this library help register route auto, write less,make develop better
## introduce
if you have ever use thinkphp5,you fill exhausted ,because if you want register route rule,you must provide it in a file called route.php (maybe call some other name),
this is not a  happy thing,so i write this tool,its format like JAVA web route
## how to use
it is very easy to use this library.
-  create  object of **RouterHelper**
-  call method called **register**

code is here:
```php
$helper=new RouterHelper($namespace,new StandardAnnotationParser());
$config=[
['admin',APP_PATH.'admin'],
['index',APP_PATH.'index']
];
$helper->register($config)
```
*$namespace* specify base namespace for each module,the second param specify a parser,it can parse php annotation,of course ,you can define it if you like

## how to define class or method
to use this library ,you must provide some annotation when you write your php code,
assume we want write a controller class called *User*:
```php
class User{
    /**
    *@method get|post
    *@https true
    *@constraint id \d{1,4}
    **/
    public function getOrder($id){
    }
}
```
code is very simple,the annotation of *getOrder* explain that this method should request by GET or POST,
and http protocal should be https,last,param id should only be number(max length is 4);

## contact
email:saberyjs@gmail.com QQ:1174332406
