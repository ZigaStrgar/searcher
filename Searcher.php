<?php
error_reporting(E_ERROR);
require_once 'Str.php';

class Searcher extends Str
{
    /**
     * It stores the PDO instance with setup database connection.
     *
     * @var PDO
     */
    protected $db;

    /**
     * Configuration array, including database connection, agency and "special breadcrumbs".
     *
     * @var array
     */
    private $config = [
        'database'        => [
            'DB_CONN'    => 'mysql',
            'DB_HOST'    => 'localhost',
            'DB_PORT'    => '3306',
            'DB_USER'    => 'root',
            'DB_PASS'    => '',
            'DB_NAME'    => 'searcher',
            'DB_CHARSET' => 'UTF8',
        ],
        'mode'            => 'loose', // strict, loose
        'mode_percentage' => 80,
        'agency'          => null, // Null --> Vse agencije
        'search'          => [
            'prefix' => 'cr_',
            'tables' => [
                'criteria_opt' => [
                    'breakable' => false,
                    'multiple'  => 'OR'
                ],
                'region'       => [],
                'city'         => [
                    'additional' => [ 'region' => 'region' ]
                ],
                'district'     => [
                    'additional' => [ 'region' => 'region', 'city' => 'city' ]
                ],
                'zip_postal'   => [],
            ]
        ],
        'query'           => [
            'tables' => [
                'items' => [
                    'region',
                    'city',
                    'district',
                    'zip_postal',
                    'size_bruto',
                    'price',
                    'status',
                    'offer_type',
                    'property_type',
                    'property_subtype',
                ],
                'additional',
                'luxury',
                'connector',
                'equipment',
                'heating',
                'suitability'
            ]
        ],
        'logging'         => [
            'enabled' => true,
            'table'   => 'searcher_logs'
        ],
        'price'           => [
            'regex'      => '(eu|vr|e|€)',
            'validation' => [ 'e', 'evr', 'eur', '€' ],
            'column'     => 'price'
        ],
        'area'            => [
            'regex'      => '(kvadrat|m\^?2)',
            'validation' => [ 'kvadrat', 'm2', 'm^2' ],
            'column'     => 'size_bruto'
        ]
    ];

    /**
     * Variation array covering edge cases from user input
     *
     * @var array
     */
    private $variations = [];

    /**
     * This must be included after every SQL query in searching for results. It limits the results by agency ID.
     *
     * @var string
     */
    protected $agencySql = "";

    /**
     * Array containing results of parsing the string.
     *
     * @var array
     */
    protected $translated = [];

    /**
     * Array containing results after the execution of SELECT
     *
     * @var array
     */
    protected $results = [];

    /**
     * This is the variable where the WHERE part of the SELECT statement which is used for obtaining results.
     *
     * @var string
     */
    protected $sql = "";

    /**
     * Searcher constructor.
     *
     * @param       $search
     * @param array $configuration
     */
    public function __construct($search, $configuration = [])
    {
        $this->config = array_merge($this->config, $configuration);
        $this->configureDatabaseConnection()->configureAgencySql()->fillVariations()->search($search)->log($search);
    }

    /**
     * Configuration of database connection and storing into $db a new PDO instance.
     *
     * @return $this
     */
    private function configureDatabaseConnection()
    {
        $config = $this->config['database'];

        $dsn =
            "{$config['DB_CONN']}:dbname={$config['DB_NAME']};host={$config['DB_HOST']};port={$config['DB_PORT']};charset={$config['DB_CHARSET']}";

        $this->db = new PDO($dsn, $config['DB_USER'], $config['DB_PASS']);
        $this->db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

        return $this;
    }

    /**
     * Builds the mandatory WHERE part for limiting results by agency ID
     *
     * @return $this
     */
    private function configureAgencySql()
    {
        if ( is_null($this->config['agency']) ) {
            return $this;
        } else if ( is_string($this->config['agency']) || is_numeric($this->config['agency']) ) {
            $this->agencySql = " AND agency = " . $this->config['agency'];

            return $this;
        } else if ( is_array($this->config['agency']) ) {
            if ( count($this->config['agency']) == 1 ) {
                $this->agencySql = " AND agency = " . $this->config['agency'][0];
            } else {
                $this->agencySql = " AND agency IN (" . implode(", ", $this->config['agency']) . ")";
            }
        }

        return $this;
    }

