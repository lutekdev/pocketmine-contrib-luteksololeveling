<?php

declare( strict_types = 1 );

namespace LutekDev\LutekSoloLeveling\database;

use LutekDev\LutekSoloLeveling\Main;
use pocketmine\player\Player;
use pocketmine\utils\SingletonTrait;

class Database {
    use SingletonTrait;
    
    private \SQLite3 $db;
    
    public function __construct ( private readonly Main $plugin ) {
        $dataFolder = $this->plugin->getDataFolder();
        
        self::setInstance( $this );
        
        $this->db = new \SQLite3( $dataFolder . "players.db" );
        $this->init();
    }
    
    private function init () : void {
        // Cria a tabela se não existir
        $this->db->exec( "CREATE TABLE IF NOT EXISTS stats (
            uuid TEXT PRIMARY KEY,
            username TEXT,
            vitality INTEGER DEFAULT 10,
            agility INTEGER DEFAULT 10,
            intelligence INTEGER DEFAULT 10,
            strength INTEGER DEFAULT 10,
            sense INTEGER DEFAULT 10,
            defense INTEGER DEFAULT 5,
            level INTEGER DEFAULT 1,
            xp FLOAT DEFAULT 0
        )" );
    }
    
    public function loadPlayer ( Player $player ) : array {
        $stmt = $this->db->prepare( "SELECT * FROM stats WHERE uuid = :uuid" );
        $stmt->bindValue( ":uuid", $player->getUniqueId()->toString(), SQLITE3_TEXT );
        $result = $stmt->execute();
        
        $data = $result->fetchArray( SQLITE3_ASSOC );
        $result->finalize(); // Limpa memória
        
        if ($data === false) {
            // Jogador novo, retorna array vazio para usar defaults
            return [];
        }
        return $data;
    }
    
    public function savePlayer ( Player $player, array $stats ) : void {
        $stmt = $this->db->prepare( "INSERT OR REPLACE INTO stats (uuid, username, vitality, agility, intelligence, strength, sense, defense, level, xp)
            VALUES (:uuid, :username, :vit, :agi, :int, :str, :sen, :def, :lvl, :xp)" );
        
        $stmt->bindValue( ":uuid", $player->getUniqueId()->toString() );
        $stmt->bindValue( ":username", strtolower( $player->getName() ) );
        $stmt->bindValue( ":vit", $stats[ 'vitality' ] );
        $stmt->bindValue( ":agi", $stats[ 'agility' ] );
        $stmt->bindValue( ":int", $stats[ 'intelligence' ] );
        $stmt->bindValue( ":str", $stats[ 'strength' ] );
        $stmt->bindValue( ":sen", $stats[ 'sense' ] );
        $stmt->bindValue( ":def", $stats[ 'defense' ] );
        $stmt->bindValue( ":lvl", $stats[ 'level' ] );
        $stmt->bindValue( ":xp", $stats[ 'xp' ] ?? 0 );
        
        $stmt->execute();
    }
}