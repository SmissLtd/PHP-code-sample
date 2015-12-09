<?php

class CronManagerCommand extends CConsoleCommand
{

    // cron starting per one minute
    const JOBS_START_TIME = 0; // seconds  [h*m*s  (time() - strtotime(date('Y-m-d 00:00:00')))] (example 3:00:00 = 10800)
    const JOBS_WORK_MAX_TIME = 21600; // seconds  [h*m*s  (time() - strtotime(date('Y-m-d 00:00:00')))] (example 6:00:00 = 21600)

    private $_startTime = false;
    private $_endTime = false;
    private $_executeMembersProcessAfterEndTime = false;
    private $_checkTime;
    private $_allProcessTerminated = false;
    private $_membersProcessTerminated = false;
    private $_debugMode = false;
    private $_manualMode = false;
    private $processId = null;
	
    // Determines the starting order!    
    private $processQueue = array(
        CronProcess::PROCESS_REJECT      => 'RejectProcess',
        CronProcess::PROCESS_UPDATE_INFO => 'UpdateInfoProcess',
        CronProcess::PROCESS_PUBLISH_ALL => 'PublishAllProcess',
        CronProcess::PROCESS_CHECK_DATA  => 'CheckDataProcess',
    );
    private $allProcessForterminate = array(
        CronProcess::PROCESS_UPDATE_INFO,
        CronProcess::PROCESS_CHECK_DATA,
    );

    protected function processArgs($args)
    {
        foreach ($args as $arg)
        {
            if ($arg == '-d')
            {
                $this->_debugMode = true;
                Process::cEcho(Yii::t('cron', 'RUNNING IN DEBUG MODE ') . "----");
            }

            if ($arg == '-m')
            {
                $this->_debugMode = true;
                $this->_manualMode = true;
            }
        }
    }

    public function runTimeCheck()
    {
        if (!$this->_manualMode)
        {
            if ($this->_endTime > $this->_startTime)
            {
                $timeNow = time() - strtotime(date('Y-m-d 00:00:00'));
                if ($timeNow > $this->_endTime || $timeNow < $this->_startTime)
                {
                    if ($this->_debugMode)
                        Process::cEcho(Yii::t('cron', 'Stoped because not time to run '), true);
                    return false;
                }
            }
            else
            {
                $timeNow = time() - strtotime(date('Y-m-d 00:00:00'));
                if (($timeNow > $this->_endTime && $timeNow < $this->_startTime) || $timeNow < $this->_startTime)
                {
                    if ($this->_debugMode)
                        Process::cEcho(Yii::t('cron', 'Stoped because not time to run'), true);
                    return false;
                }
            }
        }
        return true;
    }

    protected function loadSettings()
    {
        $maintenanceSettings = Settings::model()->findAll('type = :type AND company_id = 0', array('type' => 'maintenance'));
        $settings = array();
        foreach ($maintenanceSettings as $setting)
            $settings[$setting->key] = $setting->value;
        
        $startMinutes = (isset($settings['start_minute']) && (!empty($settings['start_minute']))) ? intval($settings['start_minute']) : 0;
        $endMinutes = (isset($settings['end_minute']) && (!empty($settings['end_minute']))) ? intval($settings['end_minute']) : 0;
        $this->_startTime = (isset($settings['start_hour']) && (!empty($settings['start_hour']))) ? (intval($settings['start_hour']) * 60 * 60 + $startMinutes * 60) : self::JOBS_START_TIME;
        $this->_endTime = (isset($settings['end_hour']) && (!empty($settings['end_hour']))) ? (intval($settings['end_hour']) * 60 * 60 + $endMinutes * 60) : self::JOBS_WORK_MAX_TIME;
        $this->_executeMembersProcessAfterEndTime = (isset($settings['execute_members_process_always']) && $settings['execute_members_process_always']);

        $terminatedAll = AuditDailyProcessTeminateLog::model()->find('`is_passed` = FALSE AND process = :process', array(':process' => AuditDailyProcessTeminateLog::PROCESS_TYPE_ALL));
        $terminatedMembers = AuditDailyProcessTeminateLog::model()->find('`is_passed` = FALSE AND process = :process', array(':process' => AuditDailyProcessTeminateLog::PROCESS_TYPE_MEMBER));
        if (!empty($terminatedAll))
        {
            $this->_allProcessTerminated = true;
            if (time() >= strtotime($terminatedAll->next_start_time))
            {
                $this->_allProcessTerminated = false;
                $terminatedAll->is_passed = true;
                $terminatedAll->save(false);
            }
        }
        if (!empty($terminatedMembers))
        {
            $this->_membersProcessTerminated = true;
            if (time() >= strtotime($terminatedMembers->next_start_time))
            {
                $this->_membersProcessTerminated = false;
                $terminatedMembers->is_passed = true;
                $terminatedMembers->save(false);
            }
        }
    }

