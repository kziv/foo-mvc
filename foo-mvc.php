<?php
/**
 * foo-mvc
 * Lightweight MVC framework
 * @author Karen Ziv <me@karenziv.com>
 */

/**
 * Pretty debug output
 */
function print_h($var) {
  echo "<pre>";
  print_r($var);
  echo "</pre>";
}

/**
 * Core class
 */
class FooMVC {

  protected static $route_file;            // Path to router

  protected static $base_url;              // The base URL path of the FooMVC instance (after the domain name). This is usually empty.
  protected static $dir_controllers;       // Absolute path to the controllers directory
  protected static $dir_models;            // Absolute path to the models directory
  protected static $dir_views;             // Absolute path to the views directory

  protected static $suffix_controller = '_Controller';
  protected static $suffix_model      = '_Model';
  protected static $suffix_view       = '_View';

  public static $current_view; // Name of the view to be rendered

  /**
   * This is a static class and as such should never be instantiated
   */
  public function __construct() {
    throw new Exception(__CLASS__ . " is a static class");
  }

  /**
   * Main dispatcher
   * @param string Path to application files relative to the foo-mvc base
   */
  public static function dispatch($app_path=NULL) {

    $app_path              = dirname(__FILE__) . '/apps' . ($app_path ? '/' . $app_path : '');
    self::$dir_controllers = $app_path . '/controllers/';
    self::$dir_models      = $app_path . '/models/';
    self::$dir_views       = $app_path . '/views/';

    if (!self::$route_file) {
      self::$route_file = $app_path. '/routes.ini';
    }

    $router = new Router(self::$route_file);
    $controller_name = $router->url2controller($_SERVER['REQUEST_URI']);
    $controller_name = str_replace('.', '_', $controller_name);
    self::forward($controller_name);

    self::runView();
  }

  public function setRouteFile($path) {
    self::$route_file = $path;
  }

  /**
   * Switches to another controller from the current one
   * @param string Name of controller to run (e.g. 'Foo_Bar');
   * @param bool   Whether or not to return to the current controller when the new one is done
   */
  public static function forward($controller, $return=FALSE) {

    // If we're not returning, the
    if (!$return) {
      self::$current_view = strtolower($controller);
    }

    $controller_name = self::loadController($controller);
    $controller = new $controller_name();
    $controller->action();

    // This prevents continuing the chain of execution back up the chain of forwarding
    if (!$return) {
      self::runView();
      exit;
    }
  }

  /**
   * Loads and validates a controller
   * Ensures that a controller file exists, loads the controller file,
   * ensures that it is properly named and extends the base class and
   * has the action() method
   * @param  string Controller name (e.g. Foo_Bar)
   * @return string Validated controller name (e.g. Foo_Bar_Controller)
   */
  public static function loadController($controller_name) {

    $controller_name = strtolower($controller_name);

    // Load the controller file
    $controller_path = self::$dir_controllers . str_replace('_', '/', $controller_name) . '.php';
    if (!file_exists($controller_path)) {
      throw new FooMVCDispatchException("Controller file expected at '$controller_path'");
    }
    require_once $controller_path;

    // Validate the controller
    $controller_name .= self::$suffix_controller;
    if (!class_exists($controller_name)) {
      throw new FooMVCDispatchException("Controller '$controller_name' expected at '$controller_path'");
    }
    if (!is_subclass_of($controller_name, 'FooMVCController')) {
      throw new FooMVCDispatchException ("Controller '$controller_name' must extend FooMVCController");
    }
    if (!method_exists($controller_name, 'action')) {
      throw new FooMVCDispatchException ("Controller '$controller_name' has no action");
    }
    return $controller_name;

  }

