<?php

/**
 *  @Author Marcelo Gennari
 *  @Version 1.0.1
 *
 * Convert database resultset into an html table (works with any bidimentional array)
 * Features: Custom field labels, Chart background, Arbitrary column width, SUM/AVG, Number Formatting, Expressions with SUM/AVG fields
 *
 *
 * SIMPLE USE:
 *
 *  $data = array (
 *     array ("AAA", "500"  , "3000" , "1"  )
 *    , array ("BBB", "1000", "2000" , "0.15" )
 *    , array ("CCC", "2000", "1000" , "0.35" )
 *    , array ("DDD", "3000", "300"  , "0.25" )
 *  );
 *
 * $tableRender = new TableRender($data); $simpleHtmlTable = $tableRender->render();
 *
 *
 *
 * ADVANCED USE:
 *
 *  $formatter = array (
 *    array (
 *      "footer" => "TOTAL"
 *      , "header" => "Place"
 *    )
 *    , array (
 *      "footer" => "sum"
 *      , "format" => "number_format(cell, 2, '.', ',')"
 *      , "header" => "Sales"
 *      , "link" => "somepage.php?module=sales&parameter=column[0]"
 *      , "width" => "50"
 *      , "graph" => array ("min" => 0, "max" => 10000)
 *      , "css_class" => "bargraph_100"
 *    )
 *    , array (
 *      "header" => "Revenues"
 *      , "footer" => "sum"
 *      , "format" => "number_format(cell, 2, '.', ',')"
 *      , "graph" => array ("min" => 0) // max leaved undeclared for automatic scale
 *      , "width" => "100"
 *    )
 *    , array (
 *      "header" => "Market Share"
 *      , "footer" => "avg"
 *      , "format" => "number_format((cell * 100), 2, '.', '') . '%'"
 *      , "graph" => array ("min" => 0, "max" => 1)
 *    )
 *  );
 *
 *  $tableRender = new TableRender($data, 'id="table" class="table"', $formatter);
 *  $fancyHtmlTable = $tableRender->render();
 *
 *
 *
 * FORMATTER:
 *
 *   An array with instructions of how to render each column.
 *   Each instruction item must be an associative array. The optional parameters are:
 *
 *   header (string):
 *     Label of the first row (header). Must be a non-spaced word. Defaults to data-array column keys
 *     Eg: "header" => "Sales"
 *
 *   footer (string):
 *     Last row, it can have 3 types of consolidation
 *       1. Keywords: "sum" or "avg"
 *          Eg: "footer" => "sum"
 *       2. Label: Any NON-SPACED word
 *          Eg: "footer" => "TOTAL"
 *       3. PHP expression: cells can be indicated by its key. Can be used for percentage calculation of "sum" and "avg" fields.
 *          Eg: "'price_floor_highest' + 'price_last_negociated'"
 *
 *   format (string):
 *     Any php expression. The target content must be called "cell". Defaults to no-format.
 *     Eg:  "format" => "number_format(cell, 2, '.', ',')"
 *
 *   width (string):
 *     Force the min column width to the given pixels amount. Defaut: none for non-graphic column and 50 when graph is declared.
 *     Eg: "width" => "100"
 *
 *   graph (array):
 *     Draws a barchart on numeric column.
 *     The array can have "min" and "max" values. If you want auto-scale, declare an empty array.
 *     You MUST declare a css style like this: .bargraph_100 { background: url('img/bargraph_100.png') no-repeat; }
 *       About bargraph_100.png: can be any image with 100 pixels width. Height is your choice, 30px is enough.
 *       +-------+-------+
 *       |###############|
 *       +-------+-------+
 *       |<----100px---->|
 *     You MUST declare width when using this option. The width must have the same size of the css's image
 *     Eg:  "graph" => array ();                                 // autoscale
 *          "graph" => array ("min" => -100, "max" => 100)       // declared limits
 *
 *   link (string):
 *    Each cell's value will be transformed into a hyperlink pointing to this address
 *    You can use the value of any column by referencing by its index (starting at zero) Ex:column[0]
 *    This feature is specially useful when you want to "drill down" a numeric sum into a list of individual registries
 *    Eg:  "link" => "somepage.php?module=sales&parameter=column[0]"
 *
 *   css_class (string):
 *     Define which css class should be applied to each column. (Excluding header and footer rows) Defaut: cellgraph_50
 *     css
 *    Eg: "css_class" => "bargraph_100"
 *
 *  Tools used to develop this class: Laragon Dev Server, Notepad++ With DBG plugin, jEdit.
 *
 */

