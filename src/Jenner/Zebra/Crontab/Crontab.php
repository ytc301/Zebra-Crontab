<?php
/**
 * Created by PhpStorm.
 * User: Jenner
 * Date: 14-11-7
 * Time: 下午5:09
 */

namespace Jenner\Zebra\Crontab;

use \Jenner\Zebra\MultiProcess\Process;
use \Jenner\Zebra\MultiProcess\ProcessManager;
use Monolog\Logger;


/**
 * Class Crontab
 * @package Jenner\Zebra\Crontab
 */
class Crontab
{
    /**
     * @var array 定时任务配置
     * 格式：[['name'=>'服务监控', 'cmd'=>'要执行的命令', 'output_file'=>'输出重定向', 'time_rule'=>'时间规则(crontab规则)']]
     */
    protected $mission;

    /**
     * @var Logger
     */
    protected $logger;

    /**
     * @var
     * start()函数开始执行时间，避免程序执行超过一分钟，获取到的时间不准确
     */
    protected $start_time;

    /**
     * @param $crontab_config
     * @param Logger $logger
     */
    public function __construct($crontab_config, $logger)
    {
        set_time_limit(0);
        $this->mission = $crontab_config;
        $this->logger = $logger;
    }

    /**
     * 创建子进程执行定时任务
     */
    public function start($time)
    {
        $this->start_time = $time;
        $this->logger->info("start. pid:" . getmypid());
        $manager = new ProcessManager();
        $missions = $this->getMission();
        foreach ($missions as $mission) {
            $mission_executor = new Mission($mission['cmd'], $mission['output']);
            $this->logger->info("start cmd:" . $mission['cmd']);
            $user_name = isset($mission['user_name']) ? $mission['user_name'] : null;
            $group_name = isset($mission['group_name']) ? $mission['group_name'] : null;
            try {
                $manager->fork(new Process([$mission_executor, 'start'], $mission['name']), $user_name, $group_name);
            } catch (\Exception $e) {
                $this->logger->error($e->getMessage(), $e->getTraceAsString());
            }

        }
        //等待子进程退出
        do {
            sleep(1);
        } while ($manager->countAliveChildren());
        $this->logger->info("end. pid:" . getmypid());
    }

    /**
     * 判断定时任务是否到时
     * @return array
     */
    protected function getMission()
    {
        $mission_config = $this->formatMission();
        $mission = [];
        foreach ($mission_config as $mission_value) {
            if ($this->start_time - CrontabParse::parse($mission_value['time'], $this->start_time) == 0) {
                $mission[] = $mission_value;
            }
        }

        return $mission;
    }

    /**
     * 格式化定时任务配置数组
     * @return array
     */
    protected function formatMission()
    {
        $mission_array = [];
        foreach ($this->mission as $mission_value) {
            if (is_array($mission_value['time']) && !empty($mission_value['time'])) {
                foreach ($mission_value['time'] as $time) {
                    $tmp = $mission_value;
                    $tmp['time'] = $time;
                    $mission_array[] = $tmp;
                }
            } else {
                $mission_array[] = $mission_value;
            }
        }
        return $mission_array;
    }

    /**
     * 添加定时任务
     * @param array $mission
     * @return mixed
     */
    public function addMission(array $mission)
    {
        return array_merge($this->mission, $mission);
    }
} 