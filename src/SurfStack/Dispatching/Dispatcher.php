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
     * PSR logger
     * @var LoggerInterface $logger
     */
    private $logger;
    
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
    private function isCallable()
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
    private function setErrorMessage($message)
    {
        $this->strErrorMessage = $message;
    }

    /**
     * Call the method in an output buffer
     * @return string
     */
    private function callClassMethod()
    {
        // Start output buffering
        ob_start();
        
        $class = new $this->strClass();
        
        try
        {   
            // Call beforeMethod
            if (method_exists($class, 'beforeMethod'))
            {
                $class->beforeMethod();
            }
            
            // Call the method (will not be handled by this try / catch)
            $return = call_user_func_array(array($class, $this->strMethod), $this->arrParameters);
            
            // Call beforeRender
            if (method_exists($class, 'beforeRender'))
            {
                $class->beforeRender();
            }
            
            // Get any error messages
            $errorMessage = $this->getErrorMessage();
            
            // If an error message exists
            if ($errorMessage && method_exists($class, 'set'))
            {
                // Set the error message
                $class->set('errorMessage', $errorMessage);
            }
            
            // Call render
            if (method_exists($class, 'render'))
            {
                $class->render();
            }
            
            // Call afterRender
            if (method_exists($class, 'afterRender'))
            {
                $class->afterRender();
            }
        }
        catch (Exception $e)
        {            
            // The throw new ErrorException prevents the error page from loading so I used trigger_error
            //throw new ErrorException($e->getMessage(), $e->getCode(), 0, $e->getFile(), $e->getLine());
            trigger_error($e->getMessage().' (Bug Page)'.PHP_EOL.$e->getFile().' on line '.$e->getLine());
            
            // If an error message exists
            if (method_exists($class, 'set'))
            {
                // Set the error message
                $class->set('errorMessage', $this->getErrorMessage());
            }
            
            // Render error message
            if (method_exists($class, 'renderError'))
            {
                $class->renderError();
            }
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
    private function callFunction()
    {
        // Start output buffering
        ob_start();
    
        try
        {
            // Call beforeFunction
            if (function_exists('beforeFunction'))
            {
                beforeFunction();
            }
    
            // Call the method (will not be handled by this try / catch)
            $return = call_user_func_array($this->strFunction, $this->arrParameters);
    
            // Call afterFunction
            if (function_exists('afterFunction'))
            {
                afterFunction();
            }
    
            // Get any error messages
            $errorMessage = $this->getErrorMessage();
            
            // Render error message
            if (function_exists('renderError') && $errorMessage)
            {
                renderError($errorMessage);
            }
        }
        catch (Exception $e)
        {
            // The throw new ErrorException prevents the error page from loading so I used trigger_error
            //throw new ErrorException($e->getMessage(), $e->getCode(), 0, $e->getFile(), $e->getLine());
            trigger_error($e->getMessage().' (Bug Page)'.PHP_EOL.$e->getFile().' on line '.$e->getLine());
    
            // Get any error messages
            $errorMessage = $this->getErrorMessage();
            
            // Render error message
            if (function_exists('renderError') && $errorMessage)
            {
                renderError($errorMessage);
            }
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
     * Log and return newest error
     * @return string
     */
    private function getErrorMessage()
    {
        $eMessage = '';
        
        // If an error occurred
        if (isset($_SESSION['error']))
        {
            // Log the error
            if ($this->logger)
            {
                $this->logger->error($_SESSION['error']);
            }
    
            // Only show the error message to the admin
            if (function_exists('isAdmin') && isAdmin())
            {
                // Get the error
                $eMessage = $_SESSION['error'];
            }
            
            // Clear the error
            unset($_SESSION['error']);
            
            // Clear the error backlog
            if (isset($_SESSION['errorBacklog']))
            {
                unset($_SESSION['errorBacklog']);
            }
        }
        
        // Return the message
        return $eMessage;
    }
    
    /**
     * Attempt to call the requested class and method
     * @return DispatcherResult or boolean Return false on error, returns DispatcherResult on success
     */
    private function invokeClassMethod()
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
    private function invokeFunction()
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