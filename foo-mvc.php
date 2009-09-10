<?php
/**
 * foo-mvc
 * Lightweight MVC framework
 * @author Karen Ziv <karen@perlessence.com>
 **/
FooMVC::dispatch();

/**
 * Pretty debug output
 **/
function print_h($var) {
    echo "<pre>";
    print_r($var);
    echo "</pre>";
}

/**
 * Core class
 **/
class FooMVC {

    protected static $router; // Router object

    protected static $base_url = '/foo-mvc'; // The base URL path of the FooMVC instance (after the domain name). This is usually empty.
    protected static $base_path;             // Absolute path to the location of FooMVC
    protected static $dir_controllers;       // Absolute path to the controllers directory
    protected static $dir_models;            // Absolute path to the models directory
    protected static $dir_views;             // Absolute path to the views directory

    protected static $suffix_controller = '_Controller';
    protected static $suffix_model      = '_Model';
    protected static $suffix_view       = '_View';

    protected static $current_view; // Name of the view to be rendered

    /**
     * This is a static class and as such should never be instantiated
     **/
    public function __construct() {
        throw new Exception(__CLASS__ . " is a static class");
    }
    
    /**
     * Main dispatcher
     **/
    public static function dispatch() {

        self::$base_path       = dirname(__FILE__);
        self::$dir_controllers = self::$base_path . '/controllers/';
        self::$dir_models      = self::$base_path . '/models/';
        self::$dir_views       = self::$base_path . '/views/';
        
        // Basic routing
        // TODO - replace with Router class
        // Get the rest of the URL after any base_url values
        if (self::$base_url) {
            $path = substr($_SERVER['REQUEST_URI'], strlen(self::$base_url));
        }
        $path = explode('/', $path);           // Split on the slash
        $path = array_filter($path, 'strlen'); // Remove empty entries
        
        $controller_name = implode('_', $path);
        self::forward($controller_name);

        self::runView();
    }

    /**
     * Switches to another controller from the current one
     * @param string Name of controller to run (e.g. 'Foo_Bar');
     * @param bool   Whether or not to return to the current controller when the new one is done
     **/
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
     **/
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
     **/
    public static function runView() {

        // Load the view file
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
     **/
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
 **/
class FooMVCDispatchException extends Exception {}

/**
 * Base class of all FooMVC controllers
 **/
abstract class FooMVCController {
    
    public function __construct() {}

    /**
     * Transfers the controller action to a new controller
     * @param string Name of controller to switch to (e.g. Foo.Bar)
     * @param bool   Whether or not to return to the previous controller when complete (default: FALSE)
     **/
    protected final function forward($controller_name, $return=FALSE) {
        $controller_name = str_replace('.', '_', $controller_name);
        FooMVC::forward($controller_name, $return);
    }

    /**
     * Main execution block - must be implemented by each controller
     **/
    abstract function action();
    
    /**
     * Passes a variable to the view data container
     * @param string
     * @param mixed
     **/
    public final function setData($key, $val) {
        
    }

    /**
     * Overrides the default view for this controller
     * @param string Name of 
     * @todo write this method
     **/
    public final function setView($view_name) {

    }

    /**
     * Loads a model for use in a controller
     **/
    public final function loadModel($model_name) {
        $model_name = str_replace('.', '_', $model_name);
        return FooMVC::loadModel($model_name);
    }

    
}

/**
 * Base class of all FooMVC models
 **/
abstract class FooMVCModel {}

/**
 * Base class of all FooMVC views
 **/
abstract class FooMVCView {

    protected $data = array(); // Data packet to send to template
    
}
