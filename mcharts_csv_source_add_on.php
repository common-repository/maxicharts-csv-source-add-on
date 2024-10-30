<?php
/*
 * Plugin Name: MaxiCharts CSV Source Add-on
 * Plugin URI: https://maxicharts.com/
 * Description: Extend MaxiCharts : Add the possibility to show beautiful Chartjs graphs from CSV files imported in Wordpress
 * Version: 1.3.2
 * Author: MaxiCharts
 * Author URI: https://wordpress.org/support/users/munger41/
 * License: GPL2
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: mcharts_csv
 * Domain Path: /languages
 */
if (! defined('ABSPATH')) {
	exit();
}

define('CSV_DEFAULT_MAX_ENTRIES', 500);

if (! class_exists('maxicharts_reports')) {
	$corePath = plugin_dir_path(__DIR__);
	$toInclude = $corePath . '/maxicharts/mcharts_utils.php';
	if (file_exists($toInclude)) {
		include_once ($toInclude);
	}
}

require_once __DIR__ . '/libs/vendor/autoload.php';
use League\Csv\Reader;
use League\Csv\Statement;

if (! class_exists('mcharts_csv_source_plugin')) {
	
	class mcharts_csv_source_plugin
	{
		
		protected $csvParameters = null;
		
		function __construct()
		{
			if (! class_exists('MAXICHARTSAPI')) {
				$msg = __('Please install MaxiCharts before');
				return $msg;
			}
			
			self::getLogger()->debug("Adding Module : " . __CLASS__);
			
			add_shortcode('csv2chartjs', array(
				$this,
				'csv2chartjs_shortcode'
			));
			
			add_filter("maxicharts_get_data_from_source", array(
				$this,
				"get_data_from_csv"
			), 10, 3);
			
			add_filter('mcharts_filter_defaults_parameters', array(
				$this,
				'add_default_params'
			));
			add_filter('mcharts_return_without_graph', array(
				$this,
				'return_without_graph'
			));
			self::getLogger()->debug("Added Module : " . __CLASS__);
		}
		
		function return_without_graph($atts)
		{
			return false;
		}
		
		function csv2chartjs_shortcode($atts)
		{
			self::getLogger()->info("Executing shortcode : csv2chartjs");
			if (! is_admin()) {
				$source = 'csv';
				$destination = 'chartjs';
				if (class_exists('maxicharts_reports')) {
					return maxicharts_reports::chartReports($source, $destination, $atts);
				} else {
					$msg = "no class maxicharts_reports";
					self::getLogger()->error($msg);
					return $msg;
				}
			}
		}
		
		function getLogger()
		{
			if (class_exists('MAXICHARTSAPI')) {
				return MAXICHARTSAPI::getLogger('CSV');
			}
		}
		
		function get_data_from_csv($reportFields, $source, $atts)
		{
			if ($source == 'csv') {
				
				$reportFields = array();
				extract(shortcode_atts($this->csvParameters, $atts));
				$type = str_replace(' ', '', $type);
				$url = str_replace(' ', '', $url);
				$columns = maxicharts_reports::get_all_ranges($columns);
				$rows = maxicharts_reports::get_all_ranges($rows);
				$delimiter = str_replace(' ', '', $delimiter);
				MAXICHARTSAPI::getLogger()->debug($columns);
				MAXICHARTSAPI::getLogger()->debug($rows);
				$maxentries = str_replace(' ', '', $maxentries);
				if (empty($maxentries)) {
					$maxentries = CSV_DEFAULT_MAX_ENTRIES;
				}
				$msg = "Using CSV file : " . $url;
				self::getLogger()->debug($msg);
				
				if (empty($columns) && empty($rows)) {
					$msg = "Need at least one row of column to chart";
					self::getLogger()->error($msg);
					return $msg;
				}
				
				$csv = $this->csv_to_array($url, $delimiter);
				$data_retrieved = count($csv);
				if ($data_retrieved){
					
					
					self::getLogger()->debug("Data retrieved : " . count($csv));
				} else {
					self::getLogger()->error("No data retrieved in file : " . $url);
				}
				
				$args = array(
					'columns' => $columns,
					'rows' => $rows,
					'maxentries' => $maxentries,
					'type' => $type,
					'source' => $source
					
				);
				
				$reportFields = $this->csv_array_to_report($csv, $args);
				self::getLogger()->debug($reportFields);
			}
			return $reportFields;
		}
		
		function get_all_ranges($inputRows)
		{
			$rawRows = explode(',', str_replace(' ', '', $inputRows));
			self::getLogger()->debug($rawRows);
			$result = array();
			foreach ($rawRows as $rowsItems) {
				self::getLogger()->debug($rowsItems);
				if (stripos($rowsItems, '-') !== false) {
					$limits = explode('-', $rowsItems);
					$newRows = range($limits[0], $limits[1]);
					$result = array_merge($result, $newRows);
				} else {
					$result[] = $rowsItems;
				}
			}
			
			if (count($result) == 1 && empty($result[0])) {
				$result = false;
			}
			self::getLogger()->debug($result);
			return $result;
		}
		
		function process_csv_source($source, $defaultsParameters, $atts)
		{
			if ($source == 'csv') {
				
				extract(shortcode_atts($this->csvParameters, $atts));
				$xcol = str_replace(' ', '', $xcol);
				$columns = $this->get_all_ranges($columns);
				$rows = $this->get_all_ranges($rows);
				$delimiter = str_replace(' ', '', $delimiter);
				self::getLogger()->debug($columns);
				self::getLogger()->debug($rows);
				$header_start = str_replace(' ', '', $header_start);
				$header_size = str_replace(' ', '', $header_size);
				
				// FIXME : process URL instead of server files
				$msg = "Using CSV file : " . $url;
				self::getLogger()->debug($msg);
				
				if (empty($columns) && empty($rows)) {
					
					$msg = "Need at least one row of column to chart";
					self::getLogger()->error($msg);
					return $msg;
				}
				
				$csvArray = apply_filters('mcharts_csv_file_to_array', $url, $delimiter);
				
				$args = array(
					'columns' => $columns,
					'rows' => $rows,
					'maxentries' => $maxentries,
					'type' => $type,
					'source' => $source
					
				);
				$reportFields = apply_filters('mcharts_csv_array_to_report', $csvArray, $args);
				
				self::getLogger()->debug($reportFields);
			}
			
			return $reportFields;
		}
		
		function add_default_params($defaultsParameters)
		{
			// CSV source
			$newParameters = array(
				'type' => 'bar',
				'url' => '',
				'maxentries' => strval(CSV_DEFAULT_MAX_ENTRIES),
				'columns' => '',
				'rows' => '',
				'delimiter' => '',
				'information_source' => ''
			);
			
			$this->csvParameters = array_merge($defaultsParameters, $newParameters);
			
			return $this->csvParameters;
		}
		
		/*
		 * function add_root_logger_appender($rootAppenders)
		 * {
		 * $rootAppenders[] = __CLASS__;
		 *
		 * return $rootAppenders;
		 * }
		 *
		 * function add_log_appender($appenders)
		 * {
		 * $appenders[__CLASS__] = array(
		 * 'class' => 'LoggerAppenderDailyFile',
		 * 'layout' => array(
		 * 'class' => 'LoggerLayoutPattern',
		 * 'params' => array(
		 * 'conversionPattern' => "%date{Y-m-d H:i:s,u} %logger %-5level %F{10}:%L %msg%n"
		 * )
		 * ),
		 *
		 * 'params' => array(
		 * 'file' => $appenders['mcharts_core']['params']['file'],
		 * 'append' => true,
		 * 'datePattern' => "Y-m-d"
		 * )
		 * );
		 *
		 * return $appenders;
		 * }
		 */
		function is_valid_name($file)
		{
			return preg_match('/^([-\.\w]+)$/', $file) > 0;
		}
		
		function downloadAndWriteFileToServer($url)
		{
			// $url = 'http://doman.com/path/to/file.mp4';
			$upload_dir = wp_upload_dir();
			$destination_folder = $upload_dir['path'];
			$destination_url = $upload_dir['url'];
			
			$distantFilename = basename($url);
			if (! $this->is_valid_name($distantFilename)) {
				self::getLogger()->error("downloadAndWriteFileToServer::invalid filename " . $distantFilename);
			}
			
			$newfname = trailingslashit($destination_folder) . $distantFilename; // set your file ext
			$local_url = trailingslashit($destination_url) . $distantFilename;
			if ($local_url == $url ) {
				self::getLogger()->warn("downloadAndWriteFileToServer::File already on local server " . $url);
			} else {
				
				file_put_contents($newfname, file_get_contents($url));
			}/* else {
			$ch = curl_init($url);
			
			$fp = fopen($newfname, ‘wb’);
			
			curl_setopt($ch, CURLOPT_FILE, $fp);
			curl_setopt($ch, CURLOPT_HEADER, 0);
			curl_exec($ch);
			curl_close($ch);
			fclose($fp);
			}*/
			self::getLogger()->debug("Downloaded to " . $newfname);
			/*if (! $this->is_valid_name($newfname)) {
			 self::getLogger()->error("invalid filename " . $newfname);
			 }*/
			return $newfname;
		}
		
		function csv_to_array($filename, $delimiter)
		{
			self::getLogger()->debug("###::csv_to_array:" . $filename);
			$errorMsg = '';
			$realPathFilename = realpath($filename);
			$results = array();
			$csv = null;
			if (file_exists($realPathFilename)) {
				self::getLogger()->debug("server path :" . $realPathFilename);
			} else {
				self::getLogger()->debug("URL :" . $filename);
				$realPathFilename = $this->downloadAndWriteFileToServer($filename);
			}
			
			if (is_readable($realPathFilename)) {
				self::getLogger()->debug("Read with delimiter : '" . $delimiter . "'");
				try {
					self::getLogger()->debug("try reading from path :" . $realPathFilename);
					$csv = Reader::createFromPath($realPathFilename);
				} catch (Exception $e) {
					// Handle exception
					$errorMsg = 'cannot read file : ' . $realPathFilename;
				}
			} else {
				$errorMsg = 'SERVER cannot read file : ' . $realPathFilename;
			}
			
			if (! empty($errorMsg)) {
				self::getLogger()->error($errorMsg);
				return false;
			}
			if (null === $csv) {
				self::getLogger()->fatal("Cannot read data source " . $filename);
				return false;
			}
			
			if (! empty($delimiter)) {
				$csv->setDelimiter($delimiter);
			}
			
			self::getLogger()->debug("delimiter set : '" . $csv->getDelimiter() . "'");
			
			$input_bom = $csv->getInputBOM();
			
			if ($input_bom === Reader::BOM_UTF16_LE || $input_bom === Reader::BOM_UTF16_BE) {
				$csv->appendStreamFilter('convert.iconv.UTF-16/UTF-8');
			}
			
			self::getLogger()->debug("input bom : '" . $input_bom . "'");
			
			return $csv;
		}
		
		/*
		 *
		 * function build_array_from_csv_filepath($url)
		 * {
		 * self::getLogger()->debug("build_array_from_csv_filepath");
		 * $csvArray = $this->csv_to_array($url);
		 * return $csvArray;
		 * }
		 */
		function csv_array_to_report($csv, $args)
		{
			$reportFields = array();
			if (! $csv) {
				self::getLogger()->error("No csv provided");
				return $reportFields;
			}
			$columns = $args['columns'];
			$rows = $args['rows'];
			$maxentries = $args['maxentries'];
			$type = $args['type'];
			$source = $args['source'];
			
			self::getLogger()->debug(count($columns) . ' columns to get');
			self::getLogger()->debug(count($rows) . ' lines parsed');
			// self::getLogger()->debug($maxColsNb . ' columns in file');
			self::getLogger()->debug('Rows to get ' . implode('/', $rows));
			self::getLogger()->debug($rows);
			self::getLogger()->debug('Columns to get ' . implode('/', $columns));
			self::getLogger()->debug($columns);
			$firstRow = min($rows);
			$firstCol = min($columns);
			
			if (empty($firstCol)) {
				self::getLogger()->error("empty first column");
				// return $reportFields;
			}
			
			if (empty($firstRow)) {
				self::getLogger()->error("empty first row");
				// return $reportFields;
			}
			
			// $csv->setHeaderOffset(intval($firstRow));
			$maxRowToFetch = min(max($rows), $maxentries);
			self::getLogger()->debug("#### Extract data from row " . $firstRow . ' to ' . $maxRowToFetch);
			$stmt = (new Statement())->offset($firstRow)->limit($maxRowToFetch);
			$records = $stmt->process($csv);
			$firstRowData = $stmt->process($csv)->fetchOne(0);
			$maxColsNb = count($firstRowData);
			self::getLogger()->debug("Number of records in CSV : " . count($records));
			self::getLogger()->debug("Number of items in record : " . $maxColsNb);
			//
			// $maxColsNb = count($csvArray[0]);
			
			$stmt = (new Statement())->offset(1)->limit(5);
			$demoData = $stmt->process($csv);
			self::getLogger()->debug($demoData);
			// echo '<pre>', PHP_EOL;
			// self::getLogger()->debug( json_encode($demoData, JSON_PRETTY_PRINT));
			// self::getLogger()->debug(array_slice($csvArray, 0, 5));
			
			$vals = array_values($columns);
			$rowTitleColIdx = array_shift($vals);
			
			$rowTitles = array();
			
			$colTitles = $firstRowData; // $csv->getHeader();//: $csvArray[$firstRow];
			$index = 0;
			
			// get all row titles
			$firstColumnNumberToFetch = reset($columns);
			self::getLogger()->debug("Get label from col " . $firstColumnNumberToFetch);
			self::getLogger()->debug($rows);
			/*
			 * $labelsColumn = $records->fetchColumn($firstColumnNumberToFetch);
			 * self::getLogger()->debug($labelsColumn);
			 *
			 * $rowIdx = 0;
			 * foreach ($labelsColumn as $value) {
			 * // $value is a string representing the value
			 * // of a given record for the selected column
			 * self::getLogger()->debug($rowIdx . " => " . $value);
			 * // first row set by user is not data but header, so skip it and get data after that
			 * $dataIdx = $rowIdx - 1;
			 * if (! in_array($dataIdx, $rows)) {
			 * self::getLogger()->debug("--- Skipping row : " . $rowIdx . " => " . $value);
			 * } else {
			 * $rowTitles[] = $value;
			 * self::getLogger()->debug("+++ New value added : " . $rowIdx . " => " . $value);
			 * }
			 * $rowIdx ++;
			 *
			 * }
			 */
			/*
			 * foreach ($csvArray as $csvRowKey => $csvLine) {
			 * // get no more than maxentries
			 * if ($index >= $maxentries) {
			 * self::getLogger()->debug('max reached ' . $maxentries);
			 * break;
			 * }
			 *
			 * // skips first rows
			 * if ($index <= $firstRow || (is_array($rows) && count($rows) > 0 && ! in_array($csvRowKey, $rows))) {
			 * $index ++;
			 * continue;
			 * }
			 *
			 * // get row titles
			 * $rowTitles[] = $csvLine[$rowTitleColIdx];
			 * $index ++;
			 * }
			 */
			
			self::getLogger()->debug('ROW TITLES : ' . implode(' / ', $rowTitles));
			self::getLogger()->debug('COL TITLES : ' . implode(' / ', $colTitles));
			
			$entriesProcessed = 0;
			/*
			 * foreach ($records as $record) {
			 * //do something here
			 * }
			 */
			
			$rowIdx = $firstRow; // $csv->getHeaderOffset();
			foreach ($records as $record) {
				// get no more than maxentries
				if ($entriesProcessed >= $maxentries) {
					self::getLogger()->debug('max reached ' . $maxentries);
					break;
				}
				// skips not wanted row values
				if ((is_array($rows) && count($rows) > 0 && ! in_array($rowIdx, $rows))) {
					$rowIdx ++;
					continue;
				}
				
				// header skipped
				if ($rowIdx == $firstRow) {
					self::getLogger()->debug('Title row skipped' . $rowIdx . ' not in ' . implode('/', $record));
					$entriesProcessed ++;
					$rowIdx ++;
					continue;
				}
				
				self::getLogger()->debug("Processing record");
				self::getLogger()->debug($record);
				
				// get all other data as reportFields datasets
				$currentRowTitle = $record[$firstCol];
				if (in_array($rowIdx, $rows)) {
					
					$withoutSpaces = str_replace(" ", "", $currentRowTitle);
					self::getLogger()->debug("+++ New x axis value added : " . $rowIdx . " => " . $currentRowTitle . ' / ' . $withoutSpaces);
					if (is_numeric($withoutSpaces)) {
						self::getLogger()->debug("Seems space inside number, converting : " . $currentRowTitle . " => " . $withoutSpaces);
						$currentRowTitle = $withoutSpaces;
					}
					$rowTitles[] = $currentRowTitle;
				}
				
				$colIdx = $firstCol;
				foreach ($record as $item) {
					// skips unwanted columns
					if ($colIdx == $firstCol || (is_array($columns) && count($columns) > 0 && ! in_array($colIdx, $columns))) {
						$colIdx ++;
						continue;
					}
					
					$colTitle = $colTitles[$colIdx];
					
					$regular_spaces = str_replace("\xc2\xa0", ' ', $item);
					$itemWithoutSpaces = str_replace(" ", "", $regular_spaces);
					
					if (is_numeric($item)) {
						$valueToCatch = $this->tofloat($item);
					} else if (is_numeric($itemWithoutSpaces)) {
						$valueToCatch = $this->tofloat($itemWithoutSpaces);
					} else {
						$valueToCatch = '"' . strval($item) . '"';
					}
					self::getLogger()->debug('*** Process ' . $rowIdx . ' (' . $currentRowTitle . ') / ' . $colIdx . ' (' . $colTitle . ') -> ' . $valueToCatch . ' / ' . $itemWithoutSpaces);
					$reportFields[0]['datasets'][$colTitle]['data'][$currentRowTitle] = $valueToCatch;
					
					$colIdx ++;
				}
				$rowIdx ++;
				$entriesProcessed ++;
			}
			
			$reportFields[0]['labels'] = $rowTitles;
			$reportFields[0]['multisets'] = 1;
			$reportFields[0]['graphType'] = $type;
			$reportFields[0]['type'] = $source;
			/*
			 * if (!isset($reportFields[0]['label'])){
			 * $reportFields[0]['label'] = 'no title';
			 * }
			 */
			
			self::getLogger()->debug($reportFields);
			
			return $reportFields;
		}
		
		function tofloat($num)
		{
			$dotPos = strrpos($num, '.');
			$commaPos = strrpos($num, ',');
			$sep = (($dotPos > $commaPos) && $dotPos) ? $dotPos : ((($commaPos > $dotPos) && $commaPos) ? $commaPos : false);
			
			if (! $sep) {
				return floatval(preg_replace("/[^0-9]/", "", $num));
			}
			
			return floatval(preg_replace("/[^0-9]/", "", substr($num, 0, $sep)) . '.' . preg_replace("/[^0-9]/", "", substr($num, $sep + 1, strlen($num))));
		}
		
		/*
		 * function csvColumnAnalysis($csvArray, $header_start, $header_size, $xCol, $yCol, $type, $compute, $maxentries)
		 * {
		 * $collectionArray = array();
		 * self::getLogger()->debug("### Options : " . implode('/', array(
		 * $header_start,
		 * $header_size,
		 * $xCol,
		 * $yCol,
		 * $type,
		 * $compute,
		 * $maxentries
		 * )));
		 * $idx = 0;
		 * $title = "";
		 * $results = array();
		 * if ($compute == 'SUM') {
		 * foreach ($csvArray as $key => $values) {
		 * if ($header_start > $idx) {
		 * $idx ++;
		 * continue;
		 * }
		 *
		 * if ($idx >= $maxentries) {
		 * break;
		 * }
		 * if ($header_size >= $idx && $idx > $header_start) {
		 * $title .= $values[$yCol];
		 * } else {
		 * $collectionArray[] = $values[$yCol];
		 * }
		 *
		 * $idx ++;
		 * }
		 * $results['scores'] = array_count_values($collectionArray);
		 * $results['data'] = array_values($results['scores']);
		 * $results['labels'] = array_keys($results['scores']);
		 * } else {
		 * self::getLogger()->debug("#### Build chart with max " . $maxentries . " CSV datas of column " . $yCol);
		 * foreach ($csvArray as $key => $values) {
		 *
		 * if ($header_start > $idx) {
		 * self::getLogger()->warn('skip ' . $idx);
		 * $idx ++;
		 *
		 * continue;
		 * }
		 * if ($idx >= $maxentries) {
		 * self::getLogger()->warn('max reached ' . $maxentries);
		 * break;
		 * }
		 * if (($header_start + $header_size) > $idx && $idx > $header_start) {
		 * $title .= empty($values[$yCol]) ? '' : $values[$yCol] . '\n';
		 * self::getLogger()->warn('---------- title: ' . $title);
		 * $idx ++;
		 * continue;
		 * } else {
		 *
		 * $valueToCatch = $values[$yCol];
		 * $trimed = $this->clean($valueToCatch);
		 * self::getLogger()->debug($valueToCatch . ' => ' . $trimed);
		 * if (is_numeric($trimed)) {
		 * $valueToCatch = $trimed; // str_replace (' ','',$values [$yCol]);
		 * } else {
		 *
		 * $valueToCatch = '"' . $this->removeQuotesAndConvertHtml($valueToCatch) . '"';
		 * }
		 *
		 * $collectionArray[$values[$xCol]] = $valueToCatch;
		 * self::getLogger()->debug($idx . ' :: ' . $values[$xCol] . ' -> ' . $values[$yCol]);
		 * }
		 * $idx ++;
		 * }
		 *
		 * self::getLogger()->debug($collectionArray);
		 * $results['data'] = array_values($collectionArray);
		 * $results['labels'] = array_keys($collectionArray);
		 * }
		 *
		 * if (! empty($title)) {
		 * $results['label'] = $title;
		 * }
		 *
		 * if (!isset($results['data']) || empty($results['data'])){
		 * $currentChartMsg = __('No Data pulled from CSV ');
		 * self::getLogger ()->error ( $currentChartMsg );
		 * self::getLogger ()->error ( $csvArray );
		 * //$allCharts .= $currentChartMsg;
		 * //continue;
		 * }
		 *
		 * return $results;
		 * }
		 */
		function replace_carriage_return($replace, $string)
		{
			return str_replace(array(
				"\n\r",
				"\n",
				"\r"
			), $replace, $string);
		}
		
		function removeQuotesAndConvertHtml($str)
		{
			$res = preg_replace('/["]/', '', $str);
			$res = html_entity_decode($res);
			$res = $this->replace_carriage_return(" ", $res);
			
			return $res;
		}
		
		function removeQuotes($string)
		{
			return preg_replace('/["]/', '', $string); // Removes special chars.
		}
		
		function clean($string)
		{
			$string = str_replace(' ', '', $string); // Replaces all spaces with hyphens.
			
			return preg_replace('/[^A-Za-z0-9\-]/', '', $string); // Removes special chars.
		}
	}
}
new mcharts_csv_source_plugin();

/**
 * Restore CSV upload functionality for WordPress 4.9.9 and up
 */
add_filter('wp_check_filetype_and_ext', function($values, $file, $filename, $mimes) {
	if ( extension_loaded( 'fileinfo' ) ) {
		// with the php-extension, a CSV file is issues type text/plain so we fix that back to
		// text/csv by trusting the file extension.
		$finfo     = finfo_open( FILEINFO_MIME_TYPE );
		$real_mime = finfo_file( $finfo, $file );
		finfo_close( $finfo );
		
		if ( $real_mime === 'text/plain' && preg_match( '/\.(csv)$/i', $filename ) ) {
			$values['ext']  = 'csv';
			$values['type'] = 'text/csv';
		}
	} else {
		// without the php-extension, we probably don't have the issue at all, but just to be sure...
		if ( preg_match( '/\.(csv)$/i', $filename ) ) {
			$values['ext']  = 'csv';
			$values['type'] = 'text/csv';
		}
	}
	
	return $values;
}, PHP_INT_MAX, 4);