    /**
     * The main function which is called after all the configuration and is responsible for calling the parsing
     * function and all functions that builds the result of the search term passed in as a first argument.
     *
     * @param $string Searching term
     *
     * @return $this
     */
    private function search($string)
    {
        $this->parseString($string)->checkTree()->buildSql()->buildResults();

        return $this;
    }

    /**
     * Log's the executed query for further researches
     *
     * @param $query
     *
     * @return $this
     */
    private function log($query)
    {
        if ( !$this->config['logging']['enabled'] ) {
            return $this;
        }

        $this->insert($this->config['logging']['table'], [
            'query'     => $query,
            'results'   => count($this->results['items']),
            'client_ip' => $_SERVER['REMOTE_ADDR'],
            'page'      => $_SERVER['REQUEST_URI'],
            'agency_id' => ( is_numeric($this->config['agency']) ) ? (int)$this->config['agency'] : (int)$this->config['agency'][0]
        ]);

        return $this;
    }

    /**
     * This function is the "core" of this script. It breaks the string to corresponding parts and then try's to find
     * the parts identifier inside the database.
     *
     * @param $string Searching term
     *
     * @return $this
     */
    private function parseString($string)
    {
        $words = $this->setupWords($string);

        $search = $this->config['search'];
        foreach ( $search['tables'] as $table => $properties ) {
            $table_name = $search['prefix'] . $table;
            foreach ( $words as $word => $_ ) {
                $params = $this->prepareParentalData($properties);
                $result = $this->getWordIdentifierReplacement($table_name, $word, $params);
                if ( !empty( $result['id'] ) ) {
                    list( $column, $type, $operation ) = $this->prepareConfigData($properties, $result, $table);
                    $this->insertIntoTranslated($column, $word, $result['id'], $type, $operation);
                    unset( $words[$word] );
                    if ( !isset( $properties['breakable'] ) || $properties['breakable'] == true ) {
                        break;
                    }
                }
            }
        }

        $this->leftOvers($words);

        return $this;
    }

    /**
     * Process the words left unmatched in cr_search.
     *
     * @param $words
     *
     * @return $this
     */
    private function leftOvers($words)
    {
        $search = $this->config['search'];
        foreach ( $words as $word => $_ ) {
            if ( strlen($word) > 0 ) {
                $result = $this->selectOne("SELECT * FROM cr_keywords WHERE text = :text OR text = :quoted", [
                    'text'   => $word,
                    'quoted' => '"' . $word . '"'
                ]);
                if ( is_null($result) ) {
                    $this->insert("cr_keywords", [ 'text' => $word ]);
                } else {
                    $result =
                        $this->selectOne("SELECT s.text, s.column, s.cr_id, s.cr_table FROM cr_search s WHERE s.cr_id = :id AND s.cr_table = :table", [
                            'id'    => $result['cr_id'],
                            'table' => $result['cr_table']
                        ]);
                    if ( !empty( $result['text'] ) ) {
                        $table_name = str_replace($search['prefix'], "", $result['cr_table']);
                        list( $column, $type, $operation ) =
                            $this->prepareConfigData($search['tables'][$table_name], $result, $table_name);
                        $this->insertIntoTranslated($column, $result['text'], $result['cr_id'], $type, $operation);
                    }
                }
            }
            unset( $words[$word] );
        }

        return $this;
    }

    /**
     * Just bunch of things to do before actual search
     *
     * @param $string
     *
     * @return array
     */
    private function setupWords($string)
    {
        $string = $this->lower($string);

        $string = $this->check($string, 'price');
        $string = $this->check($string, 'area');

        $string = $this->specialCases($string);

        list( $literals, $string ) = $this->literals($string);

        $string = $this->removeSpecialWords($string);
        $string = $this->searchify($string);

        $words = array_flip(explode(" ", $string));

        foreach ( $literals as $literal ) {
            $words[$literal] = 0;
        }

        return $words;
    }

    /**
     * Prepares column, type and operation for correct binding and building
     *
     * @param $properties
     * @param $result
     * @param $table_name
     *
     * @return array
     */
    private function prepareConfigData($properties, $result, $table_name)
    {
        $column    = $this->prepareColumnName($properties, $result, $table_name);
        $type      = $this->prepareType($properties);
        $operation = $this->prepareOperation($properties);

        return [ $column, $type, $operation ];
    }

    /**
     * Prepares a column name from given information
     *
     * @param $properties
     * @param $result
     * @param $table_name
     *
     * @return mixed
     */
    private function prepareColumnName($properties, $result, $table_name)
    {
        return ( strlen($result['column']) == 0 ) ? ( !isset( $properties['column'] ) ) ? $table_name : $properties['column'] : $result['column'];
    }