class TableRender {

	private $data = array();
	private $rows = 0;
	private $columns = 0;
	private $formatter = false;
	private $complement = false;


	/**
	 * Load the constructor's optional parameters
	 * 0: array to be rendered
	 * 1: complement (Extra parameters of <table> tag)
	 * 2: formatter (Array with instructions on how to renderize each column)
	 */
	function __construct() {
		$numargs = func_num_args();
		$args = func_get_args();
		for ($i = 0; $i < $numargs; $i++) {
			switch ($i) {
				case 0:
					$this->setData($args[0]);
					break;
				case 1:
					$this->setComplement($args[1]);
					break;
				case 2:
					$this->setFormatter($args[2]);
					break;
				default:
					//pass through
			}
		}
	}


	/**
	 * Renders the array into html
	 */
	public function render() {

		// Exit the function in case of empty array
		if ($this->getRows() == 0) {
			return;
		}

		// Creates an empty row with the same array structure as template to be used ahead
		$columnStructure = $this->data[0]; //creates a copy of 1st row
		foreach($columnStructure as $colIdx => $cell) $columnStructure[$colIdx] = false; //fill all its values with false

		// The format array has the formatting to be applied to each cell
		$format = $columnStructure; //Creates the $format array which will contain formatter strings for each column
		if ($this->formatter !== false) {
			foreach($format as $colIndex => $cell) {
				if (array_key_exists('format', $this->formatter[$colIndex])) {
					$format[$colIndex] = 'return ' . str_replace("cell", "\$cell", $this->formatter[$colIndex]['format']) . ";";
				}
			}
		}

		//The CssArray to be applied to each cell
		$cssClass = $columnStructure;
		if ($this->formatter !== false) {
			foreach($cssClass as $colIndex => $cell) {
				if (array_key_exists('css_class', $this->formatter[$colIndex])) {
					$givenCssClass = trim($this->formatter[$colIndex]['css_class']);
					if (preg_match("/^\w+$/", $givenCssClass)) {
						$cssClass[$colIndex] = " class=\"$givenCssClass\"";
					} else {
						throw new Exception("Invalid css given [$givenCssClass] for column: [$colIndex]");
					}
				}
			}
		}

		//The hyperlink to be applied to each cell
		$link = $columnStructure;
		if ($this->formatter !== false) {
			foreach($link as $colIndex => $cell) {
				if (array_key_exists('link', $this->formatter[$colIndex])) {
					$link[$colIndex] = trim($this->formatter[$colIndex]['link']);
				}
			}
		}

		// Decides if there will be a graph and computes the min and max values for each column
		$graph = false;
		if ($this->formatter !== false) {
			foreach($this->formatter as $colIndex => $cell) {
				if (array_key_exists('graph', $this->formatter[$colIndex])) {
					$graph = $columnStructure; //Creates an empty array where the footer results will be inserted
					break;
				}
			}
		}
		if ($graph !== false) {
			//Is there a graph definition for at least one column?
			foreach($graph as $colIndex => $cell) {
				if (array_key_exists('graph', $this->formatter[$colIndex])) {
					$graphInstructionByUser = $this->formatter[$colIndex]['graph'];
					// computes min
					$min = $this->data[0][$colIndex];
					if (array_key_exists('min', $graphInstructionByUser)) {
						$givenMin = $graphInstructionByUser['min'];
						if (preg_match("/^-?\d+(\.\d+)?$/", $givenMin)) {
							$min = $givenMin;
						} else {
							throw new Exception("Invalid min given [$givenMin] for column: [$colIndex]");
						}
					} else {
						foreach($this->data as $rowIndex => $column) {
							if ($this->data[$rowIndex][$colIndex] < $min) {
								$min = $this->data[$rowIndex][$colIndex];
							}
						}
					}
					// computes max
					$max = $this->data[0][$colIndex]; //max possible value
					if (array_key_exists('max', $graphInstructionByUser)) {
						$givenMax = $graphInstructionByUser['max'];
						if (preg_match("/^-?\d+(\.\d+)?$/", $givenMax)) {
							$max = $givenMax;
						} else {
							throw new Exception("Invalid max given [$givenMax] on formatter for column: [$colIndex]");
						}
					} else {
						foreach($this->data as $rowIndex => $column) {
							if ($this->data[$rowIndex][$colIndex] > $max) {
								$max = $this->data[$rowIndex][$colIndex];
							}
						}
					}
					// finds out the column width, if none was given then forces default "50"
					$width = 50;
					if ($this->formatter !== false) {
						//Is there a formatter declared?
						if (array_key_exists('width', $this->formatter[$colIndex])) {
							$givenWidth = trim($this->formatter[$colIndex]['width']);
							if (preg_match("/^\d{1,3}$/", $givenWidth)) {
								$width = (int) $givenWidth;
							} else {
								throw new Exception("Invalid width given [$givenWidth] on formatter for column: [$colIndex]");
							}
						} else {
							//theres no width given by the user, so forces the creation of a new one
							$this->formatter[$colIndex]['width'] = (string) $width;
						}
					}
					//Once min, max and width has been calculated, determine the scale.
					$scale = 0;
					if (($max - $min) > 0) {
						$scale = $width  / ($max - $min);
					}
					// stores min, max, width and scale in a array to be used ahead on renderization
					$graph[$colIndex]['max'] = $max;
					$graph[$colIndex]['min'] = $min;
					$graph[$colIndex]['scale'] = $scale;
					$graph[$colIndex]['width'] = $width;

					//If there is no cssClass associated with the "graficable" column, forces default
					if ($cssClass[$colIndex] === false) {
						$cssClass[$colIndex] = " class=\"cellgraph_$width\"";
					}
				}
			}
		}

		/***************************************************************************
		 Create arrays containing header and (maybe) the footer values
		****************************************************************************/

		//Creates the first row (header)
		$firstRow = $columnStructure;
		if ($this->formatter !== false) {
			//Is there a formatter declared?
			foreach($this->data[0] as $colIndex => $cell) {
				$firstRow[$colIndex] = array_key_exists('header', $this->formatter[$colIndex]) ? $this->formatter[$colIndex]['header'] : $colIndex;
			}
		} else {
			foreach($this->data[0] as $colIndex => $cell) {
				$firstRow[$colIndex] = $colIndex;
			}
		}

		//Creates the last row (footer)
		$lastRow = false;
		if ($this->formatter !== false) {
			foreach($this->formatter as $colIndex => $cell) {
				if (array_key_exists('footer', $this->formatter[$colIndex])) {
					$lastRow = $columnStructure; //Creates an empty array where the footer results will be inserted
					break;
				}
			}
		}
		if ($lastRow !== false) {
			//there will be an last row?
			//fill the last line with data
			for ($pass = 0; $pass < 2; $pass++) {
				//2-phase calculus
				foreach($lastRow as $colIndex => $cell) {
					$footerInstruction = false;
					if (array_key_exists('footer', $this->formatter[$colIndex])) {
						$footerInstruction = trim($this->formatter[$colIndex]['footer']);
					}
					if ($footerInstruction !== false) {
						//array tem alguma coisa, processar
						if (preg_match("/^(sum|avg)$/", $footerInstruction)) {
							//sum
							if ($lastRow[$colIndex] === false ) {
								//Footer is empty yet?
								$math = 0.0;
								for ($i = 0; $i < $this->rows; $i++) {
									$math += $this->data[$i][$colIndex];
								}
								if ($footerInstruction == "sum") {
									$lastRow[$colIndex] = $math;
								} else { //avg
									$lastRow[$colIndex] = $math / $this->rows;
								}
							}
						} else if (preg_match("/^[a-z]\w+$/i", $footerInstruction)) {
							//header
							if ($lastRow[$colIndex] === false) {
								$lastRow[$colIndex] = $footerInstruction;
							}
						} else if (preg_match("/^\(* *'\w+'.*$/", $footerInstruction)) {
							//expression
							if ($pass > 0) {
								//only works after others be computed
								$instruction = "return " . preg_replace("/('\w+')/i", "\$lastRow[$1]", $footerInstruction) . ";";
								$instruction = preg_replace("/'(\d+)'/", "$1", $instruction);
								$lastRow[$colIndex] = eval($instruction);
							}
						}
					}
				}
			}
		}


		/***************************************************************************
		 Starts the table renderization
		****************************************************************************/
		// Table
		$complementStr = empty($this->complement) ? '' : ' ' . $this->complement;
		$rtn = "<table$complementStr>\n";

		// Header.
		$rtn .= "<thead>\n";
		$rowCurrent = "<tr> ";
		foreach($firstRow as $colIndex => $cell) {
			//first array row
			$th = "th";
			if ($this->formatter !== false) {
				if ($this->formatter[$colIndex] !== false) {
					if (array_key_exists('width', $this->formatter[$colIndex])) {
						$givenWidth = trim($this->formatter[$colIndex]['width']);
						if (preg_match("/^\d{1,3}$/", $givenWidth)) {
							$th = "th style=\"width:" . $givenWidth . "px;\"";
						} else {
							throw new Exception("Invalid width given [$givenWidth] for column: [$cell]");
						}
					}
				}
			}
			$rowCurrent .= "<$th>$cell</th> ";
		}
		$rowCurrent .= "</tr>\n";
		$rtn .= $rowCurrent;
		$rtn .= "</thead>\n";

		// Contents
		$rtn .= "<tbody>\n";
		foreach($this->data as $rowIndex => $column) {
			$rowCurrent = "<tr> ";
			foreach($column as $colIndex => $cell) {
				//Is there a formatter?
				if ($format[$colIndex] !== false) {
					$cellFormatted = eval($format[$colIndex]);
				} else {
					$cellFormatted = $cell;
				}

				//This cell should be turned into a hyperlink?
				if ($link[$colIndex] !== false) {
					$hyperlink = "<a href=\"";
					$url = explode("?", $link[$colIndex]);
					$hyperlink .= $url[0] . "?";
					$parameters = explode("&", $url[1]);
					foreach($parameters as $paramIndex => $parameter) {
						$content = explode("=", $parameter);
						if (preg_match("/column\[\d+\]/i", $content[1])) {
							$value = urlencode(eval("return \$" . $content[1] . ";")); //evalues de column given by user
						} else {
							$value = urlencode($content[1]); // live like that..
						}

						if ($paramIndex == 0) {
							$hyperlink .= $content[0] . "=" . $value;
						} else {
							$hyperlink .= "&amp;" . $content[0] . "=" . $value;
						}
					}
					$hyperlink .= "\">" . $cellFormatted . "</a>";
					$cellFormatted = $hyperlink;
				}

				//Is there a graph for this column?
				$td = "td" . $cssClass[$colIndex];
				if ($graph !== false) {
					if ($graph[$colIndex] !== false) {
						$barSize = (($cell - $graph[$colIndex]['min']) * $graph[$colIndex]['scale']) - $graph[$colIndex]['width'];
						$td .= " style=\"background-position:" . floor($barSize) . "px;\"";
					}
				}
				$rowCurrent .= "<$td>$cellFormatted</td> ";
			}
			$rowCurrent .= "</tr>\n";
			$rtn .= $rowCurrent;
		}

		// Footer
		if ($lastRow !== false) {
			//footer shall be rendered?
			$rowCurrent = "<tr> ";
			foreach($lastRow as $colIndex => $cell) {
				if ($format[$colIndex] !== false) {
					$cell = eval($format[$colIndex]);
				}
				$rowCurrent .= "<th>$cell</th> ";
			}
			$rowCurrent .= "</tr>\n";
			$rtn .= $rowCurrent;
		}
		$rtn .= "</tbody>\n";

		//closes the table
		$rtn .= "</table>";
		return $rtn;
	}


