<?php
/*
This Class manage define logs process
like : 
- Insert
- data analysis & statistic calculation 
- data clean ups
*/
class Log {

	const COLLECTION = "logs";				

	/**
	 * Set an array of parameters for actions we want to log
	 * @return $array : a set of actions with parameters
	*/
	public static function getActionsToLog(){
		return array(
	      "person/authenticate" => array('waitForResult' => true, "keepDuration" => 60),
	      "person/logout" => array('waitForResult' => false, "keepDuration" => 60),
	      "organization/save" => array('waitForResult' => true, "keepDuration" => 60),
	      "person/register" => array('waitForResult' => true, "keepDuration" => 60),
	      "news/save" => array('waitForResult' => true, "keepDuration" => 60),
	      "action/addaction" => array('waitForResult' => true, "keepDuration" => 60),
	    );
	}

	/**
	 * adds an entry into the logs collection
	 * @param $libAction : a set of information for a proper logs entry
	*/
	public static function setLogBeforeAction($libAction){

    	//Data by default
	    $logs =array(
			"userId" => @Yii::app()->session['userId'],
			"browser" => $_SERVER["HTTP_USER_AGENT"],
			"ipAddress" => $_SERVER["REMOTE_ADDR"],
			"created" => new MongoDate(time()),
			"action" => $libAction
	    );

	    //POST or GET
    	if(!empty($_REQUEST)) $logs['params'] = $_REQUEST;
	    return $logs;
	}

	/**
	 * Set the result answer of the logging action
	 * @param $id : the log id
	 * @param $result : the result information
	*/
	public static function setLogAfterAction($log, $result){

		$log['result']['result'] = @$result['result'];
		$log['result']['msg'] = @$result['msg'];
		self::save($log);

	}

	public static function save($log){
		//Update
		if(isset($log['_id'])){
			$id = $log['_id'];
			unset($log['_id']);
			PHDB::update(  self::COLLECTION, 
		     								    array("_id"=>new MongoId($id)),
		     									array('$set' => $log)
		     								);
		}//Insert
		else{
			PHDB::insert(self::COLLECTION,$log);
		}
	}


	/**
	 * List all the log in the collection logs
	*/
	public static function getAll(){
		return PHDB::find(self::COLLECTION);
	}

	/**
	 * Give a lift of IpAdress to block
	*/
	public static function getSummaryByAction(){
		
		$c = Yii::app()->mongodb->selectCollection(self::COLLECTION);
		$result = $c->aggregate(
			array(   
			  '$group'=> 
		  		array(
			    	'_id' => array(
			    		'action' => '$action'
			    		, 'ip' => '$ipAddress'
			    		, 'result' => '$result.result'
			    		, 'msg' => '$result.msg'
			    	),
				    'count' => array ('$sum' => 1),
				    'minDate' => array ( '$min' => '$created' ),
				    'maxDate' => array ( '$max' => '$created' )
				)
			
		));
		return $result;
	}

	/**
	 * Give a lift of IpAdress to block
	*/
	public static function getIpAddressToBlock(){
		//More than 5 login false in 5 minutes
		$c = Yii::app()->mongodb->selectCollection(self::COLLECTION);
		$result = $c->aggregate(
			array(
				'$group' =>
				  	array(
					  	'_id'=> '$ipAddress', 
					    'count'=> array('$sum'=> 1),
					    'minDate'=> array( '$min' => '$created' ),
   						'maxDate'=> array( '$max' => '$created' ),
					    'details'=> 
					    array('$push' =>  
					    	array(
						        'action' => '$action'
						        // , 'date' => '$created'
						        , 'result' => '$result.result'
						        , 'message' => '$result.message'
						        // , 'dateDifference'=> array (
					        	// 	'$subtract' => array('new Date()', '$created')
				        		// ) 
				        	)
			        	)
			        )
			),
		    array(
		    	'$match' => 
					array(
						'count'=> array('$gt'=> 0)
						,'details.action' => 'person/register'
						,'details.result' => false
					)
				
		    ),
		    array( 
				'$sort' => array(
					'ipAddress' => -1
					, 'date' => -1 
				)
			)
		);

		if (@$result["ok"]) {
			$list = @$result;
		} else {
			throw new CTKException("Something went wrong retrieving the list of IP !");
		}

		$nb = count($list['result']);
		for($i=0;$i<$nb;$i++){
			$nb = count($list['result']);
			for($i=0;$i<$nb;$i++){
				echo "Adresse IP => ".$list['result'][$i]['_id']."<br/>";
				echo "Nombre de tentative fausse => ".$list['result'][$i]['count']."<br/>";
				echo "Date de la première tentative =>".$list['result'][$i]['minDate']."<br/>";
				echo "Date de la dernière tentative =>".$list['result'][$i]['maxDate']."<br/>";

			}
		}
	}

	/**
	 * Let to clean the logs depends to the rules defined in the array logs parameters
	*/
	public static function cleanUp(){
	    $actionsToLog = Log::getActionsToLog();

	    foreach($actionsToLog as $action => $param){
	    	PHDB::remove(self::COLLECTION, array( 
	    		"action"=> $action
	    		, "created" => array('$lt' => 'new Date(ISODate().getTime() - 1000 * 60 * 60)')
	    	));
	    }

	}
}

?>