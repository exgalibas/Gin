Gin
====
A simple route for PHP base on yii2. You can get the source code easily by downloading file or using composer.

### Composer
If you have composer, just require gin as dependency in your composer.json, like the following.
```
require: {
    "exgalibas/gin": "dev-master"
}
```

### Using
Use the namaspace
```
use \Exgalibas\Gin\Gin;
```
You can add the routing rules for specified http method like GET|HEAD|POST|PUT|PATCH|DELETE|OPTIONS|ANY
```
(new Gin)->get($pattern, $route)
```
### Pattern
The Pattern can be ordinary string, formatting regex with name or original regular expression like:

The name in pattern will be used to replace the route if it has \<name> or transmit as params
```
// formatting regex 
(new Gin)->get("<function:(post|comment)>/<action:(create|update|delete)>/<id:(\d+)>", "<function>")
```
This rule only match "exgalibas/gin/login" and call function named "login"
```
//ordinary string
(new Gin)->get("exgalibas/gin/login", "login")
```
Just be ordinary regular expression, match like "post/create/10" and call function named "create"
```
//original regular
(new Gin)->get("(post|comment)\/(create|delete)\/\d+", "create")
```
### Route
The Route can be Closure, function name or formatting string like "class|function"

### Example
```PHP
use \Exgalibas\Gin\Gin;

$route = new Gin();

//example, match get url "post/10/create", call_user_func(["post", "create"], ["id" => 10])
$route->get("<class:(post|comment)>/<id:(\d+)>/<function:(create|update|delete)>", '<class>|<function>');

//delete route rule
$route->deleteRule("<class:(post|comment)>/<id:(\d+)>/<function:(create|update|delete)>");

//example, match post url "exgalibas/login/10", call closure function([id=>10])
$route->post("exgalibas/login/<id:(\d+)>", function($params){...})

//example, match get url "exgalibas/login/joker", call login(["name" => "joker"])
$route->get("exgalibas/login/<name:joker>", "login")

//parse the request
$route->dispatch();
```

### Error
If dispatch error, exit "404"
