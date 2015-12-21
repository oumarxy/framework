<?php

namespace System\Database;

use PDO;
use StdClass;
use PDOStatement;
use PDOException;
use ErrorException;
use System\Support\Util;
use System\Support\Logger;
use System\Support\Security;
use InvalidArgumentException;
use System\Exception\ConnectionException;

class DB extends DbTools
{
    /**
     * Information sur les erreurs de pdoStement
     * 
     * @var array
     */
    private static $currentPdoStementErrorInfo = [];
    /**
     * Information sur les erreurs de pdo
     * 
     * @var array
     */
    private static $currentPdoErrorInfo = [];
    /**
     * Instance de DB
     *
     * @var null
     */
    private static $db = null;
    /**
     * Configuration
     *
     * @var array
     */
    private static $config;
    /***
     * Liste des constances d'execution de Requete SQL.
     * Pour le system de de base de donnee ultra minimalise de snoop.
     */
    const SELECT = 1;
    const UPDATE = 2;
    const DELETE = 3;
    const INSERT = 4;

    public static function loadConfiguration($config)
    {
        return static::$config = (object) $config;
    }

    /**
     * connection
     *
     * @param null $option
     * @param null $cb
     * @return null
     */
    public static function connection($option = null, $cb = null)
    {
        if (static::$db instanceof PDO) {
            return null;
        }
        if ($option !== null) {
            if (is_string($option)) {
                $zone = $option;
            } else {
                $zone = "default";
                $cb = $option;
            }
        } else {
            $zone = "default";
        }
        /**
         * Essaie de connection
         */
        $t = static::$config;

        if (! $t instanceof StdClass) {
            Util::launchCallBack($cb, [new ErrorException("Le fichier db.php est mal configurer")]);
        }

        $c = isset($t->connections[$zone]) ? $t->connections[$zone] : null;

        if (is_null($c)) {
            Util::launchCallBack($cb, [new ErrorException("La clé '$zone' n'est pas définir dans l'entre db.php")]);
        }

        $db = null;

        try {
            // Construction de l'objet PDO
            $dns = $c["scheme"] . ":host=" . $c['host'] . ($c['port'] !== '' ? ":" . $c['port'] : "") . ";dbname=". $c['dbname'];
            if ($c["scheme"] == "pgsql") {
                $dns = str_replace(";", " ", $dns);
            }
            // Connection à la base de donnée.
            static::$db = new PDO($dns, $c['user'], $c['pass'], [
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES UTF8",
                PDO::ATTR_DEFAULT_FETCH_MODE => $t->fetch
            ]);

        } catch (PDOException $e) {
            /**
             * Lancement d'exception
             */
            Util::launchCallBack($cb, [$e]);
        }

        Util::launchCallBack($cb, false);
        
        return static::class;

    }

	/**
	 * switchTo, permet de ce connecter a une autre base de donnee.
     *
	 * @param string $enterKey
	 * @param callable $cb
	 * @return void
	 */
	public static function switchTo($enterKey, $cb)
	{
        static::verifyConnection();

		if (!is_string($enterKey)) {
			Util::launchCallBack($cb, [new InvalidArgumentException("parametre invalide")]);
		} else {
			static::$db = null;
			static::connection($enterKey, $cb);
		}

	}

    /**
     * execute une requete update
     *
     * @param $sqlstatement
     * @param array $bind
     * @return bool
     */
    public static function update($sqlstatement, array $bind = [])
    {
        static::verifyConnection();

        if (preg_match("/^update\s[\w\d_`]+\s\bset\b\s.+\s\bwhere\b\s.+$/i", $sqlstatement)) {
             return static::executePrepareQuery($sqlstatement, $bind);
        }

        return false;
    }

    /**
     * execute une requete select
     *
     * @param $sqlstatement
     * @param array $bind
     * @return mixed|null
     */
    public static function select($sqlstatement, array $bind = [])
    {
        static::verifyConnection();
        if (preg_match("/^select\s[\w\d_()*`]+\sfrom\s[\w\d_`]+.+$/i", $sqlstatement)) {

            $pdostatement = static::$db->prepare($sqlstatement);
            static::bind($pdostatement, $bind);
            $fetch = "fetchAll";
            $pdostatement->execute();

            static::$currentPdoStementErrorInfo = $pdostatement->errorInfo();
            static::$currentPdoErrorInfo = static::$db->errorInfo();

            if ($pdostatement->rowCount() == 1) {
               $fetch = "fetch";
            }

            return Security::sanitaze($pdostatement->$fetch());
        }

        return null;
    }

    /**
     * execute une requete insert
     *
     * @param $sqlstatement
     * @param array $bind
     * @return null
     */
    public static function insert($sqlstatement, array $bind = [])
    {
        static::verifyConnection();

        if (preg_match("/^insert\sinto\s[\w\d_-`]+\s?(\(.+\)\svalues\(.+\)|\s?set\s(.+)+)$/i", $sqlstatement)) {
            $r = 0;
            if (count($bind) > 0) {

                $is_array = true;
               
                foreach ($bind as $key => $value) {
                    if (is_array($value)) {
                        $r += static::executePrepareQuery($sqlstatement, $value);
                    }
                }

            } else {
                static::executePrepareQuery($sqlstatement, $bind);
            }

            return $r;
        }

        return null;
    }

