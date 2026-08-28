<?php

require __DIR__ . '/vendor/autoload.php';
require "util.php";
class Spreadsheet{

	private string $sheetId;
	private Google_Service_Sheets $service;
	public array $headers;
	public Util $util;


	public function __construct($sheetId)
	{

		$this->sheetId=$sheetId;
		$this->service=$this->getService();
		$this->headers=$this->service->spreadsheets_values->get($this->sheetId, "1:1")->getValues()[0];
		$this->util= new Util();

	}
	

	public function getSheetId(): string {
		return $this->sheetId;
	}	



	public function getService(){

		$client = new \Google_Client();

		$client->setApplicationName('Google Sheets and PHP');

		$client->setScopes([\Google_Service_Sheets::SPREADSHEETS]);

		$client->setAccessType('offline');

		$client->setAuthConfig(__DIR__ . '/credentials.json');

		return new Google_Service_Sheets($client);
	}
	
	

	//incorporated json as well as csv, if json then true else return csv
	public function getRange($range) {
		return  $this->service->spreadsheets_values->get($this->sheetId, $range);
		
	}
	
	public function getRangeColumn($column, $startRow, $endRow) {
		$range = "{$column}{$startRow}:{$column}{$endRow}";
		// $range = "P2:P2";

		$params = [
			'valueRenderOption' => 'FORMATTED_VALUE'
				];

		$response = $this->service->spreadsheets_values->get($this->sheetId, $range, $params);		
		$values = $response->getValues() ?? [0];

		// Flatten the response to a simple array
		$columnValues = [];
		foreach ($values as $row) {
			$columnValues[] = $row[0] ?? "";
		}
	
		return json_encode($columnValues, JSON_PRETTY_PRINT);
	}
	


	public function updateRange($range,$data){
		$body = new Google_Service_Sheets_ValueRange([

				'values' => $data

		]);

		$params = [

			'valueInputOption' => 'RAW'

		];

		$update_sheet = $this->service->spreadsheets_values->update($this->sheetId, $range, $body, $params);


	}

	//updates the approval columns with yes or no 
	public function updateRowColumn($row, $column, $data) {

		$range = "$column$row";
	
		$body = new Google_Service_Sheets_ValueRange([
			'values' => [[$data]]
		]);
	
		$params = ['valueInputOption' => 'RAW'];
	
		$this->service->spreadsheets_values->update($this->sheetId, $range, $body, $params);
	}
	

	public function batchGet($ranges) {
        $response = $this->service->spreadsheets_values->batchGet($this->sheetId, ['ranges' => $ranges]);
        return $response->getValueRanges();
    }


	public function csv($data) {
		$fh = fopen('php://temp', 'rw'); # don't create a file, attempt
			foreach ( $data as $row ) {
				fputcsv($fh, $row, ",", "\"", "\\");
			}
		rewind($fh);
		$csv = stream_get_contents($fh);
		fclose($fh);

		return $csv;
	}

	// get last filled rowId
	public function getHighestRow(string $sheetName = "Sheet1"): ?int {
		$range = "!A:Z";
		$response = $this->service->spreadsheets_values->get($this->sheetId, $range);
		$values = $response->getValues();

		if (empty($values)) {
			return null;
		}

		return count($values);
	}


	//get the index from header name
	public function findHeaderIndex(array $headers, string $searchKey) {
		$searchKey = strtolower(trim($searchKey));
		foreach ($headers as $index => $header) {
			$normalized = strtolower(trim($header));
			if (strpos($normalized, $searchKey) !== false) {
				return $index;
			}
		}
		return false;
	}


	// extract sheet ID from URL
	public function extractSheetIdFromUrl(string $url): ?string {
		if (preg_match('/\/d\/([a-zA-Z0-9-_]+)(?:\/|$)/', $url, $matches)) {
			return $matches[1];
		}
		return null;
	}


	// get a cell vlaue by providing COLUMN NAMES and row ID
		public function getCellFromRowByHeader(string $headerName, int $rowNumber): ?string {
				// calculate the column index for the given header
				$columnIndex = array_search($headerName, $this->headers);
			
				$columnName= $this->util->numberToColumnName($columnIndex+1);

				// defininig like "B4"
				$cell = $columnName . $rowNumber;
			
				// get the cell value
				$response = $this->service->spreadsheets_values->get($this->sheetId, $cell);
				$value = $response->getValues()[0][0] ?? "";
				return $value;
			}


		// function to add a new column to the end of the sheet with a given header name
		// public function addNewColumnToEnd(string $newColumnName): void {
		// 	$headers = $this->service->spreadsheets_values
		// 		->get($this->sheetId, "$sheetName!1:1")
		// 		->getValues()[0] ?? [];