    /**
     * Prepares a type from given information
     *
     * @param $properties
     *
     * @return string
     */
    private function prepareType($properties)
    {
        return ( isset( $properties['type'] ) ) ? $properties['type'] : "=";
    }

    /**
     * Prepares the operation type from given information
     *
     * @param $properties
     *
     * @return string
     */
    private function prepareOperation($properties)
    {
        return ( isset( $properties['multiple'] ) ) ? $properties['multiple'] : "AND";
    }

    /**
     * Prepares the parental parameters for more accurate location search
     *
     * @param $properties
     *
     * @return array
     */
    private function prepareParentalData($properties)
    {
        $params = [];

        if ( isset( $properties['additional'] ) ) {
            foreach ( $properties['additional'] as $parent_table => $table_column ) {
                if ( isset( $this->translated[$parent_table] ) ) {
                    $params[$table_column] = $this->translated[$parent_table][0]['search'];
                }
            }
        }

        return $params;
    }

    /**
     * Generates the SQL, only WHERE part which will be appended to the SELECT statement which will return matching ids.
     *
     * @return $this
     */
    private function buildSql()
    {
        $wheres = [];

        foreach ( $this->config['query']['tables'] as $table => $columns ) {
            if ( is_numeric($table) ) {
                $table  = "item_" . $columns;
                $column = "id_" . $columns;
                if ( isset( $this->translated[$columns] ) ) {
                    foreach ( $this->translated[$columns] as $item ) {
                        $wheres[] = "id IN (SELECT id_item FROM $table WHERE $column = {$item['search']})";
                    }
                }
            } else {
                foreach ( $columns as $column ) {
                    if ( isset( $this->translated[$column] ) ) {
                        $innerWhere = [];
                        foreach ( $this->translated[$column] as $item ) {
                            switch ( $item['type'] ) {
                                case "=":
                                case ">":
                                case "<":
                                case "LIKE":
                                    $innerWhere[] = "{$column} {$item['type']} {$item['search']}";
                                    break;
                                case "BETWEEN":
                                    $innerWhere[] =
                                        "{$column} {$item['type']} {$item['search'][0]} AND {$item['search'][1]}";
                                    break;
                            }
                            $glue = ( isset( $item['multiple'] ) ) ? $item['multiple'] : "AND";
                        }
                        $wheres[] = "(" . implode(" {$glue} ", $innerWhere) . ")";
                    }
                }
            }
        }

        $query = implode(" AND ", $wheres);

        $this->sql = "({$query})";

        return $this;
    }

    /**
     * Executes the query and get's the proper results for given search string
     *
     * @return $this
     */
    private function queryResults()
    {
        if ( count($this->translated) == 0 ) {
            return $this;
        }
        $query                  = "SELECT id FROM item WHERE {$this->sql} {$this->agencySql}";
        $this->results['items'] = array_map(function($item) { return $item['id']; }, $this->select($query));

        return $this;
    }

    /**
     * Builds an array with correct meta data and results which can be return to the main program.
     *
     * @return $this
     */
    private function buildResults()
    {
        foreach ( $this->translated as $column => $values ) {
            foreach ( $values as $properties ) {
                if ( count($values) > 1 ) {
                    $this->results['meta'][$column][] =
                        [ 'text' => $properties['identifier'], 'id' => $properties['search'] ];
                } else {
                    $this->results['meta'][$column]['text'] = $properties['identifier'];
                    $this->results['meta'][$column]['id']   = $properties['search'];
                }
                if ( $column == "price" || $column == "size_bruto" ) {
                    $this->results['meta'][$column]['type'] = $properties['type'];
                }
            }
        }

        return $this;
    }

    /**
     * Builds and query which looks into Code register tables and find a matching ID for a given word and returns the
     * ID.
     *
     * @param       $table        Table name where to look
     * @param       $part         Part of the word which is being looked upon
     * @param array $additional   Additional parameters
     *
     * @return mixed
     */
    private function getWordIdentifierReplacement($table, $part, $additional = [])
    {
        if ( strlen($part) > 0 ) {
            $additionalSql = implode(" AND ", array_map(function($key) {
                return "$key = :$key";
            }, array_keys($additional)));

            $additionalSql = ( strlen($additionalSql) > 0 ) ? " AND " . $additionalSql : "";

            $word = $this->modifyWord($part);

            $query  =
                "SELECT cr_id as id, `column` FROM cr_search WHERE search LIKE :word AND cr_table = :table {$additionalSql} ORDER BY CHAR_LENGTH(search) ASC LIMIT 1";
            $result = $this->selectOne($query, array_merge([
                'word'  => "$word",
                'table' => $table
            ], $additional));

            return $result;
        }
    }

