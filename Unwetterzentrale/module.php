<?

	class Unwetterzentrale extends IPSModule
	{
		
		private $imagePath;
		
		public function __construct($InstanceID)
		{
			//Never delete this line!
			parent::__construct($InstanceID);
			
			//You can add custom code below.
			$this->imagePath = "media/radar".$InstanceID.".jpg";
			
		}
		
		public function Create()
		{
			//Never delete this line!
			parent::Create();
			
			$this->RegisterPropertyString("area", "SHS");
			$this->RegisterPropertyInteger("homeX", 420);
			$this->RegisterPropertyInteger("homeY", 352);
			$this->RegisterPropertyInteger("homeRadius", 10);
			$this->RegisterPropertyInteger("Interval", 900);
			
			$this->RegisterTimer("UpdateTimer", 900 * 1000, 'UWZ_RequestInfo($_IPS[\'TARGET\']);');
		}
	
		public function ApplyChanges()
		{
			//Never delete this line!
			parent::ApplyChanges();
			
			$this->RegisterVariableInteger("RainValue", "Regenwert");

		}

		private function ConvertArea($area) {

			switch($area) {
				case "DL":
					return "brd";
				case "BWB":
					return "baw";
                case "BAY":
                    return "bay";
                case "BRA":
                    return "bbb";
                case "HES":
                    return "hes";
                case "MVP":
                    return "mvp";
                case "NIE":
                    return "nib";
                case "NRW":
                    return "nrw";
                case "RHP":
                    return "rps";
                case "SAC":
                    return "sac";
                case "SAH":
                    return "saa";
                case "SHS":
                    return "shh";
                case "THU":
                    return "thu";
				default:
					throw new Exception("Unknown area");
			}

		}

		/**
		* This function will be available automatically after the module is imported with the module control.
		* Using the custom prefix this function will be callable from PHP and JSON-RPC through:
		*
		* UWZ_RequestInfo($id);
		*
		*/
		public function RequestInfo()
		{
		
			$imagePath = IPS_GetKernelDir() . $this->imagePath;
			$area = $this->ReadPropertyString("area");
			$homeX = $this->ReadPropertyInteger("homeX");
			$homeY = $this->ReadPropertyInteger("homeY");
			$homeRadius = $this->ReadPropertyInteger("homeRadius");
			
			//Download picture
			$opts = array(
			'http'=>array(
				'method'=>"GET",
				'max_redirects'=>1,
				'header'=>"User-Agent: "."Mozilla/5.0 (Windows NT 10.0; WOW64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/50.0.2661.102 Safari/537.36"
			)
			);
			$context = stream_context_create($opts);

			$remoteImage = "https://www.dwd.de/DWD/wetter/radar/rad_" . $this->ConvertArea($area) ."_akt.jpg";
			$data = @file_get_contents($remoteImage, false, $context);

			$this->SendDebug($http_response_header[0], $remoteImage, 0);
			
			if((strpos($http_response_header[0], "200") === false)) {
				echo $http_response_header[0]." ".$data;
				return;
			}

			file_put_contents($imagePath, $data);

			$mid = $this->RegisterMediaImage("RadarImage", "Radarbild", $this->imagePath);
			
			//Bild aktualisiern lassen in IP-Symcon
			IPS_SendMediaEvent($mid);
			
			//Radarbild auswerten
			$im = ImageCreateFromJPEG($imagePath);

			//Stärken
			$regen[6] = imagecolorresolve($im, 241,2,8);
			$regen[5] = imagecolorresolve($im, 238,6,206);
			$regen[4] = imagecolorresolve($im,  4,4,242);
			$regen[3] = imagecolorresolve($im,  25,216,242);
			$regen[2] = imagecolorresolve($im,  0,119,0);
			$regen[1] = imagecolorresolve($im, 228,240,92);

			//Pixel durchgehen
			$regenmenge = 0;
			for($x=$homeX-$homeRadius; $x<=$homeX+$homeRadius; $x++) {
				for($y=$homeY-$homeRadius; $y<=$homeY+$homeRadius; $y++) {
					$found = array_search(imagecolorat($im, $x, $y), $regen);
					if(!($found === FALSE)) {
						$regenmenge+=$found;
					}
				}
			}

			// Bereich zeichnen
			$rot = ImageColorAllocate ($im, 255, 0, 0);
			imagerectangle($im, $homeX-$homeRadius, $homeY-$homeRadius, $homeX+$homeRadius, $homeY+$homeRadius, $rot);
			imagesetpixel($im, $homeX, $homeY, $rot);
			imagejpeg($im, $imagePath);

			imagedestroy($im);

			SetValue($this->GetIDForIdent("RainValue"), $regenmenge);
			
		}
		
		private function RegisterMediaImage($Ident, $Name, $Path) {
		
			//search for already available media with proper ident
			$mid = @IPS_GetObjectIDByIdent($Ident, $this->InstanceID);
		
			//properly update mediaID
			if($mid === false)
				$mid = 0;
				
			//we need to create one
			if($mid == 0)
			{
				$mid = IPS_CreateMedia(1);
				
				//configure it
				IPS_SetParent($mid, $this->InstanceID);
				IPS_SetIdent($mid, $Ident);
				IPS_SetName($mid, $Name);
				//IPS_SetReadOnly($mid, true);
			}

			//update path if needed
			if(IPS_GetMedia($mid)['MediaFile'] != $Path) {
                IPS_SetMediaFile($mid, $Path, false);
			}

            return $mid;
			
		}
	
	}

?>
