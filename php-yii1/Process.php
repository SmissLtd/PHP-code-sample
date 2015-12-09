<?php

class Process
{
    const PROCESS_EXECUTE_TIME = 120;
    const PROCESS_EXECUTE_TIME_OUT = 180;
    
    protected $_cronProcess = null;
    protected $_companyId = false;
    
    protected $publishDate = null;
    protected $effectiveDate = null;
    
    public function __construct($cronProces, $companyId = false)
    {
        $this->_cronProcess = $cronProces;
        if (!empty($this->_cronProcess->process_options))
            $this->_cronProcess->process_options = unserialize($this->_cronProcess->process_options);
        
        if ($companyId !== false)
        {
            $this->_companyId = $companyId;
        }
    }
    
    /**
     * Console Echo 
     * @param text $text 
     * @param bool $end end this process script
     */
    public static function cEcho($text, $end = false)
    {
        echo '[' . date('H:i:s', time()) . '] ' . $text . "\n";
        if ($end)
            Yii::app()->end('', true);
    }
     
    /**
     * Convert intager time to 'H:i:s'
     * @param int $time 
     * @return time in format 'H:i:s'
     */
    public static function timeToHours($time)
    {
        $bigTime = strtotime(date('Y-m-d')) + $time;
        return date('H:i:s', $bigTime);
    }

    public function isTimeEnded()
    {
        return (time() - strtotime($this->_cronProcess->launched_datetime) > self::PROCESS_EXECUTE_TIME);
    }
    
    public function stopProcessPartial()
    {
        $this->_cronProcess->state = CronProcess::STATE_PARTIAL;
        if (!empty($this->_cronProcess->process_options))
            $this->_cronProcess->process_options = serialize($this->_cronProcess->process_options);

        $this->_cronProcess->save(false);

        return $this->cEcho('Process partial done, time is ended', true);
    }
    
    public function finishProcess()
    {
        $this->_cronProcess->state = CronProcess::STATE_FINISHED;
        if (!empty($this->_cronProcess->process_options))
            $this->_cronProcess->process_options = null;
        
        $this->_cronProcess->end_datetime = date('Y-m-d H:i:s');
        $this->_cronProcess->save(false);
        return $this->cEcho('Process successfully finished', true);
    }

    public function run()
    {
        $this->_cronProcess->state = CronProcess::STATE_BROKEN;
        $this->_cronProcess->end_datetime = date('Y-m-d H:i:s');
        $this->_cronProcess->save();
        return $this->cEcho('Error: Create run function!');
    }
    
    public function setTimeSettings($timeStart, $timeEnd)
    {
        $this->publishDate = date('Y-m-d 00:00:00');
        $this->effectiveDate = date('Y-m-d');
        
        $timeNow = time() - strtotime(date('Y-m-d 00:00:00'));
        if ($timeStart > $timeEnd && $timeNow > $timeStart)
        {
            $this->publishDate = date('Y-m-d 00:00:00', strtotime('+1 day'));
            $this->effectiveDate = date('Y-m-d', strtotime('+1 day'));
        }
        
        if (strtotime(strtotime(Yii::app()->params['publishDelay']) == strtotime('-1 day')))
        {
            $this->publishDate = date('Y-m-d 00:00:00', strtotime("{$this->publishDate} -1 day"));
        }
    }
}

