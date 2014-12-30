function avater_edit(){
	
		$uid				= session('uid'); 
		if($_POST){
			
			//var_dump($_POST);exit;
		 
			$pic		= $_POST['pic'];
			$bin		= base64_decode(substr($pic,strpos($pic,',')));
			$str_info  	= @unpack("C2chars", $bin);
        	$type_code 	= intval($str_info['chars1'].$str_info['chars2']);
        	 
        	$file_type  = '';
		 	switch ($type_code) {            
            case 255216:
                $file_type = 'jpg';
                break;
            case 7173:
                $file_type = 'gif';
                break;          
            case 13780:
                $file_type = 'png';
                break;
            default:
                $file_type = '';
                break;
        }
        
        if( empty($file_type) ){
        	$jsonback['status']	= 0;
        	$jsonback['erroe']  = '文件类型错误，只支持jpg、png、gif类型文件';       	
        }else{
        	$ymd 		= date("Ymd");
        	//$savepath 	= dirname($_SERVER["SCRIPT_FILENAME"]).'/data/upload/attached/image/'.$ymd.'/';
        	$urlpath	= C('PIC_DIR').$ymd.'/';
        	$savepath 	= dirname($_SERVER["SCRIPT_FILENAME"]).$urlpath;
        	$filename	=time().rand(10000,99999).rand(10000,99999).'.'.$file_type;    
        	$filename2	=time().rand(10000,99999).rand(10000,99999).'.thumb.'.$file_type;       	
        	if(!is_dir($savepath)) {
				if(!mkdir($savepath)){				 
					$jsonback['status']			= 6011;
					$jsonback['error']			= "DIR CAN NOT BE CREATED";				 
				}
			}else{
				$fp 	= fopen($savepath.$filename,'wb'); 
				fwrite($fp, $bin);	
				fclose($fp);
				
				$x1 = $_POST["x1"];
				$y1 = $_POST["y1"];
				$x2 = $_POST["x2"];
				$y2 = $_POST["y2"];
				$w 	= $_POST["w"];
				$h 	= $_POST["h"];
				//Scale the image to the thumb_width set above
				$thumb_width = "100";						// Width of thumbnail image
				$thumb_height = "100";		
				$scale = $thumb_width/$w;
				$this->resizeThumbnailImage($savepath.$filename2, $savepath.$filename,$w,$h,$x1,$y1,$scale);
				
				 
				
        		$Us 	= M("personal");  
				$data['avater'] =  C('PIC_UPLOAD_URL').'/data/upload/attached/image/'.$ymd.'/'.$filename2;
				$result	= $Us->where('mid='.$uid)->save($data);  
				if($result===false){
			 		$jsonback['status']			= 6000;
					$jsonback['error']			= "system error";
				 
				}else{
					$jsonback['status']			= 1;
					$jsonback['url']			= $data['avater'];
				 
				}
        	
       		}
        
        	}
        	if($jsonback['status']	==1 ){
        		redirect(U('person/index'));
        		exit;
        	}else{
			exit(json_encode($jsonback));
        	}	
		}
		else{
		
		$this->display('personal-center-modify-avatar');
		exit;
		}
		
	}
	
		protected  function resizeThumbnailImage($thumb_image_name, $image, $width, $height, $start_width, $start_height, $scale){
	list($imagewidth, $imageheight, $imageType) = getimagesize($image);
	$imageType = image_type_to_mime_type($imageType);
	
	$newImageWidth = ceil($width * $scale);
	$newImageHeight = ceil($height * $scale);
	$newImage = imagecreatetruecolor($newImageWidth,$newImageHeight);
	switch($imageType) {
		case "image/gif":
			$source=imagecreatefromgif($image); 
			break;
	    case "image/pjpeg":
		case "image/jpeg":
		case "image/jpg":
			$source=imagecreatefromjpeg($image); 
			break;
	    case "image/png":
		case "image/x-png":
			$source=imagecreatefrompng($image); 
			break;
  	}
	imagecopyresized($newImage,$source,0,0,$start_width,$start_height,$newImageWidth,$newImageHeight,$width,$height);
	switch($imageType) {
		case "image/gif":
	  		imagegif($newImage,$thumb_image_name); 
			break;
      	case "image/pjpeg":
		case "image/jpeg":
		case "image/jpg":
	  		imagejpeg($newImage,$thumb_image_name,90); 
			break;
		case "image/png":
		case "image/x-png":
			imagepng($newImage,$thumb_image_name);  
			break;
    }
	chmod($thumb_image_name, 0777);
	 
}