    /**
     * execute une requete de type DROP|CREATE TABLE|TRAUNCATE|ALTER TABLE
     *
     * @param $sqlstatement
     * @return bool
     */
    public static function statement($sqlstatement)
    {
        static::verifyConnection();

        if (preg_match("/^(drop|alter\stable|truncate|create\stable)\s.+$/i", $sqlstatement)) {
            $r = static::$db->exec($sqlstatement);
            static::$currentPdoErrorInfo = static::$db->errorInfo();
            return $r;
        }

        return false;
    }

    /**
     * execute une requete delete
     *
     * @param $sqlstatement
     * @param array $bind
     * @return bool
     */
    public static function delete($sqlstatement, array $bind = [])
    {
        static::verifyConnection();

        if (preg_match("/^delete\sfrom\s[\w\d_`]+\swhere\s.+$/i", $sqlstatement)) {
            return static::executePrepareQuery($sqlstatement, $bind);
        }

        return false;
    }

    /**
     * Charge le factory Table
     *
     * @param $tableName
     * @return mixed
     */
    public static function table($tableName)
    {
        static::verifyConnection();
        return Table::load($tableName, static::$db);
    }

    /**
     * Insertion des données dans la DB
     * ====================== USAGE ======================
     *	$options = [
     *		"query" => [
     *			"table" => "nomdelatable",
     *			"type" => INSERT|SELECT|DELETE|UPDATE,
     *			"data" => $data2pointAdded
     *		],
     *		"data" => "les données a insérer."
     *	];
     * 
     * @param array $options
     * @param bool|false $return
     * @param bool|false $lastInsertId
     * @throws \ErrorException
     * @return array|self|\StdClass
     */
    public static function query(array $options, $return = false, $lastInsertId = false)
    {
        static::verifyConnection();

        $sqlStatement = static::makeQuery($options["query"]);
        $pdoStatement = static::$db->prepare($sqlStatement);

        static::bind($pdoStatement, isset($options["data"]) ? $options["data"] : []);
        $pdoStatement->execute();

        if ($pdoStatement->execute()) {

            if ($pdoStatement->rowCount() === 0) {
                $data = null;
            } else if ($pdoStatement->rowCount() === 1) {
                $data = $pdoStatement->fetch();
            } else {
                $data = $pdoStatement->fetchAll();
            }

            if ($return == true) {
                if ($lastInsertId == false) {
                    return empty($data) ? null : Security::sanitaze($data);
                }
                return static::$db->lastInsertId();
            }

        } else {

            $debug = $pdoStatement->debugDumpParams();
            Logger::error(__METHOD__."(): Query fails, [SQL: {$debug}]");
        }
        return false;
    }

    /**
     * Lancement du debut d'un transaction
     *
     * @var void
     */
    public static function transaction()
    {
        static::verifyConnection();
        static::$db->beginTransaction();
    }

    /**
     * Valider une transaction
     * 
     * @var void
     */
    public static function commit()
    {
        static::verifyConnection();
        static::$db->commit();
    }

    /**
     * Annuler une transaction
     * 
     * @var void
     */
    public static function rollback()
    {
        static::verifyConnection();
        static::$db->rollBack();
    }

    /**
     * Lance la verification de l'etablissement de connection
     * 
     * @throws ConnectionException
     * @var void
     */
    private static function verifyConnection()
    {
        if (! (static::$db instanceof PDO)) {
            throw new ConnectionException("Connection non initialisé.", E_ERROR);
        }
    }
    /**
     * Récupère l'identifiant de la derniere enregistrement.
     * 
     * @return int
     */
    public static function lastInsertId()
    {
        static::verifyConnection();
        return (int) static::$db->lastInsertId();
    }

    /**
     * Récupère la derniere erreur sur la l'object PDO
     * 
     * @return array
     */
    public static function getLastErreur()
    {
        return [
            "pdo" => static::$currentPdoErrorInfo,
            "stmt" => static::$currentPdoStementErrorInfo
        ];
    }