  /**
   * Loads, validates and runs the current view
   */
  public static function runView() {

    // Load the view file
    self::$current_view = strtolower(self::$current_view);
    $view_path = self::$dir_views . str_replace('_', '/', self::$current_view) . '.php';
    if (!file_exists($view_path)) {
      throw new FooMVCDispatchException("View file expected at '$view_path'");
    }
    require_once $view_path;

    // Validate the view
    $view_name = self::$current_view . self::$suffix_view;
    if (!class_exists($view_name)) { // Class is in the file
      throw new FooMVCDispatchException("View '$view_name' expected at '$view_path'");
    }
    if (!is_subclass_of($view_name, 'FooMVCView')) { // View extends base view
      throw new FooMVCDispatchException ("View '$view_name' must extend FooMVCView");
    }
    if (!method_exists($view_name, 'render')) { // View has appropriate method
      throw new FooMVCDispatchException ("View '$view_name' has no render() method");
    }

    $view = new $view_name();
    $view->render();

  }

  /**
   * Loads and validates a model
   * @param  string Name of model (e.g. Object)
   * @return string Class name of validated and included model
   */
  public static function loadModel($model_name) {

    // Load the model file
    $model_name = strtolower($model_name);
    $model_path = self::$dir_models . str_replace('_', '/', $model_name) . '.php';
    if (!file_exists($model_path)) {
      throw new FooMVCDispatchException("Model file expected at '$model_path'");
    }
    require_once $model_path;

    // Validate the model
    $model_name = $model_name . self::$suffix_model;
    if (!class_exists($model_name)) { // Class is in the file
      throw new FooMVCDispatchException("Model '$model_name' expected at '$model_path'");
    }
    if (!is_subclass_of($model_name, 'FooMVCModel')) { // Model extends base model
      throw new FooMVCDispatchException ("Model '$model_name' must extend FooMVCModel");
    }
    return $model_name;
  }

}

/**
 * Custom exception for dispatching errors
 * Use this exception when any problems occur with the lookup,
 * inclusion, or instantiation of any of the dispatched items
 */
class FooMVCDispatchException extends Exception {}

/**
 * Base class of all FooMVC controllers
 */
abstract class FooMVCController {

  public function __construct() {}

  /**
   * Transfers the controller action to a new controller
   * @param string Name of controller to switch to (e.g. Foo.Bar)
   * @param bool   Whether or not to return to the previous controller when complete (default: FALSE)
   */
  protected final function forward($controller_name, $return=FALSE) {
    $controller_name = str_replace('.', '_', $controller_name);
    FooMVC::forward($controller_name, $return);
  }

  /**
   * Main execution block - must be implemented by each controller
   */
  abstract function action();

  /**
   * Passes a variable to the view data container
   * @param string
   * @param mixed
   */
  public final function setData($key, $val) {

  }

  /**
   * Overrides the default view for this controller
   * @param string Name of view in dot notation (e.g. Foo.Bar would be view Bar in folder views/foo)
   */
  public final function setView($view_name) {
    $view_name = str_replace('.', '_', $view_name);
    FooMVC::$current_view = $view_name;
  }

  /**
   * Loads a model for use in a controller
   * @param  string Model name in dot notation (e.g. Foo.Bar would be model Bar in folder models/foo)
   * @return string Name of model class
   */
  public final function loadModel($model_name) {
    $model_name = str_replace('.', '_', $model_name);
    return FooMVC::loadModel($model_name);
  }

}

/**
 * Base class of all FooMVC models
 */
abstract class FooMVCModel {}

/**
 * Base class of all FooMVC views
 */
abstract class FooMVCView {

  protected $data = array(); // Data packet to send to template

}

class Router {

  protected $routes = array();
  protected $urlparams = array();

  public function __construct($ini_path=NULL) {
    // Set the default routes path
    if (!$ini_path) {
      $ini_path = dirname(__FILE__) . '/routes.ini';
    }
    $this->loadRoutesFile($ini_path);
  }

