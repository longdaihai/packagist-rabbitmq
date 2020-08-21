<?php
// +------------------------------------------------------------
// | Author: HanSheng
// +------------------------------------------------------------

namespace longdaihai\rabbitmq;

use think\facade\Config;
use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;

class RabbitMq implements IMQ
{
    //保存类实例的静态成员变量
    static private $_instance;

    /**
     * 连接信息
     * @var AMQPStreamConnection
     */
    static private $_conn;

    /**
     * 通道
     * @var \PhpAmqpLib\Channel\AMQPChannel
     */
    static private $_channel;

    /**
     * 列队名
     * @var
     */
    static private $_queueName;

    /**
     * 初始化
     * @param string $queueName
     * @return RabbitMq
     * @throws \Exception
     */
    public static function getInstance($queueName = 'default'){
        $conf = Config::get('rabbitmq');
        if (empty($conf)){
            throw new \Exception('rabbitmq.php 配置文件不存在或配置为空');
        }

        // 初始化列队名
        self::$_queueName = $queueName;

        if (!(self::$_instance instanceof self)) {
            self::$_instance = new self($conf);
            return self::$_instance;
        }
        return self::$_instance;
    }

    private function __construct($conf)
    {
        self::$_conn = new AMQPStreamConnection(
            $conf['host'],
            $conf['port'],
            $conf['user'],
            $conf['password']
        );

        self::$_channel = self::$_conn->channel();
        $this->queue_declare();
    }

    private function queue_declare()
    {
        self::$_channel->queue_declare(self::$_queueName,
            false,
            false,
            false,
            false);
    }

    /*
     * 消费者
     * $func = [$classobj,$function] or function name string
     * $autoack 是否自动应答
     *
     * public function doWorker($msg) {
            $data = $msg->getBody();
            echo $data."\n"; //处理消息
        }
     */
    public function run($func){
        if (!$func || !self::$_channel) return false;

        self::$_channel->basic_consume(self::$_queueName,
            '',
            false,
            true,
            false,
            false,
            $func);

        while (self::$_channel->is_consuming()) {
            self::$_channel->wait();
        }

        self::closeConn();
    }

    /**
     * 推送消息
     * @param string $msg
     * @param string $exchange
     * @return bool
     */
    public function push(string $msg, $exchange = '') {
        if (!self::$_channel) return false;
        $msg = new AMQPMessage($msg);

        self::$_channel->basic_publish($msg, $exchange, self::$_queueName);

        echo " [x] Sent 'Hello World!'\n";
        self::closeConn();
    }

    // 关闭连接
    private static function closeConn(){
        self::$_channel->close();
        self::$_conn->close();
    }

    //__clone方法防止对象被复制克隆
    public function __clone()
    {
        trigger_error('Clone is not allow!', E_USER_ERROR);
    }

}