<?php
    require_once('Config.php');

    class Database {
        private static $writeDBConnection;
        private static $readDBConnection;

        public static function connectWriteDB() {
            if (self::$writeDBConnection === null) {
                self::$writeDBConnection = new PDO(Config::HOST_URL, Config::USERNAME, Config::PASSWORD);
                self::$writeDBConnection->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                self::$writeDBConnection->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
            }

            return self::$writeDBConnection;
        }
        
        public static function connectReadDB() {
            if (self::$readDBConnection === null) {
                self::$readDBConnection = new PDO(Config::HOST_URL, Config::USERNAME, Config::PASSWORD);
                self::$readDBConnection->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                self::$readDBConnection->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
            }

            return self::$readDBConnection;
        }
    }
