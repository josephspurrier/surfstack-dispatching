<?php

/**
 * This file is part of the SurfStack package.
 *
 * @package SurfStack
 * @copyright Copyright (C) Joseph Spurrier. All rights reserved.
 * @author Joseph Spurrier (http://josephspurrier.com)
 * @license http://www.apache.org/licenses/LICENSE-2.0.html
 */

namespace SurfStack\Dispatching;
use Psr\Log\LoggerInterface;

/**
 * Dispatcher
 *
 * Calls the request class method or function
 */
class Dispatcher
{
    /**
     * Class to call
     * @var string
     */
    private $strClass = '';
    
    /**
     * Method to call
     * @var string
     */
    private $strMethod = '';
    
    /**
     * Function to call
     * @var string|function
     */
    private $strFunction = '';
    
    /**
     * Error message if generated
     * @var string
     */
    private $strErrorMessage = '';
    
    /**
     * Discovered parameters from URI
     * @var arrays
     */
    private $arrParameters = array();
    
    /**
     * Application configuration
     * @var array
     */
    private $config = array();
    
    /**
     * PSR logger
     * @var LoggerInterface $logger
     */
    private $logger;
    
    /**
     * Set the app config
     * @param mixed $config
     */
    public function setAppConfig($config)
    {
        $this->config = $config;
    }
    
    /**
     * Set the class and method
     * @param string $strClass
     * @param string $strMethod
     */
    public function setRouteClassMethod($strClass, $strMethod)
    {
        $this->strClass = $strClass;
        $this->strMethod = $strMethod;
    }
    
    /**
     * Set the function
     * @param string $strClass
     */
    public function setRouteFunction($strFunction)
    {
        $this->strFunction = $strFunction;
    }
    
    /**
     * Set the route parameters (if any)
     * @param array $arr
     */
    public function setParameters(array $arrParameters)
    {
        $this->arrParameters = $arrParameters;
    }
    
    /**
     * Set the PSR logger
     * @param LoggerInterface $logger
     */
    public function setLogger(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }
    
    /**
     * Determine if the method is callable
     * @return boolean
     */
    protected function isCallable()
    {
        if (!class_exists($this->strClass))
        {
            $this->setErrorMessage('Requested Class does not exist: '.$this->strClass);
            return false;
        }
        elseif (!method_exists($this->strClass, $this->strMethod))
        {
            $this->setErrorMessage('Requested method does not exist: '.$this->strMethod);
            return false;
        }
        elseif (strstr($this->strMethod, '__'))
        {
            $this->setErrorMessage('Requested method must not be a magic method.');
            return false;
        }
        
        $reflectClass = new \ReflectionClass($this->strClass);
        $reflectMethod = new \ReflectionMethod($this->strClass, $this->strMethod);
        
        if (!$reflectClass->isInstantiable())
        {
            $this->setErrorMessage('Requested class must be instantiable.');
            return false;
        }
        else if(!$reflectClass->isUserDefined())
        {
            $this->setErrorMessage('Requested class must be user defined.');
            return false;
        }
        elseif (!$reflectMethod->isPublic())
        {
            $this->setErrorMessage('Requested method must be public.');
            return false;
        }
        elseif ($reflectMethod->isAbstract())
        {
            $this->setErrorMessage('Requested class cannot be abstract.');
            return false;
        }
        elseif ($reflectMethod->isConstructor())
        {
            $this->setErrorMessage('Requested method cannot be a constructor.');
            return false;
        }
        elseif ($reflectMethod->isDestructor())
        {
            $this->setErrorMessage('Requested method cannot be a destructor.');
            return false;
        }
        else
        {
            return true;            
        }
    }
    
    /**
     * Set an error message
     * @param string $message
     */
    protected function setErrorMessage($message)
    {
        $this->strErrorMessage = $message;
    }

