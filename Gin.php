<?php
/**
 * @author exgalibas <exgalibas@gmail.com>
 */

namespace Exgalibas\Gin;

/**
 * Class Gin
 * @package Exgalibas\Gin
 */
class Gin
{
    private $_rules = [];
    private $tr = [
        '.' => '\\.',
        '*' => '\\*',
        '$' => '\\$',
        '[' => '\\[',
        ']' => '\\]',
        '(' => '\\(',
        ')' => '\\)',
    ];

    /**
     * @param $method
     * @param $params
     * @return bool
     */
    public function __call($method, $params)
    {
        $verb = "GET|HEAD|POST|PUT|PATCH|DELETE|OPTIONS|ANY";
        $method = strtoupper($method);

        if (!preg_match("/($verb)/", $method) || !isset($params[0],$params[1])) {
            return false;
        }

        $this->_rules[$params[0]]['method'] = $method;
        $this->init($params);
    }

    /**
     * @param $rule
     *
     * init a route pattern, for example:
     *
     * before init
     * ```
     *  $pattern = <controller:(post|comment)>/<action:(create|update|delete)>
     * ```
     *
     * after init
     * ```
     *  $pattern = #^(?P<controller>(post|comment))/(?P<action>(create|update|delete))$#u
     * ```
     */
    private function init($rule) {
        $pattern = $rule[0];
        $route = $rule[1];
        $routeParams = [];
        $params = [];

        //generate routeParams
        if (is_string($route) && strpos($route, '<') !== false && preg_match_all('/<([\w._-]+)>/', $route, $routeMatches)) {
                $routeParams = $routeMatches[1];
        }

        //parse pattern
        if (preg_match_all('/<([\w._-]+):?([^>]+)?>/', $pattern, $patternMatches, PREG_SET_ORDER)) {
            $replace = [];
            foreach ($patternMatches as $patternMatch) {
                $name = $patternMatch[1];
                $subPattern = isset($patternMatch[2]) ? $patternMatch[2] : '[^\/]+';
                $replace["<$name>"] = "(?P<$name>$subPattern)";

                if (!in_array($name, $routeParams, true)) {
                    $params[] = $name;
                }
            }

            $pattern = preg_replace('/<([\w._-]+):?([^>]+)?>/', '<$1>', $pattern);
            $pattern = '#^' . trim(strtr($pattern, array_merge($this->tr, $replace)), '/') .'$#u';
        }

        $this->_rules[$rule[0]] = array_merge($this->_rules[$rule[0]], [
            'pattern' => $pattern,
            'route' => $route,
            'routeParams' => $routeParams,
            'params' => $params,
        ]);
    }

    /**
     * @param $pattern
     *
     * delete a route rule
     */
    public function deleteRule($pattern)
    {
        if (isset($this->_rules[$pattern])) {
            unset($this->_rules[$pattern]);
        }
    }

    /**
     * analyse the url and call the corresponding function
     */
    public function dispatch()
    {
        $result = $this->parseRequest();
        if (!$result) {
            exit('404');
        }
        $route = $result['route'];
        $params = isset($result['params']) && !empty($result['params']) ? $result['params'] : null;

        if ($route instanceof \Closure) {
            $route($params);
        } else if (is_string($route)) {
            if (strpos($route, '|') !== false) {
                list($class, $action) = explode($route, '|', 2);
                call_user_func([$class, $action], $params);
            } elseif (function_exists($route)) {
                call_user_func($route, $params);
            } else {
                exit('404');
            }
        } else {
            exit('404');
        }
    }

    /**
     * @return array|bool
     * parse request and return ['route' => $route, ('params' => $params)]
     */
    private function parseRequest()
    {
        $url = $this->getUrl();
        $method = strtoupper($_SERVER['REQUEST_METHOD']);

        //no using regex
        if (isset($this->_rules[$url]) && in_array($this->_rules[$url]['method'], ['ANY', $method], true)) {
            return ['route' => $this->_rules[$url]['route']];
        }

        //using regex
        foreach ($this->_rules as $rule) {
            if (in_array($rule['method'], ['ANY', $method]) && preg_match($rule['pattern'], $url, $urlMatches)) {
                foreach ($rule['routeParams'] as $routeParam) {
                    isset($urlMatches[$routeParam]) && $routeReplace["<$routeParam>"] = $urlMatches[$routeParam];
                }

                foreach ($rule['params'] as $param) {
                    isset($urlMatches[$param]) && $routeParams[$param] = $urlMatches[$param];
                }

                $route['route'] = $rule['route'];
                isset($routeReplace) && $route['route'] = strtr($route['route'], $routeReplace);
                isset($routeParams) && $route['params'] = $routeParams;

                return $route;
            }
        }

        return false;
    }

    /**
     * @return string
     *
     * get request url
     */
    public function getUrl() {

        $url = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

        if (($pos = strpos($url, '?')) !== false) {
            $url = substr($url, 0, $pos);
        }

        $url = urldecode($url);

        // try to encode in UTF8 if not so
        // http://w3.org/International/questions/qa-forms-utf-8.html
        // 非utf-8的编码,转换成utf-8,just try
        // var_dump("\xA0") and var_dump(utf8_encode("\xA0"))
        if (!preg_match('%^(?:
            [\x09\x0A\x0D\x20-\x7E]              # ASCII
            | [\xC2-\xDF][\x80-\xBF]             # non-overlong 2-byte
            | \xE0[\xA0-\xBF][\x80-\xBF]         # excluding overlongs
            | [\xE1-\xEC\xEE\xEF][\x80-\xBF]{2}  # straight 3-byte
            | \xED[\x80-\x9F][\x80-\xBF]         # excluding surrogates
            | \xF0[\x90-\xBF][\x80-\xBF]{2}      # planes 1-3
            | [\xF1-\xF3][\x80-\xBF]{3}          # planes 4-15
            | \xF4[\x80-\x8F][\x80-\xBF]{2}      # plane 16
            )*$%xs', $url)
        ) {
            $url = utf8_encode($url);
        }

        if (substr($url, 0, 1) === '/') {
            $url = substr($url, 1);
        }

        return (string) $url;
    }

}