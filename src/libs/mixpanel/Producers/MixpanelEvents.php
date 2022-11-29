<?php
require_once(dirname(__FILE__) . "/MixpanelBaseProducer.php");
require_once(dirname(__FILE__) . "/MixpanelPeople.php");
require_once(dirname(__FILE__) . "/../ConsumerStrategies/CurlConsumer.php");

/**
 * Provides an API to track events on Mixpanel
 */
class Producers_MixpanelEvents extends Producers_MixpanelBaseProducer {

    /**
     * An array of properties to attach to every tracked event
     * @var array
     */
    private $_super_properties = array("mp_lib" => "php");


    /**
     * Track an event defined by $event associated with metadata defined by $properties
     * @param string $event
     * @param array $properties
     */
    public function track($event, $properties = array()) {

        // if no token is passed in, use current token
        if (!isset($properties["token"])) $properties['token'] = $this->_token;

        // if no time is passed in, use the current time
        if (!isset($properties["time"])) $properties['time'] = microtime(true);

        $params['event'] = $event;
        $params['properties'] = array_merge($this->_super_properties, $properties);

        $this->enqueue($params);
    }


    /**
     * Register a property to be sent with every event. If the property has already been registered, it will be
     * overwritten.
     * @param string $property
     * @param mixed $value
     */
    public function register($property, $value) {
        $this->_super_properties[$property] = $value;
    }


    /**
     * Register multiple properties to be sent with every event. If any of the properties have already been registered,
     * they will be overwritten.
     * @param array $props_and_vals
     */
    public function registerAll($props_and_vals = array()) {
        foreach($props_and_vals as $property => $value) {
            $this->register($property, $value);
        }
    }


    /**
     * Register a property to be sent with every event. If the property has already been registered, it will NOT be
     * overwritten.
     * @param $property
     * @param $value
     */
    public function registerOnce($property, $value) {
        if (!isset($this->_super_properties[$property])) {
            $this->register($property, $value);
        }
    }


    /**
     * Register multiple properties to be sent with every event. If any of the properties have already been registered,
     * they will NOT be overwritten.
     * @param array $props_and_vals
     */
    public function registerAllOnce($props_and_vals = array()) {
        foreach($props_and_vals as $property => $value) {
            if (!isset($this->_super_properties[$property])) {
                $this->register($property, $value);
            }
        }
    }


    /**
     * Un-register an property to be sent with every event.
     * @param string $property
     */
    public function unregister($property) {
        unset($this->_super_properties[$property]);
    }


    /**
     * Un-register a list of properties to be sent with every event.
     * @param array $properties
     */
    public function unregisterAll($properties) {
        foreach($properties as $property) {
            $this->unregister($property);
        }
    }


    /**
     * Get a property that is set to be sent with every event
     * @param string $property
     * @return mixed
     */
    public function getProperty($property) {
        return $this->_super_properties[$property];
    }


    /**
     * Identify the user you want to associate to tracked events. The $anon_id must be UUID v4 format and not already merged to an $identified_id.
     * All identify calls with a new and valid $anon_id will trigger a track $identify event, and merge to the $identified_id.
     * @param string|int $user_id
     * @param string|int $anon_id [optional]
     */
    public function identify($user_id, $anon_id = null) {
        $this->register("distinct_id", $user_id);

        $UUIDv4 = '/^[a-zA-Z0-9]*-[a-zA-Z0-9]*-[a-zA-Z0-9]*-[a-zA-Z0-9]*-[a-zA-Z0-9]*$/i';
        if (!empty($anon_id)) {
            if (preg_match($UUIDv4, $anon_id) !== 1) {
                /* not a valid uuid */
                error_log("Running Identify method (identified_id: $user_id, anon_id: $anon_id) failed, anon_id not in UUID v4 format");
            } else {
                $this->track('$identify', array(
                    '$identified_id' => $user_id,
                    '$anon_id'       => $anon_id
                ));
            }
        }
    }


    /**
     * An alias to be merged with the distinct_id. Each alias can only map to one distinct_id.
     * This is helpful when you want to associate a generated id (such as a session id) to a user id or username.
     *
     * Because aliasing can be extremely vulnerable to race conditions and ordering issues, we'll make a synchronous
     * call directly to Mixpanel when this method is called. If it fails we'll throw an Exception as subsequent
     * events are likely to be incorrectly tracked.
     * @param string|int $distinct_id
     * @param string|int $alias
     * @return array $msg
     * @throws Exception
     */
    public function createAlias($distinct_id, $alias) {
        $msg = array(
            "event"         => '$create_alias',
            "properties"    =>  array("distinct_id" => $distinct_id, "alias" => $alias, "token" => $this->_token)
        );

        // Save the current fork/async options
        $old_fork = isset($this->_options['fork']) ? $this->_options['fork'] : false;
        $old_async = isset($this->_options['async']) ? $this->_options['async'] : false;

        // Override fork/async to make the new consumer synchronous
        $this->_options['fork'] = false;
        $this->_options['async'] = false;

        // The name is ambiguous, but this creates a new consumer with current $this->_options
        $consumer = $this->_getConsumer();
        $success = $consumer->persist(array($msg));

        // Restore the original fork/async settings
        $this->_options['fork'] = $old_fork;
        $this->_options['async'] = $old_async;

        if (!$success) {
            error_log("Creating Mixpanel Alias (distinct id: $distinct_id, alias: $alias) failed");
            throw new Exception("Tried to create an alias but the call was not successful");
        } else {
            return $msg;
        }
    }


    /**
     * Returns the "events" endpoint
     * @return string
     */
    function _getEndpoint() {
        return $this->_options['events_endpoint'];
    }
}
