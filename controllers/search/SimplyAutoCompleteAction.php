<?php
class SimplyAutoCompleteAction extends CAction
{
    public function run($filter = null)
    {

  		// $pathParams = Yii::app()->controller->module->viewPath.'/default/dir/';
		// echo file_get_contents($pathParams."simply.json");
		// die();

        $search = isset($_POST['name']) ? trim(urldecode($_POST['name'])) : null;
        $locality = isset($_POST['locality']) ? trim(urldecode($_POST['locality'])) : null;
        $searchType = isset($_POST['searchType']) ? $_POST['searchType'] : null;
        $searchTag = isset($_POST['searchTag']) ? $_POST['searchTag'] : null;
        $searchPrefTag = isset($_POST['searchPrefTag']) ? $_POST['searchPrefTag'] : null;
        $filtreTag = isset($_POST['filtreTag']) ? $_POST['filtreTag'] : null;
        $searchBy = isset($_POST['searchBy']) ? $_POST['searchBy'] : "INSEE";
        $indexMin = isset($_POST['indexMin']) ? $_POST['indexMin'] : 0;
        $indexMax = isset($_POST['indexMax']) ? $_POST['indexMax'] : 100;
        $country = isset($_POST['country']) ? $_POST['country'] : "";
        $sourceKey = isset($_POST['sourceKey']) ? $_POST['sourceKey'] : null;
        $mainTag = isset($_POST['mainTag']) ? $_POST['mainTag'] : null;
        $paramsFiltre = isset($_POST['paramsFiltre']) ? $_POST['paramsFiltre'] : null;
        $disabled = isset($_POST['disabled']) ? $_POST['disabled'] : null;
        $seeDisable = isset($_POST['seeDisable']) ? $_POST['seeDisable'] : null;

        if( ($indexMax - $indexMin) > 2000 ){
        	$indexMax = $indexMin + 2000 ;
        }
        
        if($search == null && $locality == null && $sourceKey == null) {
        	Rest::json(array());
			Yii::app()->end();
        }
        $getCreator = false ;
        //strpos($sourceKey[0], "@")
        if( $sourceKey != null && $sourceKey != "" && strpos($sourceKey[0], "@") > 0 ) {
        	$split = explode("@", $sourceKey[0]);
        	$query = array();
        	try{
        		$element = Element::getByTypeAndId($split[1], $split[0]);
	        	if(!empty($element) && 
	        		(	$split[1] != Person::COLLECTION || 
                     Preference::showPreference($element, $split[1], "directory", Yii::app()->session["userId"]) ) ) {

	        		$query = array("creator" => $split[0]);
		        	$links = array("events", "projects", "followers", "members", "memberOf", "subEvents", "follows", "attendees", "organizer", "contributors");
		        	foreach ($links as $key => $value) {
		        		$query = array('$or' => array($query, array("links.".$value.".".$split[0] => array('$exists' => 1))));
		        	}
		        	$getCreator = true ;
	        	}
        	}catch (MongoException $m){
				
			}
        	
		}else{

	        /***********************************  DEFINE GLOBAL QUERY   *****************************************/
	        $query = array( "name" => new MongoRegex("/".$search."/i"));

	        if($seeDisable == "true"){
	  			$query = array('$and' => array($query, array("disabled" => array('$exists' => 1))));
	        }else if($disabled != "true"){
	  			$query = array('$and' => array($query, array("disabled" => array('$exists' => 0))));
	        }

	        /***********************************  Filtre   *****************************************/
	        
	        $tmpTags = array();
	        if(strpos($search, "#") > -1){
	        	$search = substr($search, 1, strlen($search));
	        	$tmpTags[] = new MongoRegex("/".$search."/i");
	  		}
	  		if(count($tmpTags)){
	  			$query = array('$and' => array( $query , array("tags" => array('$in' => $tmpTags)))) ;
	  		}
	  		
	  		if(!empty($filtreTag)){
	  			foreach ($filtreTag as $key => $tags) {
		  			$tmpTags = array();
		  			foreach ($tags as $key => $tag) {
				  		$tmpTags[] = new MongoRegex("/".$tag."/i");
				  		/*if(in_array($tag, $searchTag))
				  			unset($searchTag["$tag"]);*/
			  		}
			  		if(count($tmpTags)){
			  			$verbBlock = ( (!empty($paramsFiltre["conditionBlock"]) && 'or' == $paramsFiltre["conditionBlock"]) ? '$or' : '$and' ) ;
	  					$verbTagInBlock = ( (!empty($paramsFiltre["conditionTagInBlock"]) && '$all' == $paramsFiltre["conditionTagInBlock"]) ? '$all' : '$in' ) ;
			  			$query = array($verbBlock => array( $query , array("tags" => array($verbTagInBlock => $tmpTags)))) ;
			  		}
		  		}
	  		}
	  		
	  		unset($tmpTags);

	        /***********************************  search Tag   *****************************************/
	        $tmpTags = array();
	  		if($searchTag){
	  			foreach ($searchTag as $value) {
		  			$tmpTags[] = new MongoRegex("/".$value."/i");
		  		}
	  		}
	  		if(count($tmpTags)){
	  			$query = array('$and' => array( $query , array("tags" => array('$in' => $tmpTags)))) ;
	  		}
	  		unset($tmpTags);

	        

	  		/***********************************  COMPLETED   *****************************************/
	  		$query = array('$and' => array( $query , array("state" => array('$ne' => "uncomplete")) ));

	      /***********************************   MAINTAG    *****************************************/
	      $tmpmainTag = array();
	      if($mainTag != null && $mainTag != ""){
	          //Several Sourcekey
	  	  		if(is_array($mainTag)){
	  	  			foreach ($mainTag as $value) {
	  	  				$tmpMainTag[] = new MongoRegex("/".$value."/i");
	  	  			}
	  	  		}//One Sourcekey
	  	  		else{
	  	  			$tmpMainTag[] = new MongoRegex("/".$mainTag."/i");
	  	  		}
		  		if(count($tmpMainTag)){
		  			$verbMainTag = ( (!empty($searchPrefTag) && '$or' == $searchPrefTag) ? '$all' : '$in' );
		  			$query = array('$and'=> array( $query , array("tags" => array($verbMainTag  => $tmpMainTag))));
		  		}
		  		unset($tmpMainTag);
		  	}

	  		/***********************************  SOURCEKEY   *****************************************/
	  		$tmpSourceKey = array();
	  		if($sourceKey != null && $sourceKey != ""){
	  			//Several Sourcekey
		  		if(is_array($sourceKey)){
		  			foreach ($sourceKey as $value) {
		  				$tmpSourceKey[] = new MongoRegex("/".$value."/i");
		  			}
		  		}//One Sourcekey
		  		else{
		  			$tmpSourceKey[] = new MongoRegex("/".$sourceKey."/i");
		  		}
		  		if(count($tmpSourceKey)){
		  			$query = array('$and' => array( $query , array("source.keys" => array('$in' => $tmpSourceKey))));
		  		}
		  		unset($tmpSourceKey);
		  	}

	  		/***********************************  DEFINE LOCALITY QUERY   ***************************************/
	  		$localityReferences['NAME'] = "address.addressLocality";
	  		$localityReferences['CODE_POSTAL_INSEE'] = "address.postalCode";
	  		$localityReferences['DEPARTEMENT'] = "address.depName";
	  		$localityReferences['REGION'] = "address.regionName"; //Spécifique
	  		$localityReferences['INSEE'] = "address.codeInsee";

	  		foreach ($localityReferences as $key => $value) {
	  			if(isset($_POST["searchLocality".$key]) && is_array($_POST["searchLocality".$key])){
	  				foreach ($_POST["searchLocality".$key] as $locality) {

	  					//OneRegion
	  					if($key == "REGION") {
		        			/*$deps = PHDB::find( City::COLLECTION, array("regionName" => $locality), array("dep"));
		        			$departements = array();
		        			$inQuest = array();
		        			if(is_array($deps))foreach($deps as $index => $value){
		        				if(!in_array($value["dep"], $departements)){
			        				$departements[] = $value["dep"];
			        				$inQuest[] = new MongoRegex("/^".$value["dep"]."/i");
						        	$queryLocality = array("address.postalCode" => array('$in' => $inQuest));

						        }
		        			}*/
		        			$queryLocality = array($value => $locality);
		        		}elseif($key == "DEPARTEMENT") {
		        			//$dep = PHDB::find( City::COLLECTION, array("depName" => $locality), array("dep"));	
			        		//$queryLocality = array($value => new MongoRegex("/^".$dep["dep"]."/i"));
			        		$queryLocality = array($value => $locality);
			        	}//OneLocality
			        	else{
		  					$queryLocality = array($value => new MongoRegex("/".$locality."/i"));
		  				}

	  					//Consolidate Queries
	  					if(isset($allQueryLocality)){
	  						$allQueryLocality = array('$or' => array( $allQueryLocality ,$queryLocality));
	  					}else{
	  						$allQueryLocality = $queryLocality;
	  					}
	  					unset($queryLocality);
	  				}
	  			}
	  		}
	  		if(isset($allQueryLocality) && is_array($allQueryLocality))
	  			$query = array('$and' => array($query, $allQueryLocality));
	  		// print_r($query);
	  		// $query = array('$and' => array($query, $queryLocality));

	    //     if($locality != null && $locality != ""){

	    //     	//$type = $this->getTypeOfLocalisation($locality);
	    //     	//if($searchBy == "INSEE")
	    //     	$type = $searchBy;

	    //     	$queryLocality = array();

	    //     	if($type == "NAME"){
	    //     		$queryLocality = array("address.addressLocality" => new MongoRegex("/".$locality."/i"));
	    //     	}
	    //     	if($type == "CODE_POSTAL_INSEE") {
	    //     		$queryLocality = array("address.postalCode" => $locality );
	    //     	}
	    //     	if($type == "DEPARTEMENT") {
	    //     		$queryLocality = array("address.postalCode"
					// 		=> new MongoRegex("/^".$locality."/i"));
	    //     	}
	    //     	if($type == "REGION") {
	    //     		//#TODO GET REGION NAME | CITIES.DEP = myDep
	    //     		$regionName = PHDB::findOne( City::COLLECTION, array("insee" => $locality), array("regionName", "dep"));

					// if(isset($regionName["regionName"])){ //quand la city a bien la donnée "regionName"
	    //     			$regionName = $regionName["regionName"];
	    //     			//#TODO GET ALL DEPARTMENT BY REGION
	    //     			$deps = PHDB::find( City::COLLECTION, array("regionName" => $regionName), array("dep"));
	    //     			$departements = array();
	    //     			$inQuest = array();
	    //     			foreach($deps as $index => $value){
	    //     				if(!in_array($value["dep"], $departements)){
		   //      				$departements[] = $value["dep"];
		   //      				$inQuest[] = new MongoRegex("/^".$value["dep"]."/i");
					//         	$queryLocality = array("address.postalCode" => array('$in' => $inQuest));

					//         }
	    //     			}
	    //     			//$queryLocality = array('$or' => $orQuest);
	    //     			//error_log("queryLocality : " . print_R($queryLocality, true));

	    //     		}else{ //quand la city communectée n'a pas la donnée "regionName", on prend son département à la place
	    //     			$regionName = isset($regionName["dep"]) ? $regionName["dep"] : "";
	    //     			$queryLocality = array("address.postalCode"
					// 		=> new MongoRegex("/^".$regionName."/i"));
	    //     		}


	    //     		//$str = implode(",", $regionName);
	    //     		error_log("regionName : ".$regionName );

	    //     		//#TODO CREATE REQUEST CITIES.POSTALCODE IN (LIST_DEPARTMENT)"
	    //   //   		$queryLocality = array("address.postalCode"
					// 		// => new MongoRegex("/^".$locality."/i"));
	    //     	}
	    //     	if($type == "INSEE") {
	    //     		$queryLocality = array("address.codeInsee" => $locality );
	    //     	}

	    //     	$query = array('$and' => array($query, $queryLocality ) );
		   //  }

	  	}
		    //$res = array();
		    $allRes = array();
	        /***********************************  PERSONS   *****************************************/
	       if(strcmp($filter, Person::COLLECTION) != 0 && $this->typeWanted("citoyen", $searchType) && !empty($query)){

	        	$allCitoyen = PHDB::find ( Person::COLLECTION , $query , array(/*"name", "address", "shortDescription", "description"*/));

		  		foreach ($allCitoyen as $key => $value) {
		  			$person = Person::getSimpleUserById($key, $value);
		  			$person["typeElement"] = "citoyen";
					$person["typeSig"] = "citoyens";
					$allCitoyen[$key] = $person;
		  		}

		  		//$res["citoyen"] = $allCitoyen;
		  		$allRes = array_merge($allRes, $allCitoyen);

		  	}

		  	/***********************************  ORGANISATIONS   *****************************************/
	        if(strcmp($filter, Organization::COLLECTION) != 0 && $this->typeWanted("organizations", $searchType) && !empty($query)){

		  		$allOrganizations = PHDB::find ( Organization::COLLECTION ,$query ,array(/*"id" => 1, "name" => 1, "type" => 1, "email" => 1,  "shortDescription" => 1, "description" => 1,
														 			"address" => 1, "pending" => 1, "tags" => 1, "geo" => 1, "source.key" => 1, "disabled" => 1,*/));
		  		foreach ($allOrganizations as $key => $value) {
		  			$orga = Organization::getSimpleOrganizationById($key, $value, $getCreator);

		  			// $followers = Organization::getFollowersByOrganizationId($key);
		  			// if(@$followers[Yii::app()->session["userId"]]){
			  		// 	$orga["isFollowed"] = true;
		  			// }

		  	// 		$allOrganizations[$key]["profilThumbImageUrl"] = "";
					// $allOrganizations[$key]["profilMarkerImageUrl"] = "//com/assets/dad45ab2/images/sig/markers/icons_carto/";
					// $allOrganizations[$key]["logoImageUrl"] = "";

					// $allOrganizations[$key]["address"] = empty($value["address"]) ? array("addressLocality" => "Unknown") : $value["address"];

					// $allOrganizations[$key] = Organization::getSimpleOrganizationById($key);

					$allOrganizations[$key] = $orga;
					$allOrganizations[$key]["typeElement"] = "organization";
					$allOrganizations[$key]["typeSig"] = "organizations";
		  		}

		  		//$res["organization"] = $allOrganizations;
		  		$allRes = array_merge($allRes, $allOrganizations);
		  	}

		  	/***********************************  EVENT   *****************************************/
	        if(strcmp($filter, Event::COLLECTION) != 0 && $this->typeWanted("events", $searchType) && !empty($query)){

	        	$queryEvent = $query;
	        	if( !isset( $queryEvent['$and'] ) )
	        		$queryEvent['$and'] = array();

	        	array_push( $queryEvent[ '$and' ], array( "endDate" => array( '$gte' => new MongoDate( time() ) ) ) );
		  		$allEvents = PHDB::findAndSort( PHType::TYPE_EVENTS, $queryEvent, array("startDate" => 1), 100, array(/*"name", "address", "startDate", "endDate", "shortDescription", "description"*/));
		  		foreach ($allEvents as $key => $value) {
		  			$event = Event::getSimpleEventById($key, $value, $getCreator);
					$event["typeElement"] = "event";
					$event["typeSig"] = "events";
					$allEvents[$key] = $event;
		  		}

		  		//$res["event"] = $allEvents;
		  		$allRes = array_merge($allRes, $allEvents);
		  	}

		  	/***********************************  PROJECTS   *****************************************/
	        if(strcmp($filter, Project::COLLECTION) != 0 && $this->typeWanted("projects", $searchType) && !empty($query)){
		  		$allProject = PHDB::find(Project::COLLECTION, $query, array(/*"name", "address", "shortDescription", "description"*/));
		  		foreach ($allProject as $key => $value) {
		  			$project = Project::getSimpleProjectById($key, $value, $getCreator);
		  			if(@$project["links"]["followers"][Yii::app()->session["userId"]]){
			  			$orga["isFollowed"] = true;
		  			}
					$project["typeElement"] = "project";
					$project["typeSig"] = "projects";
					$allProject[$key] = $project;
		  		}
		  		//$res["project"] = $allProject;
		  		$allRes = array_merge($allRes, $allProject);
		  	}

		  	/***********************************  CITIES   *****************************************/
	        if(strcmp($filter, City::COLLECTION) != 0 && $this->typeWanted("cities", $searchType) && !empty($query)){
		  		$query = array( "name" => new MongoRegex("/".self::wd_remove_accents($search)."/i"));//array('$text' => array('$search' => $search));//

		  		/***********************************  DEFINE LOCALITY QUERY   *****************************************/
		        	if($locality == null || $locality == ""){
			    		$locality = $search;
			    	}
			    	$type = $this->getTypeOfLocalisation($locality);
			    	if($searchBy == "INSEE") $type = $searchBy;
		        	error_log("type " . $type);
		    		if($type == "NAME"){
		        		$query = array('$or' => array( array( "name" => new MongoRegex("/".self::wd_remove_accents($locality)."/i")),
		        									   array( "alternateName" => new MongoRegex("/".self::wd_remove_accents($locality)."/i")),
		        									   array("postalCodes.name" => array('$in' => array(new MongoRegex("/".self::wd_remove_accents($locality)."/i"))))
		        					));
		        		//error_log("search city with : " . self::wd_remove_accents($locality));
		        	}
		        	if($type == "CODE_POSTAL_INSEE") {
		        		$query = array("postalCodes.postalCode" => array('$in' => array($locality)));
		        	}
		        	if($type == "DEPARTEMENT") {
		        		$query = array("dep" => $locality );
		        	}
		        	if($type == "INSEE") {
		        		$query = array("insee" => $locality );
		        	}
				    //}

				    if($country != ""){
				    	$query["country"] = $country;
				    }

		  		$allCities = PHDB::find(City::COLLECTION, $query, array("name", "alternateName", "cp", "insee", "regionName", "country", "geo", "geoShape","postalCodes"));
		  		$allCities = PHDB::find(City::COLLECTION, $query);
		  		$allCitiesRes = array();
		  		$nbMaxCities = 20;
		  		$nbCities = 0;
		  		foreach($allCities as $data){
			  		$countPostalCodeByInsee = count($data["postalCodes"]);
			  		foreach ($data["postalCodes"] as $val){
				  		if($nbCities < $nbMaxCities){
				  		$newCity = array();
				  		//$regionName =
				  		$newCity = array(
				  						"_id"=>$data["_id"],
				  						"insee" => $data["insee"],
				  						"regionName" => isset($data["regionName"]) ? $data["regionName"] : "",
				  						"country" => $data["country"],
				  						"geoShape" => isset($data["geoShape"]) ? $data["geoShape"] : "",
				  						"cp" => $val["postalCode"],
				  						"geo" => $val["geo"],
				  						"geoPosition" => $val["geoPosition"],
				  						"name" => ucwords(strtolower($val["name"])),
				  						"alternateName" => ucwords(strtolower($val["name"])),
				  						"type"=>"city",
				  						"typeSig" => "city");
				  		if($countPostalCodeByInsee > 1){
				  			$newCity["countCpByInsee"] = $countPostalCodeByInsee;
				  			$newCity["cityInsee"] = ucwords(strtolower($data["alternateName"]));
				  		}
				  		$allCitiesRes[]=$newCity;
				  		} $nbCities++;
			  		}
		  		}


		  		if(empty($allCitiesRes)){
		  			$query = array( "cp" => $search);
			  		$allCities = PHDB::find(City::COLLECTION, $query, array("name", "cp", "insee", "geo", "geoShape"));
			  		$nbCities = 0;
		  			foreach ($allCities as $key => $value) {
			  			if($nbCities < $nbMaxCities){
				  			$city = City::getSimpleCityById($key);
							$city["type"] = "city";
							$city["typeSig"] = "city";
							$allCitiesRes[$key] = $city;
						} $nbCities++;
			  		}
			  		//$res["cities"] = $allCitiesRes;
		  		}
		  	}
		
		  	//trie les éléments dans l'ordre alphabetique par name
		  	function mySort($a, $b){
		  		if(isset($a['name']) && isset($b['name'])){
			    	return ( strtolower($b['name']) < strtolower($a['name']) );
				}else{
					return false;
				}
			}

		  	if(isset($allRes)) //si on a des resultat dans la liste
		  		if(!$this->typeWanted("events", $searchType)) //si on n'est pas en mode "event" (les event sont classé par date)
		  			usort($allRes, "mySort"); //on tri les éléments par ordre alphabetique sur le name

		  	if(isset($allCitiesRes)) usort($allCitiesRes, "mySort");

		  	//error_log("count : " . count($allRes));
		  	if(count($allRes) < $indexMax)
		  		if(isset($allCitiesRes))
		  			$allRes = array_merge($allRes, $allCitiesRes);

		  	$limitRes = $filters = array();
		  	$index = 0;
		  	foreach ($allRes as $key => $value) {
		  		//Limit <pagination
		  		if($index < $indexMax && $index >= $indexMin){
		  			$limitRes[] = $value;
			  	}

			  	//filter tag
			  	if(!empty($value['tags']))foreach ($value['tags'] as $keyTag => $valueTag) {
			  		$valueTag = trim($valueTag);
			  		if(!empty($valueTag)){
			  			if(isset($filters['tags'][$valueTag])){
				  			$filters['tags'][$valueTag] +=1;
				  		}
				  		else{
				  			$filters['tags'][$valueTag] = 1;
				  		}
			  			ksort($filters['tags'], SORT_NATURAL | SORT_FLAG_CASE);
			  		}
			  	}

			  	//filter type
		  		if(isset($value['typeSig'])){
		  			if(isset($filters['types'][$value['typeSig']])){
		  				$filters['types'][$value['typeSig']] += 1;
		  			}
		  			else{
		  				$filters['types'][$value['typeSig']] = 1;
		  			}
		  			ksort($filters['types'], SORT_NATURAL | SORT_FLAG_CASE);
		  		}

		  		//filter sourcekey
			  	if(isset($value['source']['key'])){
			  		if(is_array($value['source']['key'])){
				  		foreach ($value['source']['key'] as $keySource => $valueSource) {
					  		if(isset($filters['sourceKey'][$valueSource])){
					  			$filters['sourceKey'][$valueSource] +=1;
					  		}
					  		else{
					  			$filters['sourceKey'][$valueSource] = 1;
					  		}
					  	}
					}
					else{
						if(isset($filters['sourceKey'][$value['source']['key']])){
				  			$filters['sourceKey'][$value['source']['key']] += 1;
				  		}
				  		else{
				  			$filters['sourceKey'][$value['source']['key']] = 1;
				  		}
					}
			  		ksort($filters['sourceKey'], SORT_NATURAL | SORT_FLAG_CASE);
			  	}
			  	$index++;
		  	}
		  	$res['res'] = $limitRes;
	  		$res['filters'] = $filters;
		

	  	Rest::json($res);
		Yii::app()->end();
    }

    //supprime les accents (utilisé pour la recherche de ville pour améliorer les résultats)
    private function wd_remove_accents($str, $charset='utf-8')
	{
		return $str;
	    $str = htmlentities($str, ENT_NOQUOTES, $charset);

	    $str = preg_replace('#&([A-za-z])(?:acute|cedil|caron|circ|grave|orn|ring|slash|th|tilde|uml);#', '\1', $str);
	    $str = preg_replace('#&([A-za-z]{2})(?:lig);#', '\1', $str); // pour les ligatures e.g. '&oelig;'
	    $str = preg_replace('#&[^;]+;#', '', $str); // supprime les autres caractères

	    return $str;
	}

	private function getTypeOfLocalisation($locStr){
		//le cas des localisation intégralement numérique (code postal, insee, departement)
		if(intval($locStr) > 0){
			if(strlen($locStr) <= 3) return "DEPARTEMENT";
			if(strlen($locStr) == 4 || strlen($locStr) == 5) return "CODE_POSTAL_INSEE";
			return "UNDEFINED";
		}else{
			//le cas où le lieu est demandé en toute lettre
			return "NAME";
		}
	}

	private function typeWanted($type, $searchType){
		if($searchType == null) return true;
		return in_array($type, $searchType);
	}
}