	/**
	 * getters and setters
	 */


	public function getColumns() {
		return $this->columns;
	}

	public function setColumns($columns) {
		$this->columns = $columns;
	}

	public function getData() {
		return $this->data;
	}

	public function setData($data) {
		if (gettype($data) != 'array') {
			throw new Exception('First parameter must be an array type');
		}
		//load the rows and columns attributes
		$this->setRows(sizeof($data));
		if ($this->getRows() > 0) {
			$this->setColumns(sizeof($data[0]));
		}
		//sets the attribute
		$this->data = $data;
	}

	public function getFormatter() {
		return $this->formatter;
	}

	public function setFormatter($formatter) {
		if (gettype($formatter) != 'array') {
			throw new Exception('Third parameter (formatter) must be an array type');
		} else {
			if ($this->getRows() > 0) {
				if (sizeof($formatter) != $this->getColumns()) {
					throw new Exception('The formatters size and data array columns must be the same. Formatter has ['
					. sizeof($formatter) . '] itens and should have [' . $this->getColumns() . '] itens');
				} else {
					//apply array-column keys to the formatter
					$this->formatter = array_combine(array_keys($this->data[0]), $formatter);
				}
			}
		}
	}

	public function getComplement() {
		return $this->complement;
	}

	public function setComplement($complement) {
		$this->complement = $complement;
	}

	public function getRows() {
		return $this->rows;
	}

	public function setRows($rows) {
		$this->rows = $rows;
	}

}
