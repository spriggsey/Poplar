<?php

namespace Poplar;

use Poplar\Exceptions\SchedulerException;

class Scheduler {
    public static $disable=FALSE;
    protected static $table_name='task_scheduler';
    public $processed_count=0;
    /** @var \Poplar\Database\oldQueryBuilder $QB */
    private $QB;

    function __construct() {
        $this->QB=database();
    }

    /**
     * @param array $params
     *
     * @return bool
     * @throws SchedulerException
     */
    public static function store(array $params) {
        if (static::$disable) {
            return TRUE;
        }
        if (isset($params['params'])) {
            // if they have set parameters, then encode them
            $params['params']=json_encode($params['params']);
        }
        // try to add the schedule to the DB, throw exception if cannot
        try {
            (App::get('database'))->add(static::$table_name, $params);
        } catch (\PDOException $e) {
            throw new SchedulerException($e->getMessage());
        }

        return TRUE;
    }

    /**
     * This will look for any
     *
     * @param int $limit
     *
     * @return bool
     * @throws SchedulerException
     */
    public function process($limit=0) {
        try {
            // get all events that are pending and ready to be processed
            $events=$this->QB->browse(static::$table_name, FALSE, [
                [
                    'status',
                    '=',
                    'pending',
                ],
                [
                    'delay',
                    '<',
                    date("Y-m-d H:i:s"),
                ],
            ]);
            if ($limit) {
                // if limit is provided, then slice it to that limit
                $events=array_slice($events, 0, $limit);
            } else {
                // check if the scheduler default limit is set to a number in the config
                if (App::get('config')->scheduler_default_limit) {
                    $events=array_slice($events, 0, App::get('config')->scheduler_default_limit);
                }
            }
            // loop through each event and call the function given with the arguments passed
            foreach ($events as $event) {
                $params=json_decode($event->params);
                $class=new $event->class;
                $function=$event->function;
                if ( ! call_user_func_array([$class, $function], array_values((array) $params))) {
                    // set set the event status to failed
                    $this->QB->edit(self::$table_name, ['status'=>'failed'], ['id'=>$event->id]);
                } else {
                    // event worked so set status complete
                    $this->processed_count++;
                    $this->QB->edit(self::$table_name, ['status'=>'complete'], ['id'=>$event->id]);
                }
            }

            return TRUE;
        } catch (\PDOException $e) {
            throw new SchedulerException($e->getMessage());
        }
    }
}
