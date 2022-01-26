<?php
namespace Poisson;

class Generator
{
	
	//This class will put points at random on a map using Poisson-disc placement pattern
	//https://www.jasondavies.com/poisson-disc/
	//It can either generate an image by itself or modify an existing image. It can also return a simple array of points if needed
	
	static function GeneratePicture($size)
	{
		header('Content-Type: image/png');
		$im = imagecreatetruecolor($size,$size);
		$colOFF = imagecolorallocatealpha($im, 255, 0, 255, 0);
		imagefill($im, 0, 0, $colOFF);
		
		$im = self::GeneratePoints($im);
		imagepng($im);
		imagedestroy($im);
	}
	
	//Description: Generate Points creates the Poisson-disc repartition and returns either an image or a list of points
	 //$seed = If no seed is fed, time will be used, causing the result to change at every refresh
	//$radius = INT radius between points 
	//$radiusVariations = FLOAT potential variation between radiuses
	//$sampleRegionSize = size of the sampling, if -1 defaults to picture size
	//$numSamplesBeforeRejection
	//$image = IMG the base image, if null, the function will return the array of points
	//MinReturnValue & MaxReturnValue = color range
	
	
	static function GeneratePoints(
	$image = 100, // the base image, if null the function will return the array of points
	$seed = null, //If no seed is fed, time will be used, causing the result to change at every refresh
	//$radius = 4, 
	$maxPoints = 10000, // max limit of points to generate. Once reached, stops generating
	$minRadius = 4,
	$maxRadius = 4,
	$minReturnValue = 255,
	$maxReturnValue = 255,
	$sampleRegionSize = -1,
	$numSamplesBeforeRejection = 20,
	$forbiddenZone = null) 
	{
		//if sample region size is -1, make it equal to file size 
		if ($sampleRegionSize == -1)
			{
				if(in_array($image , array(IMAGETYPE_GIF , IMAGETYPE_JPEG ,IMAGETYPE_PNG , IMAGETYPE_BMP)))
					{
						$sampleRegionSize = 512;//getimagesize($image)[0];
					}
			}
		
		
		//Let's get the dimensions of the image, if applicable
		list($width, $height) = array($sampleRegionSize, $sampleRegionSize);
		if ($image != null)
		{
			list($width, $height) = getimagesize($image);
		}
		
		$radius = ($minRadius + $maxRadius) / 2;
		$cellSize = $radius/sqrt (2);

		$grid = array(array(),array());
						
		$gridScope = ceil($sampleRegionSize / $cellSize);
		
		//This array will store the final candidates
		$points = array(); 
		
		//This array stores the confirmed spawn points to be tested. Once we have exhausted all samples, the spawn points will be removed from the list 
		$spawnPoints = array(array("x" => $sampleRegionSize/2,
							 "y" =>$sampleRegionSize/2));
		
		$increments = 0;
		
		//Here goes the actual loop
		while(count($spawnPoints) > 0 && $maxPoints> $increments )
			{
				if ($seed != null)
				{
					mt_srand($seed);
				}
				$spawnIndex = mt_rand(0,count($spawnPoints)-1);
				$spawnCentre = $spawnPoints[$spawnIndex];
				$candidateAccepted = false;
				for ($i = 0; $i < $numSamplesBeforeRejection; $i += 1)
				{
					//We determine a direction at random. 
					$angle = (mt_rand (0,100000)/100000) * pi() * 2;
					$dir = array(number_format(sin($angle), 1), number_format(cos($angle), 1));
					
					//Let's create the candidate we will evaluate next
					$candidate = array("x" => $spawnCentre["x"] + $dir[0] * (mt_rand($radius * 1000000, 2*$radius * 1000000)/1000000),
							 "y" =>$spawnCentre["y"] + $dir[1] * (mt_rand($radius * 1000000, 2*$radius * 1000000)/1000000));
							 if ($minRadius != null && $maxRadius != null && $maxRadius > $minRadius)
							 {
								$radius = mt_rand($minRadius * 1000000, $maxRadius * 1000000)/1000000;			
							 }
					//Let's now actually test out candidate
					$valid = self::IsValid($candidate, $sampleRegionSize, $cellSize, $radius, $points, $grid, $gridScope);
					//var_dump($valid);
					if ($valid == 0) // Rejected
						{
							$seed++;
						}
					if ($valid == 1) // Accepted
						{
							array_push($points, $candidate);
							array_push($spawnPoints, $candidate);
							$dataVal = mt_rand($minReturnValue, $maxReturnValue);			
							if ($image != null)
							{
								$basecolor = imagecolorat($image, $candidate["x"], $candidate["y"]);
								$colors = imagecolorsforindex($im, $rgb);
								$color = imagecolorallocatealpha($image, $colors["red"], $colors["green"], $dataVal, $colors["alpha"]);
								imagesetpixel($image, $candidate["x"], $candidate["y"], $color);
							}
							$grid[(int)($candidate["x"]/$cellSize)][(int)($candidate["y"]/$cellSize)] = count($points);
							$candidateAccepted = true;
							break;
						}
					if ($valid == 2) // Accepted but won't generate because out of bounds
						{
							array_push($points, $candidate);
							$candidateAccepted = true;
							break;
						}
				}
				if (!$candidateAccepted) 
					{
						array_splice($spawnPoints, $spawnIndex, 1);
					}	
				$increments++;
				$seed++;
			}
		if ($image != null)
			{	
				return $image;
			}
		return $points;
	}	
	
	
	static function GeneratePointsFromArray(
		$array
		$minPoints,
		$maxPoints,
			)
			{
				
			}
		
	//candidate is a vector, cellsize a float, radius a float, points a List of vectors, grid a 2D array
	static function IsValid($candidate, $sampleRegionSize, $cellSize, $radius, $points, $grid, $gridScope)
	{
		
		if ($candidate["x"] >=0 && $candidate["x"] < $sampleRegionSize && $candidate["y"]<$sampleRegionSize) 
		{
			(int)$cellX = (int)($candidate["x"] / $cellSize);
			(int)$cellY = (int)($candidate["y"] / $cellSize);
			$searchStartX = max(0, $cellX -2);
			$searchEndX = min($cellX+2,$gridScope-1); 
			$searchStartY = max(0,$cellY -2);
			$searchEndY = min($cellY+2,$gridScope-1); 
			
			
			for ($x = $searchStartX;  $x<= $searchEndX; $x++) 
			{
				for ($y = $searchStartY; $y <= $searchEndY; $y++) 
				{
					$pointIndex = $grid[$x][$y]-1;
					if ($pointIndex != -1) 
					{
						$sqrDst = self::sqrmag($candidate["x"] - $points[$pointIndex]["x"], $candidate["y"] - $points[$pointIndex]["y"]);
						if ($sqrDst < $radius*$radius) 
						{
							//Return 0 means we have a real invalid point, it shouldn't return anything
							return 0;
						}
					}
				}
			}
			//point is tested and valid
			return 1;
			
		}
		//This point is valid but out of the boundaries. We add it to the list but with a tag 
		return 2;
	}
	
	static function mag($x, $y)
	{
		return sqrt(pow($x,2) + pow($y,2));
	}
	
	static function sqrmag($x, $y)
	{
		return pow($x,2) + pow($y,2);
	}
}
?>