    /**
     * Call the method in an output buffer
     * @return string
     */
    protected function callClassMethod()
    {
        // Start output buffering
        ob_start();
        
        $class = new $this->strClass();
        
        // Set the app config
        if (method_exists($class, 'setAppConfig'))
        {
            $class->setAppConfig(
                $this->config
            );
        }
        
        // Call beforeMethod
        if (method_exists($class, 'beforeMethod'))
        {
            call_user_func_array(array($class, 'beforeMethod'), $this->arrParameters);
        }
        
        // Call the method (will not be handled by this try / catch)
        $return = call_user_func_array(array($class, $this->strMethod), $this->arrParameters);
        
        // Call beforeRender
        if (method_exists($class, 'beforeRender'))
        {
            call_user_func_array(array($class, 'beforeRender'), $this->arrParameters);
        }
        
        // Call render
        if (method_exists($class, 'render'))
        {
            call_user_func_array(array($class, 'render'), $this->arrParameters);
        }
        
        // Call afterRender
        if (method_exists($class, 'afterRender'))
        {
            call_user_func_array(array($class, 'afterRender'), $this->arrParameters);
        }

        // End output buffering
        $echo = ob_get_contents();
        
        // If there is an active buffer, empty it
        // If a response is already sent from within a method, it won't be cleaned
        if (ob_get_contents()) ob_end_clean();
        
        $outputObject = new DispatcherResult();
        $outputObject->setEcho($echo);
        $outputObject->setReturn($return);
        
        return $outputObject;
    }
    
    /**
     * Call the function in an output buffer
     * @return string
     */
    protected function callFunction()
    {
        // Start output buffering
        ob_start();
        
        // Call beforeFunction
        if (function_exists('beforeFunction'))
        {
            call_user_func_array('beforeFunction', $this->arrParameters);
        }

        // Call the method (will not be handled by this try / catch)
        $return = call_user_func_array($this->strFunction, $this->arrParameters);

        // Call afterFunction
        if (function_exists('afterFunction'))
        {
            call_user_func_array('afterFunction', $this->arrParameters);
        }
    
        // End output buffering
        $echo = ob_get_contents();
    
        // If there is an active buffer, empty it
        // If a response is already sent from within a method, it won't be cleaned
        if (ob_get_contents()) ob_end_clean();
    
        $outputObject = new DispatcherResult();
        $outputObject->setEcho($echo);
        $outputObject->setReturn($return);
    
        return $outputObject;
    }
    
    /**
     * Attempt to call the requested class and method
     * @return DispatcherResult or boolean Return false on error, returns DispatcherResult on success
     */
    protected function invokeClassMethod()
    {
        // Return an error if unable to call
        if (!$this->isCallable())
        {
            $output = new DispatcherResult();
            $output->setError($this->strErrorMessage);
            return $output;
        }
        
        // Make the call
        $outputObject = $this->callClassMethod();
        
        // Return the output
        return $outputObject;
    }
    
    /**
     * Attempt to call the requested function
     * @return DispatcherResult or boolean Return false on error, returns DispatcherResult on success
     */
    protected function invokeFunction()
    {
        // Return an error if unable to call
        if (!is_callable($this->strFunction))
        {
            $this->strErrorMessage = "The function, $this->strFunction, cannot be found.";
            $output = new DispatcherResult();
            $output->setError($this->strErrorMessage);
            return $output;
        }
    
        // Make the call
        $outputObject = $this->callFunction();
    
        // Return the output
        return $outputObject;
    }
    
    /**
     * Attempt to call the request class method first if it's passed, if not call
     * the function or anonymous function.
     * @return Ambigous <\SurfStack\Dispatching\DispatcherResult, string>|\SurfStack\Dispatching\DispatcherResult
     */
    public function invoke()
    {
        if ($this->strClass && $this->strMethod)
        {
            return $this->invokeClassMethod();
        }
        else if ($this->strFunction)
        {
            return $this->invokeFunction();
        }
        else
        {
            $this->strErrorMessage = "No routes were passed.";
            $output = new DispatcherResult();
            $output->setError($this->strErrorMessage);
            return $output;
        }
    }
}

?>