    /**
     * Calls the selectAll method and then returns only the first one
     *
     * @param       $sql    SQL Statement with placeholders for parameters
     * @param array $params Parameters which must be bind to the statement
     *
     * @return mixed        Only First record
     */
    private function selectOne($sql, $params = [])
    {
        return $this->select($sql, $params)[0];
    }

    /**
     * The function which binds the parameters of the query to the actual query end after that executes the result
     *
     * @param       $sql    SQL Statement with placeholders for parameters
     * @param array $params Parameters which must be bind to the statement
     *
     * @return array        All results
     */
    private function select($sql, $params = [])
    {
        $statement = $this->db->prepare($sql);

        foreach ( $params as $key => $value ) {
            $statement->bindValue(":$key", $value);
        }

        $statement->execute();

        return $statement->fetchAll();
    }

    /**
     * Insert's a record into a database
     *
     * @param       $table
     * @param array $params
     */
    private function insert($table, $params = [])
    {
        $fieldNames  = implode("`, `", array_keys($params));
        $fieldValues = ":" . implode(", :", array_keys($params));
        $statement   = $this->db->prepare("INSERT INTO $table (`$fieldNames`) VALUES ($fieldValues);");

        foreach ( $params as $key => $val ) {
            $statement->bindValue(":$key", $val);
        }

        $statement->execute();
    }

    /**
     * Check's the tree for property type and location information
     *
     * @return $this
     */
    private function checkTree()
    {
        foreach ( $this->config['search']['tables'] as $table => $properties ) {
            $column = ( isset( $properties['column'] ) ) ? $properties['column'] : $table;
            if ( isset( $this->translated[$column] ) && isset( $properties['additional'] ) ) {
                foreach ( $properties['additional'] as $translated => $search ) {
                    if ( !isset( $this->translated[$translated] ) ) {
                        $result = $this->selectOne("SELECT * FROM cr_search WHERE cr_table = :table AND cr_id = :id", [
                            'table' => $this->config['search']['prefix'] . $table,
                            'id'    => $this->translated[$column][0]['search']
                        ]);
                        if ( !is_null($result) ) {
                            $table            = $translated;
                            $result           =
                                $this->selectOne("SELECT * FROM cr_search WHERE cr_table = :table AND cr_id = :id", [
                                    'table' => $this->config['search']['prefix'] . $table,
                                    'id'    => $result[$search]
                                ]);
                            $translatedColumn = ( strlen($result['column']) > 0 ) ? $result['column'] : $translated;
                            $this->insertIntoTranslated($translatedColumn, $result['text'], $result['cr_id']);
                        }
                    }
                }
            }
        }

        return $this;
    }

    /**
     * Checks for matches in area and price. Very extensive to any other feature.
     *
     * @param $text Text where to search for
     * @param $type Type what to look for
     *
     * @return mixed|string
     */
    private function check($text, $type)
    {
        $pattern = $this->config[$type]['regex'];
        if ( preg_match('/' . $pattern . '/', $text) ) {
            $minPattern     = '/(od|min|nad|vsaj|najmanj) ?(\d+(,|\.)?\d+) ?' . $pattern . '/';
            $maxPattern     = '/(do|pod|max|najvec|,| ) ?(\d+(,|\.)?\d+) ?' . $pattern . '/';
            $betweenPattern = '/(\d+(,|\.)?\d+) ?' . $pattern . '? ?(do|,|in|-) ?(\d+(,|\.)?\d+) ?' . $pattern . '/';
            $column         = ( isset( $this->config[$type]['column'] ) ) ? $this->config[$type]['column'] : $type;
            if ( preg_match($betweenPattern, $text, $matches) ) {
                $this->insertIntoTranslated($column, $matches[0], [ $matches[1], $matches[5] ], 'BETWEEN');
            } else {
                if ( preg_match($minPattern, $text, $matches) ) {
                    $this->insertIntoTranslated($column, $matches[0], $matches[2], ">");
                } else {
                    if ( preg_match($maxPattern, $text, $matches) ) {
                        $this->insertIntoTranslated($column, $matches[0], $matches[2], '<');
                    }
                }
            }

            return str_replace($matches[0], "", $text);
        }

        return $text;
    }

