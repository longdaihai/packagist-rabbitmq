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
    static private $_channel;
    static private $route = 'key_1';
    static private $q ;
    static private $ex ;
    static private $queue;

    public static function getInstance(){
        $config = Config::get('rabbitmq');

        if (!(self::$_instance instanceof self)) {
            self::$_instance = new self($config);
            return self::$_instance;
        }
        return self::$_instance;
    }

    private function __construct($config)
    {
        //创建连接和channel
        self::$_conn = new \AMQPConnection($config);
        if(!self::$_conn->connect()) {
            die("Cannot connect to the broker!\n");
        }
        self::$_channel = new \AMQPChannel(self::$_conn);
    }

    /**
     * @param $exchangeName 交换机名
     * @param $queuename 队列名
     * @return mixed
     */
    public function listen($exchangeName,$queuename){
        self::$queue = $queuename;
        $this->setExchange($exchangeName,$queuename);
        return self::$_instance;
    }

    // 创建交换机
    public function setExchange($exchangeName,$queueName){
        self::$ex = new \AMQPExchange(self::$_channel);
        self::$ex->setName($exchangeName);
        self::$ex->setType(AMQP_EX_TYPE_DIRECT); //direct类型
        self::$ex->setFlags(AMQP_DURABLE); //持久化
        self::$ex->declareExchange();
        return self::setQueue($queueName,$exchangeName);
    }

    // 创建队列
    private static function setQueue($queueName,$exchangeName){
        self::$q = new \AMQPQueue(self::$_channel);
        self::$q->setName($queueName);
        self::$q->setFlags(AMQP_DURABLE);
        self::$q->declareQueue();
        // 用于绑定队列和交换机
        self::$q->bind($exchangeName,  self::$route);
        return self::$_instance;
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
    public function run($func, $autoack = true){
        if (!$func || !self::$q) return False;
        while(true){
            if ($autoack) {
                if(!self::$q->consume($func, AMQP_AUTOACK)){
                    // self::$q->ack($envelope->getDeliveryTag());
                    //失败之后会默认进入 noack 队列。下次重新开启会再次调用，目前还不清楚 回调配置应该这里做一个失败反馈
                }
            }
            self::$q->consume($func);
        }
    }

    /**
     * 推送消息
     * @param string $msg
     * @return bool
     */
    public function pushlish(string $msg):bool {

        if (self::$ex->publish(date('H:i:s') . $msg, self::$route)) {
            // 发送成功
            echo "发送成功";
            return true;
        }else{
            // 发送失败
            echo "发送失败";
            return false;
        }

        self::closeConn();

        /*
        while (1) {
            sleep(1);
            if (self::$ex->publish(date('H:i:s') . "用户" . "注册", self::$route)) {
                //写入文件等操作
                echo $msg;
            }
        }
        */
    }

    //
    private static function closeConn(){
        self::$conn->disconnect();
    }



    //__clone方法防止对象被复制克隆
    public function __clone()
    {
        trigger_error('Clone is not allow!', E_USER_ERROR);
    }

}