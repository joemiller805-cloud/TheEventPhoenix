#!/usr/local/bin/php.cli
<?php
	include("../common_functions.php");
	
	$resourceID = database_connect();
	
	$query = "show full tables where Table_Type = 'BASE TABLE'";
	$resultID = mysqli_query($resourceID, $query);
	
	$tables = [];
	while ($row = mysqli_fetch_row($resultID))	
	{
		$tables[] = $row[0];
	}

	$date = date("Ymd_His");
	$zip = new ZipArchive;
	$zip->open("/home/easyregpro/private/backups/$date.zip", ZipArchive::CREATE);

	$stdout = fopen("php://output", "w");
	
	foreach($tables as $table)
	{	
		ob_start();
	
		$query = "select * from $table";	
		$resultID = mysqli_query($resourceID, $query);
		$fields = [];
		while($property = mysqli_fetch_field($resultID))
		{
			$fields[] = $property->name;
		}
		fputcsv($stdout, $fields);
		
		while($row = mysqli_fetch_array($resultID, MYSQLI_NUM))
		{
			$data = [];			
			foreach($row as $field)
			{
				$datum = $field;
				$datum = str_replace("\n", "\\n", $datum);
				$datum = str_replace("\r", "\\r", $datum);
				
				$data[] = $datum;
			}			
			fputcsv($stdout, $data);	
		}	
		$output = ob_get_clean();		
		$zip->addFromString("$table.csv", $output);
	}		
	mysqli_close($resourceID);
	fclose($stdout);
	$zip->close();
?>