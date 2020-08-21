<?php
// +------------------------------------------------------------
// | Author: HanSheng
// +------------------------------------------------------------

namespace longdaihai\rabbitmq;

interface IMQ
{
    public static function getInstance($queueName);
}