		// 	// determine the next empty column
		// 	$newColumnIndex = count($headers);
		// 	$columnLetter= $this->util->numberToColumnName($newColumnIndex+1);

		// 	// set the new header value in the first row of that column so
		// 	$range = "$sheetName!{$columnLetter}1";
		// 	$body = new Google_Service_Sheets_ValueRange([
		// 		'values' => [[$newColumnName]]
		// 	]);

		// 	$params = ['valueInputOption' => 'RAW'];
		// 	$this->service->spreadsheets_values->update($this->sheetId, $range, $body, $params);
		// }


		// public function insertColumnAtIndex(string $sheetId, string $newColumnName, int $columnIndex): void {
		// 	$spreadsheet = $this->service->spreadsheets->get($sheetId);
		// 	$sheet = $spreadsheet->getSheets()[0]; 
		// 	$sheetName = $sheet->getProperties()->getTitle();
		// 	$sheetGid = $sheet->getProperties()->getSheetId();
		
		// 	// insert a blank column at the desired index, 0-based meaning A:0
		// 	$requests = [
		// 		new Google_Service_Sheets_Request([
		// 			'insertDimension' => [
		// 				'range' => [
		// 					'sheetId' => $sheetGid,
		// 					'dimension' => 'COLUMNS',
		// 					'startIndex' => $columnIndex,
		// 					'endIndex' => $columnIndex+1,
		// 				],
		// 				'inheritFromBefore' => true
		// 			]
		// 		])
		// 	];
		
		// 	$batchUpdateRequest = new Google_Service_Sheets_BatchUpdateSpreadsheetRequest([
		// 		'requests' => $requests
		// 	]);
		// 	$this->service->spreadsheets->batchUpdate($sheetId, $batchUpdateRequest);
		
		// 	// now lets set the new column name in the first row
		// 	$columnLetter= $this->util->numberToColumnName($columnIndex+1);
		// 	$range = "{$sheetName}!{$columnLetter}1";
		
		// 	$body = new Google_Service_Sheets_ValueRange([
		// 		'values' => [[$newColumnName]]
		// 	]);
		// 	$params = ['valueInputOption' => 'RAW'];
		
		// 	$this->service->spreadsheets_values->update($sheetId, $range, $body, $params);
		// }

		
		


		public function getSheetName()
		{
			$spreadsheet = $this->service->spreadsheets->get($this->sheetId);
			$sheets = $spreadsheet->getSheets();
			$sheetName = $sheets[0]->getProperties()->getTitle(); 

			return $sheetName;
		}


		// public function getColumnLetterFromHeader($sheetName, $headerName, $apiKey)
		// {
		// 	$url = "https://sheets.googleapis.com/v4/spreadsheets/$this->sheetId/values/$sheetName!1:1?key=$apiKey";

		// 	$response = file_get_contents($url);
		// 	if ($response === false) {
		// 		return "Error fetching data from Google Sheets.";
		// 	}

		// 	$data = json_decode($response, true);
		// 	if (empty($data['values'][0])) {
		// 		return "Header row is empty.";
		// 	}

		// 	$headers = $data['values'][0];
		// 	$searchHeader = strtolower(trim($headerName));

		// 	foreach ($headers as $index => $header) {
		// 		if (strtolower(trim($header)) === $searchHeader) {
		// 			// Convert zero-based index to one-based for Util::numberToColumnName
		// 			return Util::numberToColumnName($index + 1);
		// 		}
		// 	}

		// 	return "Header '$headerName' not found.";
		// }

	public function getSheetData($range)
	{
		$response = $this->service->spreadsheets_values->get($this->sheetId, $range);
		return $response->getValues();
	}

	public function clearSheet($range)
	{
		$requestBody = new Google_Service_Sheets_ClearValuesRequest();
		$this->service->spreadsheets_values->clear($this->sheetId, $range, $requestBody);
	}

	public function updateSheetData($range, $data)
	{
		$body = new Google_Service_Sheets_ValueRange([
			'values' => $data
		]);
		$params = [
			'valueInputOption' => 'RAW'
		];
		$this->service->spreadsheets_values->update($this->sheetId, $range, $body, $params);
	}

	public function batchUpdateValues(array $data)
    {
        $body = new Google_Service_Sheets_BatchUpdateValuesRequest([
            'valueInputOption' => 'RAW',
            'data' => $data
        ]);
        return $this->service->spreadsheets_values->batchUpdate($this->sheetId, $body);
    }

	public function getSpreadsheetTitle(): string
	{
		
		$spreadsheet = $this->service->spreadsheets->get($this->sheetId);
		$spreadsheetTitle = $spreadsheet->getProperties()->getTitle();
		return $spreadsheetTitle;
	}
}