    /**
     * Function responsible to insert into $translated variable for future usage.
     *
     * @param        $column        Column which represents the "what column to look for in specific table"
     * @param        $identifier    Original matching text
     * @param        $search        ID which is representing the Original text.
     * @param string $type          Type of operation, currently supported "=", "<", ">" and "BETWEEN"
     * @param string $operation     Type of operation for
     */
    private function insertIntoTranslated($column, $identifier, $search, $type = "=", $operation = 'AND')
    {
        $this->translated[$column][] = [
            'identifier' => $identifier,
            'type'       => $type,
            'search'     => $search,
            'multiple'   => $operation
        ];
    }

    /**
     * Process the search term and removes any unwanted characters and trims it down for better "security"
     *
     * @param $string
     *
     * @return mixed|string
     */
    private function searchify($string)
    {
        return $this->lower(self::ascii($string));
    }

    /**
     * Modify's word by it's length and program mode
     *
     * @param $word
     *
     * @return string
     */
    private function modifyWord($word)
    {
        if ( strlen($word) <= 5 ) {
            return $word;
        } else {
            switch ( $this->config['mode'] ) {
                case 'strict':
                    return $word;
                    break;
                case 'loose':
                    return "%" . substr($word, 0, floor(strlen($word) * $this->config['mode_percentage'] / 100)) . "%";
                    break;
            }
        }
    }

    /**
     * Removes all the words that are shorter than 3 characters
     *
     * @param $string
     *
     * @return string
     */
    private function removeSpecialWords($string)
    {
        $string = $this->caseSpaces($string);
        $words  = array_map(function($word) {
            return ( strlen($word) <= 2 ) ? null : $word;
        }, explode(" ", $string));

        return trim(implode(" ", $words));
    }

    /**
     * Find's occurrence of literal strings in between " or ', replaces them with empty string and return them as one
     * word
     *
     * @param $string
     *
     * @return array
     */
    private function literals($string)
    {
        preg_match_all('/(\'|")([a-z0-9 \-]*)(\'|")/', $string, $matches);

        foreach ( $matches[0] as $match ) {
            $string = str_replace($match, "", $string);
        }

        return [ $matches[2], $string ];
    }

    /**
     * Fill's the variations form keywords table
     *
     * @return $this
     */
    private function fillVariations()
    {
        $results = $this->select("SELECT * FROM cr_keywords WHERE text LIKE '\"%\"'");

        foreach ( $results as $result ) {
            $this->variations[$result['text']] = str_replace('"', '', $result['text']);
        }

        return $this;
    }

    /**
     * This function handle's all the "special breadcrumbs" like flat type
     *
     * @param $string   Original string
     *
     * @return mixed    Beautified string to work on in future
     */
    private function specialCases($string)
    {
        $string = $this->caseSpaces($string);
        $string = $this->caseVariations($string);
        $string = $this->caseFlats($string);

        return $string;
    }

    /**
     * Removes all multiple spaces
     *
     * @param $string
     *
     * @return mixed
     */
    private function caseSpaces($string)
    {
        return preg_replace('/\s/', " ", $string);
    }

    /**
     * Function dedicated to look for flat types and translate them to the correct form
     *
     * @param $string   Original string
     *
     * @return mixed    Beautified string to work on in future
     */
    private function caseFlats($string)
    {
        $string = preg_replace('/(<?\d)(<? )(sob)/', '$1-$3', $string); // 2.5 sobno --> 2.5-sobno
        $string = preg_replace('/(<?\d)(sob)/', '$1-$2', $string); // 2.5sobno --> 2.5-sobno
        $string = preg_replace('/\.(\d-)/', ',$1', $string);

        return $string;
    }

    /**
     * Replaces special variations with database string
     *
     * @param $string
     *
     * @return mixed
     */
    private function caseVariations($string)
    {
        $string = $this->searchify($string);

        foreach ( $this->variations as $key => $val ) {
            $string = str_replace($val, $key, $string);
        }

        return $string;
    }

    /**
     * @return string
     */
    public function getSql()
    {
        return $this->sql;
    }

    /**
     * @param bool $withItems
     *
     * @return array
     */
    public function getResults($withItems = false)
    {
        if ( !$withItems ) {
            return $this->results;
        }

        $this->queryResults();

        return $this->results;
    }
}