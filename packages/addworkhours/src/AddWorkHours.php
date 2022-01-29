<?php

namespace Studio770Utils;
 
use Carbon\Exceptions\InvalidFormatException;
use DateTime;

class AddWorkHours
{

    private $holidays;
    private $timeTable;
    private $periodLimit;
    private $dateFrom;
    private $type;

    function __construct($date)
    {
        $this->dateFrom = $date;
        $this->timeTable = array(
            '1' => array('start' => '09:00:00', 'end' => '18:00:00'),
            '2' => array('start' => '09:00:00', 'end' => '18:00:00'),
            '3' => array('start' => '09:00:00', 'end' => '18:00:00'),
            '4' => array('start' => '09:00:00', 'end' => '18:00:00'),
            '5' => array('start' => '09:00:00', 'end' => '18:00:00'),
//            '6' => array('start' => '10:00:00', 'end' => '16:00:00')
        );
        $this->holidays = array('30.03.2020', '31.03.2020', '01.04.2020', '02.04.2020', '03.04.2020', '01.05.2020', '04.05.2020', '05.05.2020', '11.05.2020', '12.06.2020', '24.06.2020', '31.07.2020'
        , '31.12.2021', '01.01.2022', '02.01.2022', '03.01.2022', '04.01.2022', '05.01.2022', '06.01.2022', '07.01.2022', '08.01.2022', '09.01.2022'
        );
    }

    /**
     * @param array $holidays
     * @return void
     */
    public function setHolidays($holidays)
    {
        $format = 'd.m.Y';
        array_map(function ($holiday) use ($format) {
            $date = DateTime::createFromFormat($format, $holiday);
            if (!$date || $date->format($format) !== $holiday) {
                throw new InvalidFormatException('incorrect date format');
            }
        }, $holidays);

        $this->holidays = $holidays;
    }

    public function setHours($hours)
    {
        if ($this->type === 'work') {
            $this->periodLimit = $hours * 60 * 60;
        }
        if ($this->type === 'calendar') {
            $this->periodLimit = $hours;
        }
        if ($this->type === 'workDays') {
            $this->periodLimit = $hours;
        }

    }

    public function setType($type)
    {
        $this->type = $type;
    }

    public function getFinalDate()
    {
        if ((string)$this->type === 'calendar') {
            /* echo 'calendar', '<br>';
             echo 'date ', $this->dateFrom, '<br>';
             echo 'period ', $this->periodLimit, '<br>';*/
            return date('Y-m-d H:i:s', strtotime('+' . $this->periodLimit . ' day', $this->dateFrom));
        }

        if((string)$this->type === 'workDays'){
            return $this->getWorkDays($this->periodLimit,$this->dateFrom,$this->holidays);
        }

        if ((string)$this->type === 'nextDay') {
            for ($i = 1; $i < 20; $i++) {
                if($this->checkDate(date('d.m.Y', strtotime('+' . $i . ' day', $this->dateFrom)))===true){
                    return date('Y-m-d 14:00:00', strtotime('+' . $i . ' day', $this->dateFrom));
                }
            }
            return false;
        }

        if ((string)$this->type === 'work') {
            $todayHours = $this->todayLeftTime($this->dateFrom);
            // echo 'today ', $todayHours, '<br>';
            if ($todayHours < $this->periodLimit) {
                $total = $todayHours;
                for ($i = 1; $i < 20; $i++) {
                    // echo 'i: ', $i, '<br>';
                    $startTime = strtotime(date('d.m.Y', strtotime('+' . $i . ' day', $this->dateFrom)) . ' ' . $this->timeTable[date('N', strtotime('+' . $i . ' day', $this->dateFrom))]['start']);
                    //  echo 'istart: ', $startTime, '<br>';
                    $dayHours = $this->todayLeftTime($startTime);
                    //   echo $dayHours, '<br>';
                    if (($dayHours + $total) >= $this->periodLimit) {
                        $delta = $this->periodLimit - $total;
                        //   echo 'delta ', $delta, '<br>';
                        //   echo 'starttime ', $startTime, '<br>';
                        return date('Y-m-d H:i:s', $startTime + $delta);
                    } else {
                        $total += $dayHours;
                    }

                }
            } else {
                $delta = $todayHours - $this->periodLimit;
                $endTime = strtotime(date('d.m.Y', $this->dateFrom) . ' ' . $this->timeTable[date('N', $this->dateFrom)]['end']);
                return date('Y-m-d H:i:s', $endTime - $delta);
            }
        }
    }

    private function todayLeftTime($date)
    {
        if (array_key_exists(date('N', $date), $this->timeTable)) {  //если это рабочий день недели
            if (!in_array(date('d.m.Y', $date), $this->holidays)) { //если это не праздник
                if ($date < strtotime(date('d.m.Y', $date) . ' ' . $this->timeTable[date('N', $date)]['start'])) {
                    $date = strtotime(date('d.m.Y', $date) . ' ' . $this->timeTable[date('N', $date)]['start']);
                }

                $leftTime = strtotime(date('d.m.Y', $date) . ' ' . $this->timeTable[date('N', $date)]['end']) - $date;
                if ($leftTime < 0) {
                    $leftTime = 0;
                }
                return $leftTime;
            }
            return 0;
        }
        return 0;
    }

    public function getWorkDays($num_business_days, $today = null,$holidays=[])
    {
        if(!$holidays) $holidays = $this->holidays;
        $num_business_days = min($num_business_days, 30);
        $business_day_count = 0;
        $time=date('H:i:s',$today);
        $current = $today;
        while ($business_day_count < $num_business_days) {
            $next1WD = strtotime('+1 weekday', $current);
            $next1WDDate = date('d.m.Y', $next1WD);
            if (!in_array($next1WDDate, $holidays)) {
                $business_day_count++;
            }
            $current = $next1WD;
        }
        return date('Y-m-d', $current).' '.$time;
    }

    public function getWorkDaysDiffBetweenDates($date1, $date2, $holidays=[]) : int
    {
        $date1 = strtotime($date1);
        $date2 = strtotime($date2);
        $business_day_count = 0;
        // TODO проверить разницу дат , максимально скажем год
        while ($date1 < $date2) {
            $date1 = strtotime('+1 weekday', $date1);
            if (!in_array(date('d.m.Y', $date1), $holidays ?? $this->holidays, true)) {
                $business_day_count++;
            }

        }
        return $business_day_count;
    }

    private function checkDate($date)
    {
        return (array_key_exists(date('N', strtotime($date)), $this->timeTable) && !in_array(date('d.m.Y', strtotime($date)), $this->holidays,true));
    }
}
