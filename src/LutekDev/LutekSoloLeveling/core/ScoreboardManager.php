<?php

declare( strict_types = 1 );

namespace LutekDev\LutekSoloLeveling\core;

use pocketmine\network\mcpe\protocol\RemoveObjectivePacket;
use pocketmine\network\mcpe\protocol\SetDisplayObjectivePacket;
use pocketmine\network\mcpe\protocol\SetScorePacket;
use pocketmine\network\mcpe\protocol\types\ScorePacketEntry;
use pocketmine\player\Player;

class ScoreboardManager {
    
    private const string OBJECTIVE_NAME = "solo_leveling";
    
    public static function updateBoard ( Player $player, PlayerSession $session ) : void {
        // Formatar valores para ficar bonito (Ex: 100/100)
        $hp = (int) $session->getHp();
        $maxHp = (int) $session->getMaxHp();
        $mp = (int) $session->getMp();
        $maxMp = (int) $session->getMaxMp();
        $lvl = $session->getLevel();
        
        // Linhas da Scoreboard
        $lines = [
            1 => "§l§eSolo Leveling RPG",
            2 => "§r ", // Espaço vazio
            3 => "§fNome: §a" . $player->getName(),
            4 => "§fLevel: §e" . $lvl,
            5 => "§r  ",
            6 => "§cHP: " . $hp . " / " . $maxHp,
            7 => "§bMP: " . $mp . " / " . $maxMp,
            8 => "§r   ",
            9 => "§7lutekdev.com"
        ];
        
        self::sendLines( $player, $lines );
    }
    
    private static function sendLines ( Player $player, array $lines ) : void {
        // Remover anterior (Limpa a tela para atualizar)
        $pkRemove = RemoveObjectivePacket::create( self::OBJECTIVE_NAME );
        $player->getNetworkSession()->sendDataPacket( $pkRemove );
        
        // Criar novo objetivo
        $pkCreate = SetDisplayObjectivePacket::create( "sidebar", self::OBJECTIVE_NAME, "§6§lSTATUS", // Título
            "dummy", 0 );
        $player->getNetworkSession()->sendDataPacket( $pkCreate );
        
        // Enviar as linhas
        $entries = [];
        foreach ($lines as $score => $text) {
            $entry = new ScorePacketEntry();
            $entry->objectiveName = self::OBJECTIVE_NAME;
            $entry->type = ScorePacketEntry::TYPE_FAKE_PLAYER;
            $entry->customName = $text;
            $entry->score = $score;
            $entry->scoreboardId = $score;
            $entries[] = $entry;
        }
        
        $pkScore = SetScorePacket::create( SetScorePacket::TYPE_CHANGE, $entries );
        $player->getNetworkSession()->sendDataPacket( $pkScore );
    }
}