  /**
   * Loads a router file and parses the route map for reading
   * @param  string Path (absolute or relative) to routes file
   * @throws RouterException if file isn't found
   */
  protected function loadRoutesFile($ini_path) {

    // Get the raw routes from the ini file
    if (!file_exists($ini_path)) {
      throw new RouterException("Router file expected at '$ini_path'");
    }
    $routes = parse_ini_file($ini_path);
    print_h($routes); // DEBUG

    // For each route...
    foreach ($routes as $raw_route => $controller) {

      if (!$raw_route) {
        $this->routes['controller'] = $controller;
        return;
      }

      $cur_route = &$this->routes;

      // Traverse the route and parse into a searchable tree
      // We do this instead of a regex because there could
      // potentially be hundreds of maps with var replacement
      // and regexes are heavy processing. This map can be cached.
      $raw_tokens = explode('/', $raw_route);
      foreach ($raw_tokens as $i => $token) {

        // Are we on the last token in the URL?
        $is_last_token = $i == (count($raw_tokens) - 1);

        // If the current route doesn't have a subcontrollers section, create and traverse to it
        if (!isset($cur_route['sub'])) {
          $cur_route['sub'] = array();
        }
        $cur_route = &$cur_route['sub'];

        // Determine if the current token is a var or standard
        // Vars are in format :var
        if (strpos($token, ':') === FALSE) { // Standard token
          $array_token = $token;
          $urlparams = array();
        }
        else { // Variable token
          $array_token = ':';
          $urlparams = array('var' => substr($token, 1));
        }

        if (isset($cur_route[$array_token])) {
          // Make sure that all variable tokens in the same location in the same path are named the same
          // This prevents multiple routes created for the same replacement location
          // (e.g. For route foo/:bar/baz, foo/:bar/quux is correct, but not foo/:var/quux)
          if ($array_token == ':' && ($cur_route[$array_token]['var'] != substr($token, 1))) {
            trigger_error("ROUTER: Token '" . substr($token, 1) . "' differs from same level variable tokens");
            return;
          }
        }
        else {
          $cur_route[$array_token] = $urlparams;
        }

        // If this is the last token in the URL, assign the controller
        if ($is_last_token) {
          $cur_route[$array_token]['controller'] = $controller;
        }

        // Traverse one more step down the URL map
        $cur_route = &$cur_route[$array_token];
      }

    }

    print_h($this->routes); // DEBUG

  }

  /**
   * Finds the controller for a given URL
   * Also populates the $urlparams variable
   * @param  string URL to find a controller for
   * @params bool   If a route isn't found, use the URL as the controller name (e.g. foo/bar => Foo_Bar)
   This allows you to use routes.ini only for routes that deviate from that pattern
   * @return string Controller name
   */
  public function url2controller($url, $path_fallback=TRUE) {

    // Split the URL on slashes and remove empty entries
    $tokenized_url = explode('/', $url);
    $tokenized_url = array_filter($tokenized_url, 'strlen');

    $cur_route = $this->routes; // Set the first current route as the head of the routes map

    // Create the fallback controller
    $fallback = $path_fallback ? implode('_', $tokenized_url) : FALSE;

    // For each part of the URL...
    foreach ($tokenized_url as $i => $token) {

      // If the route map path we're on has subcontrollers...
      if (isset($cur_route['sub'])) {
        $cur_route = $cur_route['sub']; // Traverse down into the subcontrollers
        // Find the subcontroller route to take
        if (isset($cur_route[$token])) { // Token found
          $cur_route = &$cur_route[$token];
        }
        elseif (isset($cur_route[':'])) { // Look for vars at the same level
          $cur_route = &$cur_route[':'];
          $this->urlparams[$cur_route['var']] = $token;
        }
        else {
          return $fallback;
        }
      }
      else {
        return $fallback;
      }
    }

    // If we've found a controller...
    if (isset($cur_route['controller'])) {

      // If there's no vars to replace in the controller name, just return it
      if (strpos($cur_route['controller'], ':') === FALSE) {
        return $cur_route['controller'];
      }

      // Replace all controller vars with their URL var counterparts, if they exist
      $controller_tokens = explode('.', $cur_route['controller']);
      foreach ($controller_tokens as $i => $token) {
        if (strpos($token, ':') === 0) { // If this token is a controller var
          $var = substr($token, 1);
          if (isset($urlparams[$var])) {
            $controller_tokens[$i] = $urlparams[$var];
          }
          else {
            trigger_error("No matching URL param '$token' in controller '" . $cur_route['controller']);
            return $fallback;
          }
        }
      }

      return implode('.', $controller_tokens);

    }
    return FALSE;

  }

  public function controller2url($controller) {

  }
}

class RouterException extends Exception {}
