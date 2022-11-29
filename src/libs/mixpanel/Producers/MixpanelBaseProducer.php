<?php
require_once(dirname(__FILE__) . "/../Base/MixpanelBase.php");
require_once(dirname(__FILE__) . "/../ConsumerStrategies/FileConsumer.php");
require_once(dirname(__FILE__) . "/../ConsumerStrategies/CurlConsumer.php");
require_once(dirname(__FILE__) . "/../ConsumerStrategies/SocketConsumer.php");

if (!function_exists('json_encode')) {
    throw new Exception('The JSON PHP extension is required.');
}

/**
 * Provides some base methods for use by a message Producer
 */
abstract class Producers_MixpanelBaseProducer extends Base_MixpanelBase {


    /**
     * @var string a token associated to a Mixpanel project
     */
    protected $_token;


    /**
     * @var array a queue to hold messages in memory before flushing in batches
     */
    private $_queue = array();


    /**
     * @var ConsumerStrategies_AbstractConsumer the consumer to use when flushing messages
     */
    private $_consumer = null;


    /**
     * @var array The list of available consumers
     */
    private $_consumers = array(
        "file"      =>  "ConsumerStrategies_FileConsumer",
        "curl"      =>  "ConsumerStrategies_CurlConsumer",
        "socket"    =>  "ConsumerStrategies_SocketConsumer"
    );


    /**
     * If the queue reaches this size we'll auto-flush to prevent out of memory errors
     * @var int
     */
    protected $_max_queue_size = 1000;


    /**
     * Creates a new MixpanelBaseProducer, assings Mixpanel project token, registers custom Consumers, and instantiates
     * the desired consumer
     * @param $token
     * @param array $options
     */
    public function __construct($token, $options = array()) {

        parent::__construct($options);

        // register any customer consumers
        if (isset($options["consumers"])) {
            $this->_consumers = array_merge($this->_consumers, $options['consumers']);
        }

        // set max queue size
        if (isset($options["max_queue_size"])) {
            $this->_max_queue_size = $options['max_queue_size'];
        }

        // associate token
        $this->_token = $token;

        if ($this->_debug()) {
            $this->_log("Using token: ".$this->_token);
        }

        // instantiate the chosen consumer
        $this->_consumer = $this->_getConsumer();

    }


    /**
     * Flush the queue when we destruct the client with retries
     */
    public function __destruct() {
        $attempts = 0;
        $max_attempts = 10;
        $success = false;
        while (!$success && $attempts < $max_attempts) {
            if ($this->_debug()) {
                $this->_log("destruct flush attempt #".($attempts+1));
            }
            $success = $this->flush();
            $attempts++;
        }
    }


    /**
     * Iterate the queue and write in batches using the instantiated Consumer Strategy
     * @param int $desired_batch_size
     * @return bool whether or not the flush was successful
     */
    public function flush($desired_batch_size = 50) {
        $queue_size = count($this->_queue);
        $succeeded = true;
        $num_threads = $this->_consumer->getNumThreads();

        if ($this->_debug()) {
            $this->_log("Flush called - queue size: ".$queue_size);
        }

        while($queue_size > 0 && $succeeded) {
            $batch_size = min(array($queue_size, $desired_batch_size*$num_threads, $this->_options['max_batch_size']*$num_threads));
            $batch = array_splice($this->_queue, 0, $batch_size);
            $succeeded = $this->_persist($batch);

            if (!$succeeded) {
                if ($this->_debug()) {
                    $this->_log("Batch consumption failed!");
                }
                $this->_queue = array_merge($batch, $this->_queue);

                if ($this->_debug()) {
                    $this->_log("added batch back to queue, queue size is now $queue_size");
                }
            }

            $queue_size = count($this->_queue);

            if ($this->_debug()) {
                $this->_log("Batch of $batch_size consumed, queue size is now $queue_size");
            }
        }
        return $succeeded;
    }


    /**
     * Empties the queue without persisting any of the messages
     */
    public function reset() {
        $this->_queue = array();
    }


    /**
     * Returns the in-memory queue
     * @return array
     */
    public function getQueue() {
        return $this->_queue;
    }

    /**
     * Returns the current Mixpanel project token
     * @return string
     */
    public function getToken() {
        return $this->_token;
    }


    /**
     * Given a strategy type, return a new PersistenceStrategy object
     * @return ConsumerStrategies_AbstractConsumer
     */
    protected function _getConsumer() {
        $key = $this->_options['consumer'];
        $Strategy = $this->_consumers[$key];
        if ($this->_debug()) {
            $this->_log("Using consumer: " . $key . " -> " . $Strategy);
        }
        $this->_options['endpoint'] = $this->_getEndpoint();

        return new $Strategy($this->_options);
    }


    /**
     * Add an array representing a message to be sent to Mixpanel to a queue.
     * @param array $message
     */
    public function enqueue($message = array()) {
        array_push($this->_queue, $message);

        // force a flush if we've reached our threshold
        if (count($this->_queue) > $this->_max_queue_size) {
            $this->flush();
        }

        if ($this->_debug()) {
            $this->_log("Queued message: ".json_encode($message));
        }
    }


    /**
     * Add an array representing a list of messages to be sent to Mixpanel to a queue.
     * @param array $messages
     */
    public function enqueueAll($messages = array()) {
        foreach($messages as $message) {
            $this->enqueue($message);
        }

    }


    /**
     * Given an array of messages, persist it with the instantiated Persistence Strategy
     * @param $message
     * @return mixed
     */
    protected function _persist($message) {
        return $this->_consumer->persist($message);
    }




    /**
     * Return the endpoint that should be used by a consumer that consumes messages produced by this producer.
     * @return string
     */
    abstract function _getEndpoint();

}