    public function run($args)
    {
        $this->processArgs($args);
        $this->loadSettings();
        if ($this->_allProcessTerminated && $this->_membersProcessTerminated)
            Process::cEcho(Yii::t('cron', 'All process terminated by users'), true);
        $this->_checkTime = $this->runTimeCheck();
        if ((!$this->_checkTime) && (!$this->_executeMembersProcessAfterEndTime))
            Process::cEcho(Yii::t('cron', 'Stoped because not time to run'), true);
        $now = time();
        $cronProcess = CronProcess::model()->findByAttributes(array('state' => CronProcess::STATE_LAUNCHED));

        if (!empty($cronProcess))
        {
            if ($now - strtotime($cronProcess->launched_datetime) > Process::PROCESS_EXECUTE_TIME_OUT)
            {
                $cronProcess->state = CronProcess::STATE_BROKEN;
                $cronProcess->end_datetime = date('Y-m-d H:i:s');
                $cronProcess->logError(Yii::t('app', 'Stopped by timeout'));
                $cronProcess->save();
                Process::cEcho(Yii::t('cron', 'Stopped by timeout [{process}]', array('{process}' => $cronProcess->process)), true);
            }
            else
            if ($this->_debugMode)
                Process::cEcho(Yii::t('cron', 'Another process is working'), true);
        }
        $cronProcess = $this->selectProcess();
        if (!empty($cronProcess))
            return $this->launchProcess($cronProcess);
        else
            Process::cEcho(Yii::t('cron', 'All processes is completed'), true);
    }

    protected function selectProcess()
    {
        foreach ($this->processQueue as $process => $processClass)
        {
            if ((!$this->_checkTime) && ($this->_executeMembersProcessAfterEndTime) && ($process != CronProcess::PROCESS_MEMBERS))
                continue;
            if ($this->_allProcessTerminated && in_array($process, $this->allProcessForterminate))
            {
                Process::cEcho(Yii::t('cron', 'All processes (Accumulators update, Payment approval, Publish all) is terminated by user'), false);
                continue;
            }
            if ($this->_membersProcessTerminated && ($process == CronProcess::PROCESS_MEMBERS))
            {
                if ($this->_debugMode)
                    Process::cEcho(Yii::t('cron', 'Members processes (Disenroll, Transfer, Terminate) is terminated by user'), false);
                continue;
            }
            $cronProcess = null;
            // commented for testing
            if ($this->_manualMode)
                $dateTimeToSelect = date('Y-m-d H:i:s', time() - 10 * 60);
            else 
            {
                if ($this->_startTime < $this->_endTime)
                    $dateTimeToSelect = date('Y-m-d ' . Process::timeToHours($this->_startTime));
                else
                {
                    $timeNow = time() - strtotime(date('Y-m-d 00:00:00'));
                    if ($this->_endTime > $timeNow 
                        || ($this->_endTime < $timeNow && $timeNow < $this->_startTime))
                    {
                        $yesterday = strtotime("-1 day");
                        $dateTimeToSelect = date('Y-m-d ' . Process::timeToHours($this->_startTime), $yesterday);
                    }
                    else
                        $dateTimeToSelect = date('Y-m-d ' . Process::timeToHours($this->_startTime));
                }
            }
            $cronProcess = CronProcess::model()->find('process = :process AND start_datetime > :start_datetime', array(':process' => $process, ':start_datetime' => $dateTimeToSelect));

            if (!empty($cronProcess))
            {
                if ($cronProcess->state != CronProcess::STATE_PARTIAL)
                    continue;
            }
            else
            {
                $cronProcess = new CronProcess();
                $cronProcess->process = $process;
                $cronProcess->start_datetime = date('Y-m-d H:i:s');
            }
            return $cronProcess;
        }
        return false;
    }
    
    private function launchProcess($cronProcess)
    {
        $cronProcess->state = CronProcess::STATE_LAUNCHED;
        $cronProcess->launched_datetime = date('Y-m-d H:i:s');
        $cronProcess->save(false);
        if (!key_exists($cronProcess->process, $this->processQueue))
        {
            $cronProcess->state = CronProcess::STATE_BROKEN;
            $cronProcess->end_datetime = date('Y-m-d H:i:s');
            $cronProcess->save();
            Process::cEcho(Yii::t('cron', 'Unknown process - {process}', array('{process}' => $cronProcess->process)), true);
        }

        $processClass = $this->processQueue[$cronProcess->process];
        $process = new $processClass($cronProcess);
        $process->setTimeSettings($this->_startTime, $this->_endTime);
        if (is_a($process, 'Process'))
            $process->run();
        else
        {
            Process::cEcho(Yii::t('cron', 'Class {process} must extend class Process', array('{process}' => get_class($cronProcess->process))), true);
            $cronProcess->state = CronProcess::STATE_BROKEN;
            $cronProcess->end_datetime = date('Y-m-d H:i:s');
            $cronProcess->save();
        }
    }

}
