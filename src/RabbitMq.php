<?php
// +------------------------------------------------------------
// | Author: HanSheng
// +------------------------------------------------------------

namespace longdaihai\rabbitmq;

use think\facade\Config;

class RabbitMq implements IMQ
{
    //保存类实例的静态成员变量
    static private $_instance;
    static private $_conn;
    static private $amp ;
    static private $route = 'key_1';
    static private $q ;
    static private $ex ;
    static private $queue;

    public static function getInstance(){
        $config = Config::get('rabbitmq');
        var_dump($config); exit();

        if (!(self::$_instance instanceof self)) {
            self::$_instance = new self($config);
            return self::$_instance;
        }
        return self::$_instance;
    }

    private function __construct($conn)
    {
        //创建连接和channel
        $conn = new AMQPConnection($conn);
        if(!$conn->connect()) {
            die("Cannot connect to the broker!\n");
        }
        self::$_conn = new AMQPChannel($conn);
        self::$amp = $conn;
    }
    /* *
     *
     * parm 交换机名
     * parm 队列名
     *
     * */
    public function listen($exchangeName,$queuename){
        self::$queue = $queuename;
        return $this->setExchange($exchangeName,$queuename);
    }

    //连接交换机
    public function setExchange($exchangeName,$queueName){
        //创建交换机
        $ex = new AMQPExchange(self::$_conn);
        self::$ex = $ex;
        $ex->setName($exchangeName);

        $ex->setType(AMQP_EX_TYPE_DIRECT); //direct类型
        $ex->setFlags(AMQP_DURABLE); //持久化
        $ex->declare();
        return self::setQueue($queueName,$exchangeName);
    }

    //创建队列
    private static function setQueue($queueName,$exchangeName){
        //  创建队列
        $q = new AMQPQueue(self::$_conn);
        $q->setName($queueName);
        $q->setFlags(AMQP_DURABLE);
        $q->declareQueue();

        // 用于绑定队列和交换机
        $routingKey = self::$route;
        $q->bind($exchangeName,  $routingKey);
        self::$q = $q;
        return(self::$_instance);
    }

    /*
     * 消费者
     * $fun_name = array($classobj,$function) or function name string
     * $autoack 是否自动应答
     *
     * function processMessage($envelope, $queue) {
            $msg = $envelope->getBody();
            echo $msg."\n"; //处理消息
            $queue->ack($envelope->getDeliveryTag());//手动应答
        }
     */
    public function run($func, $autoack = True){
        if (!$func || !self::$q) return False;
        while(True){
            if ($autoack) {
                if(!self::$q->consume($func, AMQP_AUTOACK)){
                    // self::$q->ack($envelope->getDeliveryTag());
                    //失败之后会默认进入 noack 队列。下次重新开启会再次调用，目前还不清楚 回调配置应该这里做一个失败反馈
                }
            }
            self::$q->consume($func);
        }
    }


    private static function closeConn(){
        self::$amp->disconnect();
    }

    public function pushlish($msg){
        while (1) {
            sleep(1);
            if (self::$ex->publish(date('H:i:s') . "用户" . "注册", self::$route)) {
                //写入文件等操作
                echo $msg;
            }
        }
    }

    //__clone方法防止对象被复制克隆
    public function __clone()
    {
        trigger_error('Clone is not allow!', E_USER_ERROR);
    }


}