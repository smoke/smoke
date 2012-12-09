<?php
/**
 * Authentication session storage for applications with "standard" User model
 * 
 * @author        Radoslav Kirilov <https://github.com/smoke>
 * @copyright    Radoslav Kirilov <https://github.com/smoke>
 */
class Smoke_Zend_Auth_Storage_Session extends Zend_Auth_Storage_Session
{
    /**
     * @var User $_loggedInUser
     */
    protected $_loggedInUser;
    
    /**
     * Class name or object representing the Mad user model
     * @var User|string
     */
    protected $_userModel = 'User';
    
    /**
     * Captures the user object model
     *
     * @param  mixed $namespace
     * @param  mixed $member
     * @param  mixed $userModel = 'User' either string as model class name or object
     * @return void
     */
    public function __construct($namespace = self::NAMESPACE_DEFAULT, $member = self::MEMBER_DEFAULT, $userModel = 'User')
    {
        $this->_userModel = $userModel;
        parent::__construct($namespace, $member);
    }
        
    /**
     * Returns true if and only if storage is empty
     *
     * @throws Zend_Auth_Storage_Exception If it is impossible to determine whether storage is empty
     * @return boolean
     */
    public function isEmpty()
    {
        return false;
    }

    /**
     * Returns the contents of storage
     *
     * Behavior is undefined when storage is empty.
     *
     * @throws Zend_Auth_Storage_Exception If reading contents from storage is impossible
     * @return User
     */
    public function read()
    {
        if (
            !isset($this->_loggedInUser) 
            || ($this->_loggedInUser->id != $this->_session->{$this->_member})) 
        {
            $user_id = (int) $this->_session->{$this->_member};
             
            if ($user_id) {
                try {
                    $this->_loggedInUser = call_user_func(array($this->_userModel, 'find'), $user_id);
                } catch (Mad_Model_Exception_RecordNotFound $e) {
                    trigger_error(
                        'User record was not found for logged in user (probably deleted or missing?!?). The exception is "'.$e.'"', 
                        E_USER_NOTICE
                    );
                }
            } else {
                $this->_loggedInUser = new $this->_userModel(array('id' => 0));
            }
            
            if (
                !$this->_loggedInUser // there is no user record found for the user_id in the session 
                || ($this->_loggedInUser->id && empty($this->_loggedInUser->status)) // the user found has id but is disabled ($status = 0)
            ) {
                // we clear the session data and regenerate the user object 
                $this->clear();
                $this->read();
            }
            
            if (
                method_exists($this->_loggedInUser, 'onAccess') 
                || (isset($this->_loggedInUser->onAccess) && is_callable(array($this->_loggedInUser, 'onAccess')))
            ) {
                $this->_loggedInUser->onAccess();
            }
        }
        
        return $this->_loggedInUser;
    }

    /**
     * Writes $contents to storage
     *
     * @param  User|mixed $user
     * @throws Zend_Auth_Storage_Exception If writing $contents to storage is impossible
     * @return void
     */
    public function write($user)
    {
        if (!($user instanceof $this->_userModel)) {
            throw new Zend_Auth_Storage_Exception('Given auth contents is not of class User');
        }
        
        /* @var $user User */
        $this->_session->{$this->_member} = $user->id;
        $this->_loggedInUser = $user;
        
        if (
            method_exists($this->_loggedInUser, 'onLogIn') 
            || (isset($this->_loggedInUser->onLogIn) && is_callable(array($this->_loggedInUser, 'onLogIn')))
        ) {
            $this->_loggedInUser->onLogIn();
        }
        
        //Zend_Session::regenerateId();
    }

    /**
     * Clears contents from storage
     *
     * @throws Zend_Auth_Storage_Exception If clearing contents from storage is impossible
     * @return void
     */
    public function clear()
    {
        if ($this->_session->{$this->_member}) {
            $this->_session->{$this->_member} = 0;
        }
        if ($this->_loggedInUser && $this->_loggedInUser->id) {
            
            if (
                method_exists($this->_loggedInUser, 'onLogOut') 
                || (isset($this->_loggedInUser->onLogOut) && is_callable(array($this->_loggedInUser, 'onLogOut')))
            ) {
                call_user_func(array($this->_loggedInUser, 'onLogOut'));
            }
            $this->_loggedInUser = null;
        }
        //Zend_Session::regenerateId();
    }
}