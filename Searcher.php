<?php
error_reporting(E_ERROR);
require_once 'Str.php';

// TODO Priprava array-a za variacije
// TODO Bombončki (balkon, ...)
// TODO Caching? (ni nujno)

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
                'region'       => [],
                'city'         => [
                    'additional' => [ 'region' => 'region' ]
                ],
                'district'     => [
                    'additional' => [ 'region' => 'region', 'city' => 'city' ]
                ],
                'criteria_opt' => [
                    'additional' => [ 'property_type' => 'parent' ],
                    'breakable'  => false,
                ]
            ]
        ],
        'query'           => [
            'prefix' => 'item',
            'tables' => [
                '',
                'luxury',
                'photo'
            ]
        ],
        'logging'         => [
            'enabled' => false,
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
        $this->configureDatabaseConnection()->configureAgencySql()->search($search)->log($search);
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
        $this->parseString($string)->buildSql()->buildResults();

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
            'query'   => $query,
            'results' => count($this->results['items'])
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
        $string = $this->specialCases($string);

        $string = $this->check($string, 'price');
        $string = $this->check($string, 'area');

        $parts = array_flip(explode(" ", $this->removeSpecialWords($string)));
        foreach ( $this->config['search']['tables'] as $table => $properties ) {
            $table_name = $this->config['search']['prefix'] . $table;
            $params     = [];
            foreach ( $parts as $part => $_ ) {
                if ( isset( $properties['additional'] ) ) {
                    foreach ( $properties['additional'] as $parent => $column ) {
                        if ( isset( $this->translated[$parent] ) ) {
                            $params[$column] = $this->translated[$parent]['search'];
                        }
                    }
                }
                $result = $this->getWordIdentifierReplacement($table_name, $this->searchify($part), $params);
                $column =
                    ( strlen($result['column']) == 0 ) ? ( !isset( $properties['column'] ) ) ? $table : $properties['column'] : $result['column'];
                if ( !empty( $result['id'] ) ) {
                    $type = ( isset( $properties['type'] ) ) ? $properties['type'] : "=";
                    $this->insertIntoTranslated($column, $part, $result['id'], $type);
                    unset( $parts[$part] );
                    if ( !isset( $properties['breakable'] ) || $properties['breakable'] == true ) {
                        break;
                    }
                }
            }
        }

        foreach ( $parts as $part => $_ ) {
            $word = $this->searchify($part);
            if ( strlen($word) > 0 ) {
                if ( is_null($this->selectOne("SELECT id FROM cr_keywords WHERE text = :text", [ 'text' => $part ])) ) {
                    $this->insert("cr_keywords", [ 'text' => $word ]);
                }
            }
            unset( $parts[$part] );
        }

        return $this;
    }

    /**
     * Generates the SQL, only WHERE part which will be appended to the SELECT statement which will return matching ids.
     *
     * @return $this
     */
    private function buildSql()
    {
        $wheres = [];
        foreach ( $this->translated as $column => $properties ) {
            switch ( $properties['type'] ) {
                case "=":
                case ">":
                case "<":
                case "LIKE":
                    $wheres[] = "{$column} {$properties['type']} {$properties['search']}";
                    break;
                case "BETWEEN":
                    $wheres[] =
                        "{$column} {$properties['type']} {$properties['search'][0]} AND {$properties['search'][1]}";
                    break;
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
        foreach ( $this->translated as $column => $properties ) {
            $this->results['meta'][$column]['text'] = $properties['identifier'];
            $this->results['meta'][$column]['id']   = $properties['search'];
            if ( $column == "price" || $column == "size_bruto" ) {
                $this->results['meta'][$column]['type'] = $properties['type'];
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
            $minPattern     = '/(od|min|nad) ?(\d+) ?' . $pattern . '/';
            $maxPattern     = '/(do|pod|max|,| ) ?(\d+) ?' . $pattern . '/';
            $betweenPattern = '/(\d+) ?' . $pattern . '? ?(do|,|in|-) ?(\d+) ?' . $pattern . '/';
            $column         = ( isset( $this->config[$type]['column'] ) ) ? $this->config[$type]['column'] : $type;
            if ( preg_match($minPattern, $text, $matches) ) {
                $this->insertIntoTranslated($column, $matches[0], $matches[2], ">");
            } else {
                if ( preg_match($betweenPattern, $text, $matches) ) {
                    $this->insertIntoTranslated($column, $matches[0], [ $matches[1], $matches[4] ], 'BETWEEN');
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
     * @param string $type          Type of operation, currently supported " = " and "BETWEEN"
     */
    private function insertIntoTranslated($column, $identifier, $search, $type = " = ")
    {
        $this->translated[$column] = [
            'identifier' => $identifier,
            'type'       => $type,
            'search'     => $search
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
     * This function handle's all the "special breadcrumbs" like flat type
     *
     * @param $string   Original string
     *
     * @return mixed    Beautified string to work on in future
     */
    private function specialCases($string)
    {
        $string = $this->caseSpaces($string);
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
        $string = preg_replace('/(<? )(sob)/', '-$2', $string); // 2.5 sobno --> 2.5-sobno
        $string = preg_replace('/(<?\d)(sob)/', '$1-$2', $string); // 2.5sobno --> 2.5-sobno
        $string = preg_replace('/\.(\d-)/', ',$1', $string);

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
     * @param bool $items
     *
     * @return array
     */
    public function getResults($items = false)
    {
        if ( !$items ) {
            return $this->results;
        }

        $this->queryResults();

        return $this->results;
    }
}