    /**
     * makeQuery, fonction permettant de générer des SQL Statement à la volé.
     *
     * @param array $options, ensemble d'information
     * @param callable $cb = null
     * @return string $query, la SQL Statement résultant
     */
    private static function makeQuery($options, $cb = null)
    {
        /** NOTE:
         *	 | - where
         *	 | - order
         *	 | - limit | take.
         *	 | - grby
         *	 | - join
         *
         *	 Si vous spécifiez un join veillez définir des alias
         *	 $options = [
         *	 	"type" => SELECT,
         * 		"table" => "table",
         *	 	"join" => [
         * 			"otherTable" => "otherTable",
         *	 		"on" => [
         *	 			"T.id",
         *	 			"O.parentId"
         *	 		]
         *	 	],
         *	 	"where" => "R.r_num = " . $currentRegister,
         *	 	"order" => ["column", true],
         *	 	"limit" => "1, 5",
         *	 	"grby" => "column"
         *	 ];
         */
        $query = "";
        switch ($options['type']) {
            /**
             * Niveau équivalant à un quelconque SQL Statement de type:
             *  _________________
             * | SELECT ? FROM ? |
             *  -----------------
             */
            case self::SELECT:
                /**
                 * Initialisation de variable à usage simple
                 */
                $join  = '';
                $where = '';
                $order = '';
                $limit = '';
                $grby  = '';
                $between = '';

                if (isset($options["join"])) {
                    $join = " INNER JOIN " . $options['join']["otherTable"] . " ON " . implode(" = ", $options['join']['on']);
                }
                /*
                 * Vérification de l'existance d'un clause:
                 * _______
                 *| WHERE |
                 * -------
                 */
                if (isset($options['where'])) {
                    $where = " WHERE " . $options['where'];
                }
                /*
                 *Vérification de l'existance d'un clause:
                 * __________
                 *| ORDER BY |
                 * ----------
                 */
                if (isset($options['-order'])) {
                    $order = " ORDER BY " . (is_array($options['-order']) ? implode(", ", $options["-order"]) : $options["-order"]) . " DESC";
                } else if (isset($options['+order'])) {
                    $order = " ORDER BY " . (is_array($options['+order']) ? implode(", ", $options["+order"]) : $options["+order"]) . " ASC";
                }

                /*
                 * Vérification de l'existance d'un clause:
                 * _______
                 *| LIMIT |
                 * -------
                 */
                if (isset($options['limit']) || isset($options["take"])) {
                    if (isset($options['limit'])) {
                        $param = $options['limit'];
                    } else {
                        $param = $options['take'];
                    }
                    $param = is_array($param) ? implode(", ", $param) : $param;
                    $limit = " LIMIT " . $param;
                }

                /**
                 * Vérification de l'existance d'un clause:
                 * ----------
                 *| GROUP BY |
                 * ----------
                 */
                if (isset($options->grby)) {
                    $grby = " GROUP BY " . $options['grby'];
                }
                if (isset($options["data"])) {
                    if (is_array($options["data"])) {
                        $data = implode(", ", $options['data']);
                    } else {
                        $data = $options['data'];
                    }
                } else {
                    $data = "*";
                }
                /**
                 * Vérification de l'existance d'un clause:
                 * ----------
                 *| BETWEEN  |
                 * ----------
                 */

                if (isset($options["between"])) {
                    $between = $options[0] . " NOT BETWEEN " . implode(" AND ", $options["between"]);
                } else if (isset($options["-between"])) {
                    $between = $options[0] . " BETWEEN " . implode(" AND ", $options["between"][1]);
                }

                /**
                 * Edition de la SQL Statement facultatif.
                 * construction de la SQL Statement finale.
                 */
                $query = "SELECT " . $data . " FROM " . $options['table'] . $join . $where . ($where !== "" ? $between : "") . $order . $limit . $grby;
                break;
            /**
             * Niveau équivalant à un quelconque
             * SQL Statement de type:
             * _____________
             *| INSERT INTO |
             * -------------
             */
            case self::INSERT:
                /**
                 * Sécurisation de donnée.
                 */
                $field = self::rangeField($options['data']);
                /**
                 * Edition de la SQL Statement facultatif.
                 */
                $query = "INSERT INTO " . $options['table'] . " SET " . $field;
                break;
            /**
             * Niveau équivalant à un quelconque
             * SQL Statement de type:
             * ________
             *| UPDATE |
             * --------
             */
            case self::UPDATE:
                /**
                 * Sécurisation de donnée.
                 */
                $field = self::rangeField($options['data']);
                /**
                 * Edition de la SQL Statement facultatif.
                 */
                $query = "UPDATE " . $options['table'] . " SET " . $field . " WHERE " . $options['where'];
                break;
            /**
             * Niveau équivalant à un quelconque
             * SQL Statement de type:
             * _____________
             *| DELETE FROM |
             * -------------
             */
            case self::DELETE:
                /**
                 * Edition de la SQL Statement facultatif.
                 */
                $query = "DELETE FROM " . implode(", ", $options['table']) . " WHERE " . $options['where'];
                break;
        }
        /**
         * Vérification de l'existance de la fonction de callback
         */
        if ($cb !== null) {
            /** NOTE:
             * Execution de la fonction de rappel,
             * qui récupère une erreur ou la query
             * pour évantuel vérification
             */
            call_user_func($cb, isset($query) ? $query : E_ERROR);
        }
        return $query;
    }

    /**
     * Execute Les request de type delete insert update
     *
     * @param $sqlstatement
     * @param array $bind
     * @return mixed
     */
    private static function executePrepareQuery($sqlstatement, array $bind = [])
    {
        $pdostatement = static::$db->prepare($sqlstatement);
        
        static::bind($pdostatement, $bind);
        $pdostatement->execute();
        var_dump($pdostatement->errorInfo());
        static::$currentPdoStementErrorInfo = $pdostatement->errorInfo();
        static::$currentPdoErrorInfo = static::$db->errorInfo();

        return $pdostatement->rowCount();
    }

}